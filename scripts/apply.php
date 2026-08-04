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
 * ##  1. Writes the .htpasswd file (bcrypt, or {SHA} fallback)
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
define('SESSION_COOKIE', 'fppefpp');
define('FPP_LOG_DIR', getenv('LOGDIR') ?: '/home/fpp/media/logs');
define('FPP_LOG_FILE', FPP_LOG_DIR . '/plugin-fpp-ExternalFPP.log');
define('BACKEND_DEFAULT_PORT', 80);

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
        'enabled' => 0,
        'port' => 8080,
        'backend_port' => BACKEND_DEFAULT_PORT,
        'username' => '',
        'password' => ''
    );
    if (!file_exists(SETTINGS_FILE)) {
        return $defaults;
    }
    $s = json_decode(file_get_contents(SETTINGS_FILE), true);
    if (!is_array($s)) {
        return $defaults;
    }
    return array_merge($defaults, $s);
}

/**
 * Writes the Apache password file. Prefers bcrypt via the htpasswd binary,
 * falls back to a {SHA} hash that every Apache 2.4 build supports.
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

function efppWriteHtpasswd($username, $password) {
    if ($username === '' || $password === '') {
        if (file_exists(HTPASSWD_FILE)) {
            @unlink(HTPASSWD_FILE);
        }
        return array(true, 'Removed password file (no credentials configured)');
    }

    // Normalize ownership so the web server (fpp user) can always read/overwrite it.
    efppRun('chown fpp:fpp ' . escapeshellarg(HTPASSWD_FILE) . ' 2>/dev/null');
    efppRun('chmod 664 ' . escapeshellarg(HTPASSWD_FILE) . ' 2>/dev/null');
    @unlink(HTPASSWD_FILE);

    $r = efppRun('htpasswd -b -B -c ' . escapeshellarg(HTPASSWD_FILE) . ' '
        . escapeshellarg($username) . ' ' . escapeshellarg($password));
    if ($r['code'] === 0 && file_exists(HTPASSWD_FILE)) {
        efppRun('chown fpp:fpp ' . escapeshellarg(HTPASSWD_FILE) . ' 2>/dev/null');
        efppRun('chmod 644 ' . escapeshellarg(HTPASSWD_FILE) . ' 2>/dev/null');
        return array(true, 'Password file written using bcrypt');
    }
    @unlink(HTPASSWD_FILE);

    // Fallback: {SHA} + base64(SHA-1) - accepted by mod_authn_file everywhere.
    $content = $username . ':{SHA}' . base64_encode(sha1($password, true)) . "\n";
    if (@file_put_contents(HTPASSWD_FILE, $content) === false) {
        return array(false, 'Could not write the password file to ' . HTPASSWD_FILE);
    }
    efppRun('chown fpp:fpp ' . escapeshellarg(HTPASSWD_FILE) . ' 2>/dev/null');
    efppRun('chmod 644 ' . escapeshellarg(HTPASSWD_FILE) . ' 2>/dev/null');
    return array(true, 'Password file written using {SHA} fallback (install apache2-utils for bcrypt)');
}

function efppBuildApacheConf($port, $backendPort, $htpasswdFile, $loginPageFile) {
    $port = (int)$port;
    $backendPort = (int)$backendPort;
    $loginPageDir = dirname($loginPageFile);
    $lines = array();
    $lines[] = '# fpp-ExternalFPP - additional password-protected port for the FPP web UI';
    $lines[] = '# Generated by the External FPP plugin. Do not edit manually.';
    $lines[] = 'Listen ' . $port;
    $lines[] = '';
    $lines[] = '<VirtualHost *:' . $port . '>';
    $lines[] = '    ServerAdmin webmaster@localhost';
    $lines[] = '    ServerName localhost';
    $lines[] = '    ServerAlias *';
    $lines[] = '';
    $lines[] = '    ErrorLog /home/fpp/media/logs/apache2-externalfpp-error.log';
    $lines[] = '    CustomLog /home/fpp/media/logs/apache2-externalfpp-access.log combined';
    $lines[] = '';
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
    $lines[] = '    # Serve the login page directly from the plugin instead of proxying it.';
    $lines[] = '    # The "!" marks the URL as not-proxied so the Alias below can serve it.';
    $lines[] = '    ProxyPass ' . LOGIN_PAGE_URL . ' !';
    $lines[] = '    # Keep the logout handler local too (ProxyPass would otherwise override';
    $lines[] = '    # its SetHandler and send /logout to the FPP backend).';
    $lines[] = '    ProxyPass /logout !';
    $lines[] = '    Alias ' . LOGIN_PAGE_URL . ' ' . $loginPageFile;
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
    $lines[] = '        AuthFormLoginSuccessLocation /';
    $lines[] = '        AuthFormLogoutLocation ' . LOGIN_PAGE_URL;
    $lines[] = '        Require valid-user';
    $lines[] = '    </LocationMatch>';
    $lines[] = '</VirtualHost>';
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

function efppApply() {
    $errors = array();
    $messages = array();

    $s = efppLoadSettings();
    $enabled = !empty($s['enabled']);
    $port = (int)$s['port'];
    $backendPort = (int)$s['backend_port'];
    $username = trim($s['username']);
    $password = $s['password'];

    efppEnsureConfigDir();
    efppEnsureLoginPage();

    // Always make sure the required Apache modules are present (idempotent).
    efppRun('a2enmod proxy proxy_http headers auth_basic authn_file auth_form session session_cookie alias >/dev/null 2>&1');

    if ($port < 1 || $port > 65535) {
        $errors[] = 'Invalid listen port "' . htmlspecialchars((string)$s['port'], ENT_QUOTES) . '". Choose a value between 1 and 65535.';
    }
    if ($backendPort < 1 || $backendPort > 65535) {
        $errors[] = 'Invalid backend (FPP web) port "' . htmlspecialchars((string)$s['backend_port'], ENT_QUOTES) . '". Choose a value between 1 and 65535.';
    }
    if ($port === $backendPort && $port !== 0) {
        $errors[] = 'The listen port and the backend (FPP web) port must be different.';
    }
    if ($enabled && ($username === '' || $password === '')) {
        $errors[] = 'The plugin is enabled but no username/password are configured. Set both before enabling.';
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

    list($ok, $msg) = efppWriteHtpasswd($username, $password);
    $messages[] = $msg;
    if (!$ok) {
        efppLog('ERROR: ' . $msg);
        return array('success' => false, 'errors' => array($msg), 'messages' => $messages);
    }

    if ($enabled) {
        $conf = efppBuildApacheConf($port, $backendPort, HTPASSWD_FILE, LOGIN_PAGE_FILE);
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

        list($ok2, $err2) = efppReloadApache();
        if (!$ok2) {
            $msg = $err2 . ' The external port may not be active until Apache is reloaded.';
            efppLog('ERROR: ' . $msg);
            return array('success' => false, 'errors' => array($msg), 'messages' => $messages);
        }
        $messages[] = 'External web access enabled on port ' . $port . '.';
        efppLog('Enabled external web access on port ' . $port . ' (backend ' . $backendPort . ')');
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
