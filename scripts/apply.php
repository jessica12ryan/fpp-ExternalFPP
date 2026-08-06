#!/usr/bin/php
<?php
/**
 * #############################################################
 * ## External FPP Web Access (fpp-ExternalFPP)               ##
 * ## Author: jessica12ryan                                ##
 * ## URL: https://github.com/jessica12ryan/fpp-ExternalFPP##
 * #############################################################
 * ## scripts/apply.php                                       ##
 * ## Applies plugin settings to Apache:                      ##
 * ##  1. Writes the .htpasswd file (bcrypt, from stored hashes)
 * ##  2. Writes /etc/apache2/conf-available/fpp-externalfpp.conf
 * ##  3. Enables/disables the conf via a2enconf/a2disconf    ##
 * ##  4. Validates and reloads Apache                        ##
 * ##                                                         ##
 * ## May be run as root (from fpp_install.sh) or as the fpp  ##
 * ## user (from api.php). Privileged commands are prefixed   ##
 * ## with sudo when not running as root.                     ##
 * #############################################################
 */

define('PLUGIN_DIR', dirname(__DIR__));
define('SETTINGS_FILE', PLUGIN_DIR . '/config/settings.json');
define('HTPASSWD_FILE', PLUGIN_DIR . '/config/plugin.fpp-ExternalFPP.htpasswd');
define('APACHE_CONF_FILE', '/etc/apache2/conf-available/fpp-externalfpp.conf');
define('APACHE_CONF_NAME', 'fpp-externalfpp');
define('REALM', 'FPP External Web Access');
define('LOGIN_PAGE_URL', '/login.html');
define('LOGIN_PAGE_DIR', PLUGIN_DIR . '/www');
define('LOGIN_PAGE_FILE', LOGIN_PAGE_DIR . '/login.html');
define('LOGIN_PAGE_TEMPLATE', PLUGIN_DIR . '/templates/login.html');
define('CHANGE_PW_FILE', LOGIN_PAGE_DIR . '/change-password.html');
define('CHANGE_PW_TEMPLATE', PLUGIN_DIR . '/templates/change-password.html');
define('CHANGE_PW_URL', '/change-password.html');
 // After a successful form login, mod_auth_form redirects here. It is a plugin
 // API endpoint that 302s the visitor to either the FPP UI or the change-password
 // page (when their account needs a forced change), so the change-password page
 // never briefly appears for accounts that do not require it.
define('LOGIN_SUCCESS_URL', '/api/plugin/fpp-ExternalFPP/login-success');
define('SESSION_COOKIE', 'fppefpp');
define('FPP_LOG_DIR', getenv('LOGDIR') ?: '/home/fpp/media/logs');
define('FPP_LOG_FILE', FPP_LOG_DIR . '/plugin-fpp-ExternalFPP.log');
define('BACKEND_DEFAULT_PORT', 80);
define('HTTPS_DEFAULT_PORT', 8443);
define('SSL_CERT_FILE', '/etc/ssl/certs/ssl-cert-snakeoil.pem');
define('SSL_KEY_FILE', '/etc/ssl/private/ssl-cert-snakeoil.key');

function efppIsRoot() {
    if (function_exists('posix_geteuid')) {
        return posix_geteuid() === 0;
    }
    return getenv('USER') === 'root';
}

function efppRun($cmd) {
    $full = (efppIsRoot() ? '' : 'sudo ') . $cmd;
    $output = array();
    $code = 0;
    exec($full . ' 2>&1', $output, $code);
    return array('code' => $code, 'output' => trim(implode("\n", $output)));
}

function efppLog($msg) {
    @file_put_contents(FPP_LOG_FILE, date('Y-m-d H:i:s') . ' fpp-ExternalFPP apply: ' . $msg . "\n", FILE_APPEND | LOCK_EX);
}

function efppLoadSettings() {
    $defaults = array(
        'port' => 8080,
        'backend_port' => BACKEND_DEFAULT_PORT,
        'https_port' => HTTPS_DEFAULT_PORT,
        'enable_http' => 0,
        'enable_https' => 0,
        'users' => array()
    );
    if (!file_exists(SETTINGS_FILE)) {
        return $defaults;
    }
    $s = json_decode(file_get_contents(SETTINGS_FILE), true);
    if (!is_array($s)) {
        return $defaults;
    }
    $s = array_merge($defaults, $s);
    // Migrate the old single username/password layout to the users list.
    if (empty($s['users']) && !empty($s['username']) && !empty($s['password'])) {
        $s['users'] = array(array('username' => $s['username'], 'password' => $s['password']));
    }
    unset($s['username'], $s['password']);
    return $s;
}

function efppUsersFromSettings($s) {
    $users = array();
    foreach (($s['users'] ?? array()) as $u) {
        if (is_array($u) && isset($u['username']) && trim((string)$u['username']) !== '') {
            $users[] = array(
                'username' => trim((string)$u['username']),
                'password' => (string)($u['password'] ?? '')
            );
        }
    }
    return $users;
}

function efppPasswordIsHash($password) {
    return is_string($password) && preg_match('/^\$2[abxy]\$/', $password) === 1;
}

function efppHashPassword($plain) {
    return password_hash((string)$plain, PASSWORD_BCRYPT);
}

/**
 * Converts any plaintext passwords in the settings file to bcrypt hashes and
 * saves the file back, so passwords are never stored in the clear.
 */
function efppMigratePasswordHashes(&$s) {
    $changed = false;
    foreach (($s['users'] ?? array()) as $i => $u) {
        if (is_array($u) && isset($u['password']) && !efppPasswordIsHash($u['password'])) {
            $s['users'][$i]['password'] = efppHashPassword($u['password']);
            $changed = true;
        }
    }
    if ($changed) {
        @file_put_contents(SETTINGS_FILE, json_encode($s, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
        efppLog('Hashed plaintext passwords in ' . SETTINGS_FILE);
    }
}

/**
 * Writes the Apache password file from the stored bcrypt hashes.
 */
function efppEnsureConfigDir() {
    if (!is_dir(PLUGIN_DIR . '/config')) {
        @mkdir(PLUGIN_DIR . '/config', 0775, true);
    }
}

function efppDefaultLoginPage() {
    if (file_exists(LOGIN_PAGE_TEMPLATE)) {
        $c = @file_get_contents(LOGIN_PAGE_TEMPLATE);
        if (is_string($c) && $c !== '') {
            return $c;
        }
    }
    // Fallback if the bundled template is missing.
    return '<!DOCTYPE html><html><body style="font-family:sans-serif;background:#1c1e21;color:#eee;text-align:center;padding:60px;">'
        . '<h1>External FPP Web Access</h1>'
        . '<form method="post" action="/">'
        . '<input type="text" name="httpd_username" placeholder="Username"><br><br>'
        . '<input type="password" name="httpd_password" placeholder="Password"><br><br>'
        . '<button type="submit">Sign In</button></form></body></html>';
}

function efppDefaultChangePasswordPage() {
    if (file_exists(CHANGE_PW_TEMPLATE)) {
        $c = @file_get_contents(CHANGE_PW_TEMPLATE);
        if (is_string($c) && $c !== '') {
            return $c;
        }
    }
    return '<!DOCTYPE html><html><body style="font-family:sans-serif;background:#1c1e21;color:#eee;text-align:center;padding:60px;">'
        . '<h1>Change Password</h1>'
        . '<p id="efpp_message">Please set a new password.</p>'
        . '<input type="password" id="efpp_password" placeholder="New password"><br><br>'
        . '<input type="password" id="efpp_password_confirm" placeholder="Confirm new password"><br><br>'
        . '<button onclick="efppSubmit()">Change Password</button>'
        . '<script>'
        . 'function efppSubmit(){var p=document.getElementById("efpp_password").value,c=document.getElementById("efpp_password_confirm").value;'
        . 'fetch("/api/plugin/fpp-ExternalFPP/change-my-password",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({password:p,password_confirm:c})})'
        . '.then(function(r){return r.json();}).then(function(d){if(d.success){window.location.href="/";}else{document.getElementById("efpp_message").textContent=(d.errors||[]).join(" ");}});}'
        . '</script></body></html>';
}

/**
 * Makes sure the user-editable login page exists. It lives in the plugin's
 * www/ directory (separate from config/ so Apache can be granted read access
 * to it without exposing the settings file or the password file).
 */
function efppEnsureLoginPage() {
    efppEnsureConfigDir();
    if (!is_dir(LOGIN_PAGE_DIR)) {
        @mkdir(LOGIN_PAGE_DIR, 0775, true);
    }
    if (!file_exists(LOGIN_PAGE_FILE)) {
        if (@file_put_contents(LOGIN_PAGE_FILE, efppDefaultLoginPage()) === false) {
            efppLog('WARNING: could not create the login page at ' . LOGIN_PAGE_FILE);
            return false;
        }
        efppRun('chown fpp:fpp ' . escapeshellarg(LOGIN_PAGE_FILE) . ' 2>/dev/null');
        efppRun('chmod 644 ' . escapeshellarg(LOGIN_PAGE_FILE) . ' 2>/dev/null');
    }
    return true;
}

function efppEnsureChangePasswordPage() {
    efppEnsureConfigDir();
    if (!is_dir(LOGIN_PAGE_DIR)) {
        @mkdir(LOGIN_PAGE_DIR, 0775, true);
    }
    if (!file_exists(CHANGE_PW_FILE)) {
        if (@file_put_contents(CHANGE_PW_FILE, efppDefaultChangePasswordPage()) === false) {
            efppLog('WARNING: could not create the change password page at ' . CHANGE_PW_FILE);
            return false;
        }
        efppRun('chown fpp:fpp ' . escapeshellarg(CHANGE_PW_FILE) . ' 2>/dev/null');
        efppRun('chmod 644 ' . escapeshellarg(CHANGE_PW_FILE) . ' 2>/dev/null');
    }
    return true;
}

function efppEnsurePages() {
    return efppEnsureLoginPage() && efppEnsureChangePasswordPage();
}

/**
 * Writes every user to the Apache password file. Uses the stored bcrypt hash
 * directly (Apache accepts $2y$ natively), so no plaintext is ever written.
 * Returns array(true, message) on success, array(false, error) on failure.
 */
function efppWriteHtpasswd($users) {
    $users = efppUsersFromSettings(array('users' => $users));

    // Normalize ownership so the web server (fpp user) can always read/overwrite it.
    efppRun('chown fpp:fpp ' . escapeshellarg(HTPASSWD_FILE) . ' 2>/dev/null');
    efppRun('chmod 664 ' . escapeshellarg(HTPASSWD_FILE) . ' 2>/dev/null');

    if (empty($users)) {
        if (file_exists(HTPASSWD_FILE)) {
            @unlink(HTPASSWD_FILE);
        }
        return array(true, 'Removed password file (no users configured)');
    }

    $content = '';
    foreach ($users as $u) {
        $hash = efppPasswordIsHash($u['password']) ? $u['password'] : efppHashPassword($u['password']);
        $content .= $u['username'] . ':' . $hash . "\n";
    }
    if (@file_put_contents(HTPASSWD_FILE, $content) === false) {
        return array(false, 'Could not write the password file to ' . HTPASSWD_FILE);
    }
    efppRun('chown fpp:fpp ' . escapeshellarg(HTPASSWD_FILE) . ' 2>/dev/null');
    efppRun('chmod 644 ' . escapeshellarg(HTPASSWD_FILE) . ' 2>/dev/null');
    return array(true, 'Password file written for ' . count($users) . ' user(s) using bcrypt');
}

/**
 * Emits the shared body of a protected vhost (proxy, headers, login page,
 * session, logout, and form auth). $https toggles the SSL directives and adds
 * the https ProxyPassReverse fallback. Returns an array of lines.
 */
function efppBuildVhostBody($backendPort, $htpasswdFile, $loginPageFile, $changePwFile, $https) {
    $backendPort = (int)$backendPort;
    $loginPageDir = dirname($loginPageFile);
    $lines = array();

    if ($https) {
        $lines[] = '    SSLEngine on';
        $lines[] = '    SSLCertificateFile ' . SSL_CERT_FILE;
        $lines[] = '    SSLCertificateKeyFile ' . SSL_KEY_FILE;
        $lines[] = '';
    }

    $lines[] = '    # Never let this vhost be used as an open proxy';
    $lines[] = '    ProxyRequests Off';
    $lines[] = '';
    $lines[] = '    # Keep the original Host header so FPP cookies/sessions work';
    $lines[] = '    ProxyPreserveHost On';
    $lines[] = '    ProxyTimeout 1200';
    $lines[] = '    LimitRequestBody 4398046511104';
    $lines[] = '';
    $lines[] = '    # Never forward the browser\'s credentials to FPP. The login uses a session';
    $lines[] = '    # cookie below, so no Authorization header is ever sent upstream, and this';
    $lines[] = '    # guard keeps a stray Basic header from being validated (and rejected) by';
    $lines[] = '    # FPP\'s own password file, which would re-trigger a browser prompt.';
    $lines[] = '    RequestHeader unset Authorization';
    $lines[] = '';
    $lines[] = '    # Tell the backend which external user is logged in (from the session set by';
    $lines[] = '    # mod_auth_form). The header is replaced server-side, never trusted from the';
    $lines[] = '    # client, so it is safe for the API to use for "change my own password".';
    $lines[] = '    RequestHeader unset X-Remote-User';
    $lines[] = '    RequestHeader set X-Remote-User "%{REMOTE_USER}s"';
    $lines[] = '    # Forward the real client IP to the backend, which the plugin logs on';
    $lines[] = '    # login (the proxy would otherwise make REMOTE_ADDR look like 127.0.0.1).';
    $lines[] = '    # Replaced server-side, never trusted from the client.';
    $lines[] = '    RequestHeader unset X-Forwarded-For';
    $lines[] = '    RequestHeader set X-Forwarded-For "%{REMOTE_ADDR}s"';
    $lines[] = '';
    $lines[] = '    # Serve the login page directly from the plugin instead of proxying it.';
    $lines[] = '    # The "!" marks the URL as not-proxied so the Alias below can serve it.';
    $lines[] = '    ProxyPass ' . LOGIN_PAGE_URL . ' !';
    $lines[] = '    ProxyPass ' . CHANGE_PW_URL . ' !';
    $lines[] = '    # Keep the logout handler local too (ProxyPass would otherwise override';
    $lines[] = '    # its SetHandler and send /logout to the FPP backend).';
    $lines[] = '    ProxyPass /logout !';
    $lines[] = '    Alias ' . LOGIN_PAGE_URL . ' ' . $loginPageFile;
    $lines[] = '    Alias ' . CHANGE_PW_URL . ' ' . $changePwFile;
    $lines[] = '    <Directory ' . $loginPageDir . '>';
    $lines[] = '        Require all granted';
    $lines[] = '    </Directory>';
    $lines[] = '    # The login page must be reachable without a session or logging in loops';
    $lines[] = '    <Location ' . LOGIN_PAGE_URL . '>';
    $lines[] = '        AuthType None';
    $lines[] = '        Require all granted';
    $lines[] = '    </Location>';
    $lines[] = '';
    $lines[] = '    # Session cookie used by the form login. HTTP-only so page scripts can\'t read it.';
    $lines[] = '    Session On';
    $lines[] = '    SessionCookieName ' . SESSION_COOKIE . ' path=/; httponly';
    $lines[] = '';
    $lines[] = '    # Logout: clears the session and returns the visitor to the login page.';
    $lines[] = '    # (form-logout-handler only runs after a valid session, so anonymous';
    $lines[] = '    #  visitors are simply redirected to the login page as usual.)';
    $lines[] = '    <Location /logout>';
    $lines[] = '        SetHandler form-logout-handler';
    $lines[] = '        AuthFormLogoutLocation ' . LOGIN_PAGE_URL;
    $lines[] = '    </Location>';
    $lines[] = '';
    $lines[] = '    ProxyPass / http://127.0.0.1:' . $backendPort . '/';
    $lines[] = '    ProxyPassReverse / http://127.0.0.1:' . $backendPort . '/';
    $lines[] = '    ProxyPassReverse / https://127.0.0.1:' . $backendPort . '/';
    $lines[] = '';
    $lines[] = '    <Proxy *>';
    $lines[] = '        Require all granted';
    $lines[] = '    </Proxy>';
    $lines[] = '';
    $lines[] = '    # Everything except the login page is protected by a form login. Requests';
    $lines[] = '    # without a valid session are redirected to the login page; the login form';
    $lines[] = '    # POSTs back here and on success Apache sets the session cookie.';
    $lines[] = '    <LocationMatch "^/(?!login\\.html)">';
    $lines[] = '        AuthType Form';
    $lines[] = '        AuthName "' . REALM . '"';
    $lines[] = '        AuthFormProvider file';
    $lines[] = '        AuthUserFile ' . $htpasswdFile;
    $lines[] = '        AuthFormLoginRequiredLocation ' . LOGIN_PAGE_URL;
    $lines[] = '        AuthFormLoginSuccessLocation ' . LOGIN_SUCCESS_URL;
    $lines[] = '        AuthFormLogoutLocation ' . LOGIN_PAGE_URL;
    $lines[] = '        Require valid-user';
    $lines[] = '    </LocationMatch>';
    return $lines;
}

function efppBuildApacheConf($port, $httpsPort, $backendPort, $htpasswdFile, $loginPageFile, $changePwFile, $enableHttp, $enableHttps) {
    $port = (int)$port;
    $httpsPort = (int)$httpsPort;
    $backendPort = (int)$backendPort;
    $lines = array();

    $lines[] = '# fpp-ExternalFPP - additional password-protected port for the FPP web UI';
    $lines[] = '# Generated by the External FPP plugin. Do not edit manually.';
    if ($enableHttp && $port > 0) {
        $lines[] = 'Listen ' . $port;
    }
    if ($enableHttps && $httpsPort > 0 && $httpsPort !== $port) {
        $lines[] = 'Listen ' . $httpsPort;
    }
    $lines[] = '';

    // HTTP vhost: a password-protected reverse proxy.
    if ($enableHttp && $port > 0) {
        $lines[] = '<VirtualHost *:' . $port . '>';
        $lines[] = '    ServerAdmin webmaster@localhost';
        $lines[] = '    ServerName localhost';
        $lines[] = '    ServerAlias *';
        $lines[] = '';
        $lines[] = '    ErrorLog /home/fpp/media/logs/apache2-externalfpp-error.log';
        $lines[] = '    CustomLog /home/fpp/media/logs/apache2-externalfpp-access.log combined';
        $lines[] = '';
        $lines = array_merge($lines, efppBuildVhostBody($backendPort, $htpasswdFile, $loginPageFile, $changePwFile, false));
        $lines[] = '</VirtualHost>';
        $lines[] = '';
    }

    // HTTPS vhost: password-protected reverse proxy with TLS.
    if ($enableHttps && $httpsPort > 0 && $httpsPort !== $port) {
        $lines[] = '<VirtualHost *:' . $httpsPort . '>';
        $lines[] = '    ServerAdmin webmaster@localhost';
        $lines[] = '    ServerName localhost';
        $lines[] = '    ServerAlias *';
        $lines[] = '';
        $lines[] = '    ErrorLog /home/fpp/media/logs/apache2-externalfpp-error.log';
        $lines[] = '    CustomLog /home/fpp/media/logs/apache2-externalfpp-access.log combined';
        $lines[] = '';
        $lines = array_merge($lines, efppBuildVhostBody($backendPort, $htpasswdFile, $loginPageFile, $changePwFile, true));
        $lines[] = '</VirtualHost>';
        $lines[] = '';
    }

    return implode("\n", $lines) . "\n";
}

function efppReloadApache() {
    $r = efppRun('systemctl reload apache2');
    if ($r['code'] === 0) {
        return array(true, '');
    }
    // Fallback for systems without systemd
    $r2 = efppRun('apachectl -k graceful');
    if ($r2['code'] === 0) {
        return array(true, '');
    }
    return array(false, 'Apache reload failed: ' . $r['output'] . ' ' . $r2['output']);
}

function efppModuleEnabled($name) {
    return file_exists('/etc/apache2/mods-enabled/' . $name . '.load')
        || file_exists('/etc/apache2/mods-enabled/' . $name . '.conf');
}

function efppEnableModules() {
    // mod_auth_form needs mod_request to work, otherwise Apache fails to start
    // (AH02618) even though 'apachectl configtest' passes.
    $required = array('proxy', 'proxy_http', 'headers', 'authn_file', 'auth_form', 'session', 'session_cookie', 'request', 'alias', 'ssl', 'rewrite');
    $missing = array();
    $newlyEnabled = array();
    foreach ($required as $m) {
        $wasEnabled = efppModuleEnabled($m);
        efppRun('a2enmod ' . $m . ' >/dev/null 2>&1');
        if (!efppModuleEnabled($m)) {
            $missing[] = $m;
        } elseif (!$wasEnabled) {
            $newlyEnabled[] = $m;
        }
    }
    return array($missing, $newlyEnabled);
}

function efppPortListening($port) {
    $port = (int)$port;
    if ($port < 1 || $port > 65535) {
        return false;
    }
    $fp = @fsockopen('127.0.0.1', $port, $errno, $errstr, 2);
    if ($fp) {
        fclose($fp);
        return true;
    }
    return false;
}

/**
 * Apache is "healthy" when the service is active AND the given port is
 * listening. After a reload/restart Apache may need a moment to bind new
 * listeners, so the port check retries for a few seconds instead of failing on
 * the first attempt.
 */
function efppApacheHealthy($port) {
    $r = efppRun('systemctl is-active apache2');
    if ($r['code'] !== 0 || strtolower(trim($r['output'])) !== 'active') {
        return false;
    }
    for ($i = 0; $i < 10; $i++) {
        if (efppPortListening($port)) {
            return true;
        }
        usleep(500000);
    }
    return false;
}

function efppApply() {
    $errors = array();
    $messages = array();

    $s = efppLoadSettings();
    $enabled = ((!empty($s['enable_http'] ?? 0)) && (int)$s['port'] > 0)
        || ((!empty($s['enable_https'] ?? 0)) && (int)$s['https_port'] > 0);
    $port = (int)$s['port'];
    $backendPort = (int)$s['backend_port'];
    $httpsPort = (int)($s['https_port'] ?? HTTPS_DEFAULT_PORT);
    $enableHttp = !empty($s['enable_http'] ?? 0);
    $enableHttps = !empty($s['enable_https'] ?? 0);

    // Passwords are stored as bcrypt hashes; hash any legacy plaintext on disk.
    efppMigratePasswordHashes($s);
    $users = efppUsersFromSettings($s);

    efppEnsureConfigDir();
    efppEnsurePages();

    // Always make sure the required Apache modules are present (idempotent).
    list($missingMods, $newlyEnabledMods) = efppEnableModules();
    if (!empty($missingMods)) {
        $msg = 'Required Apache modules could not be enabled: ' . implode(', ', $missingMods) . '.';
        efppLog('ERROR: ' . $msg);
        efppRun('a2disconf ' . APACHE_CONF_NAME . ' >/dev/null 2>&1');
        return array('success' => false, 'errors' => array($msg), 'messages' => $messages);
    }

    if ($enableHttp) {
        if ($port < 1 || $port > 65535) {
            $errors[] = 'Invalid HTTP port "' . htmlspecialchars((string)$s['port'], ENT_QUOTES) . '". Choose a value between 1 and 65535.';
        }
        if ($port === $backendPort) {
            $errors[] = 'The HTTP port and the backend (FPP web) port must be different.';
        }
    }
    if ($backendPort < 1 || $backendPort > 65535) {
        $errors[] = 'Invalid backend (FPP web) port "' . htmlspecialchars((string)$s['backend_port'], ENT_QUOTES) . '". Choose a value between 1 and 65535.';
    }
    if ($enableHttps) {
        if ($httpsPort < 1 || $httpsPort > 65535) {
            $errors[] = 'Invalid HTTPS port "' . htmlspecialchars((string)$s['https_port'], ENT_QUOTES) . '". Choose a value between 1 and 65535.';
        }
        if ($httpsPort === $backendPort) {
            $errors[] = 'The HTTPS port must be different from the backend (FPP web) port.';
        }
        if (!file_exists(SSL_CERT_FILE)) {
            $errors[] = 'HTTPS is enabled but no TLS certificate was found at ' . SSL_CERT_FILE . '.';
        }
    }
    if ($enableHttp && $enableHttps && $httpsPort === $port) {
        $errors[] = 'The HTTP port and the HTTPS port must be different.';
    }
    if ($enabled && empty($users)) {
        $errors[] = 'The plugin is enabled but no users are configured. Create at least one user before enabling.';
    }

    if (!empty($errors)) {
        // Never leave an active listener pointing at an invalid config.
        efppRun('a2disconf ' . APACHE_CONF_NAME . ' >/dev/null 2>&1');
        efppReloadApache();
        foreach ($errors as $e) {
            efppLog('ERROR: ' . $e);
        }
        return array('success' => false, 'errors' => $errors, 'messages' => $messages);
    }

    list($ok, $msg) = efppWriteHtpasswd($users);
    $messages[] = $msg;
    if (!$ok) {
        efppLog('ERROR: ' . $msg);
        return array('success' => false, 'errors' => array($msg), 'messages' => $messages);
    }

    if ($enabled) {
        $conf = efppBuildApacheConf($port, $httpsPort, $backendPort, HTPASSWD_FILE, LOGIN_PAGE_FILE, CHANGE_PW_FILE, $enableHttp, $enableHttps);
        $tmp = APACHE_CONF_FILE . '.tmp';
        if (@file_put_contents($tmp, $conf) === false) {
            $msg = 'Could not write Apache config to ' . APACHE_CONF_FILE;
            efppLog('ERROR: ' . $msg);
            return array('success' => false, 'errors' => array($msg), 'messages' => $messages);
        }
        @rename($tmp, APACHE_CONF_FILE);

        $r = efppRun('a2enconf ' . APACHE_CONF_NAME);
        if ($r['code'] !== 0) {
            $msg = 'Could not enable Apache config (a2enconf): ' . $r['output'];
            efppLog('ERROR: ' . $msg);
            return array('success' => false, 'errors' => array($msg), 'messages' => $messages);
        }

        $r = efppRun('apachectl configtest');
        if ($r['code'] !== 0) {
            efppRun('a2disconf ' . APACHE_CONF_NAME . ' >/dev/null 2>&1');
            $msg = 'Apache configuration test failed, external port left disabled: ' . $r['output'];
            efppLog('ERROR: ' . $msg);
            return array('success' => false, 'errors' => array($msg), 'messages' => $messages);
        }

        if (!empty($newlyEnabledMods)) {
            // a2enmod only loads new modules on a full restart (a graceful
            // reload ignores them), so restart rather than reload here.
            $ok2 = false;
            $r = efppRun('systemctl restart apache2');
            if ($r['code'] === 0) {
                $ok2 = true;
            } else {
                $r2 = efppRun('apachectl -k restart');
                if ($r2['code'] === 0) {
                    $ok2 = true;
                } else {
                    $err2 = 'Apache restart failed: ' . $r['output'] . ' ' . $r2['output'];
                }
            }
            if (!$ok2) {
                efppRun('a2disconf ' . APACHE_CONF_NAME . ' >/dev/null 2>&1');
                $msg = $err2 . ' The external port was disabled.';
                efppLog('ERROR: ' . $msg);
                return array('success' => false, 'errors' => array($msg), 'messages' => $messages);
            }
        } else {
            list($ok2, $err2) = efppReloadApache();
            if (!$ok2) {
                // A reload that kills Apache must never leave the FPP web UI down.
                efppRun('a2disconf ' . APACHE_CONF_NAME . ' >/dev/null 2>&1');
                efppRun('systemctl restart apache2 >/dev/null 2>&1');
                $msg = $err2 . ' The external port was disabled and Apache restarted.';
                efppLog('ERROR: ' . $msg);
                return array('success' => false, 'errors' => array($msg), 'messages' => $messages);
            }
        }
        $healthy = $enableHttp ? efppApacheHealthy($port) : true;
        if ($healthy && $enableHttps && $httpsPort > 0) {
            $healthy = efppApacheHealthy($httpsPort);
        }
        if (!$healthy) {
            efppRun('a2disconf ' . APACHE_CONF_NAME . ' >/dev/null 2>&1');
            efppRun('systemctl restart apache2 >/dev/null 2>&1');
            $msg = 'Apache did not come back healthy after applying the external port configuration. The external port was disabled and Apache restarted.';
            efppLog('ERROR: ' . $msg);
            return array('success' => false, 'errors' => array($msg), 'messages' => $messages);
        }
        $ports = array();
        if ($enableHttp) {
            $ports[] = 'http ' . $port;
        }
        if ($enableHttps) {
            $ports[] = 'https ' . $httpsPort;
        }
        $messages[] = 'External web access enabled on ' . implode(', ', $ports) . '.';
        efppLog('Enabled external web access on ' . implode(' + ', $ports) . ' (backend ' . $backendPort . ')');
    } else {
        efppRun('a2disconf ' . APACHE_CONF_NAME . ' >/dev/null 2>&1');
        list($ok2, $err2) = efppReloadApache();
        $messages[] = 'External web access disabled.';
        efppLog('Disabled external web access');
    }

    return array('success' => true, 'errors' => array(), 'messages' => $messages);
}

if (php_sapi_name() === 'cli') {
    $result = efppApply();
    echo ($result['success'] ? 'OK' : 'ERROR') . "\n";
    foreach ($result['messages'] as $m) {
        echo '  ' . $m . "\n";
    }
    foreach ($result['errors'] as $e) {
        echo '  ' . $e . "\n";
    }
    exit($result['success'] ? 0 : 1);
}
