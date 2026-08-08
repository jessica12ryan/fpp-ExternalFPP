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
define('GROUPS_FILE', PLUGIN_DIR . '/config/plugin.fpp-ExternalFPP.groups');
define('ADMIN_GROUP_NAME', 'efpp-admin');
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
define('ACCESS_DENIED_FILE', LOGIN_PAGE_DIR . '/access-denied.html');
define('ACCESS_DENIED_TEMPLATE', PLUGIN_DIR . '/templates/access-denied.html');
define('ACCESS_DENIED_URL', '/access-denied.html');
define('FPP_WWW_DIR', '/opt/fpp/www');
define('FPP_CONFIG_FILE', FPP_WWW_DIR . '/config.php');
define('FPP_UI_LEVEL_ANCHOR', '$uiLevel = $settings[\'uiLevel\'];');
define('FPP_UI_LEVEL_START', '// ExternalFPP plugin: force the Basic UI for non-Admin external proxy users. (start)');
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
// The box's own shellinaboxd (FPP Help > SSH Shell). FPP's UI links to it as
// /proxy/<ip>:<port> (using SERVER_ADDR, which is 127.0.0.1 when reached
// through the plugin's local reverse proxy). The plugin exposes that path
// itself so the shell keeps working even when no matching entry exists in
// FPP's own proxy list (proxies.php).
define('SHELL_PROXY_PATH', '/proxy/127.0.0.1:4200');
define('SHELL_PROXY_TARGET', 'http://127.0.0.1:4200/');
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
            $role = (string)($u['role'] ?? 'admin');
            $users[] = array(
                'username' => trim((string)$u['username']),
                'password' => (string)($u['password'] ?? ''),
                'role' => ($role === 'user') ? 'user' : 'admin'
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

function efppDefaultAccessDeniedPage() {
    if (file_exists(ACCESS_DENIED_TEMPLATE)) {
        $c = @file_get_contents(ACCESS_DENIED_TEMPLATE);
        if (is_string($c) && $c !== '') {
            return $c;
        }
    }
    // Fallback if the bundled template is missing.
    return '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Access Denied</title></head>'
        . '<body style="font-family:sans-serif;background:#1c1e21;color:#eee;text-align:center;padding:60px;">'
        . '<h1>Access Denied</h1>'
        . '<p>Your account does not have permission to open this page.</p>'
        . '<a href="/" style="display:inline-block;margin:8px;padding:10px 18px;background:#2563eb;color:#fff;text-decoration:none;border-radius:6px;">Home</a>'
        . '<button onclick="history.back();" style="display:inline-block;margin:8px;padding:10px 18px;background:#374151;color:#fff;border:none;border-radius:6px;">Go Back</button>'
        . '</body></html>';
}

function efppEnsureAccessDeniedPage() {
    efppEnsureConfigDir();
    if (!is_dir(LOGIN_PAGE_DIR)) {
        @mkdir(LOGIN_PAGE_DIR, 0775, true);
    }
    if (!file_exists(ACCESS_DENIED_FILE)) {
        if (@file_put_contents(ACCESS_DENIED_FILE, efppDefaultAccessDeniedPage()) === false) {
            efppLog('WARNING: could not create the access denied page at ' . ACCESS_DENIED_FILE);
            return false;
        }
        efppRun('chown fpp:fpp ' . escapeshellarg(ACCESS_DENIED_FILE) . ' 2>/dev/null');
        efppRun('chmod 644 ' . escapeshellarg(ACCESS_DENIED_FILE) . ' 2>/dev/null');
    }
    return true;
}

function efppEnsurePages() {
    return efppEnsureLoginPage() && efppEnsureChangePasswordPage() && efppEnsureAccessDeniedPage();
}

/**
 * Patches FPP's www/config.php so that requests arriving through the external
 * proxy (detected by the X-FPP-Ext-User header set in the Apache vhost) are
 * forced down to the Basic UI level unless the account is in the efpp-admin
 * group. The patch block's membership check reads the group file on every
 * request, so role changes apply immediately. The block is inserted just
 * before "$uiLevel = $settings['uiLevel'];" (after the temporary override
 * cookie handling) and is flagged with a marker so the patch is idempotent and
 * FPP version updates that rewrite config.php re-patch cleanly on next apply.
 */
function efppPatchFppUiLevel() {
    if (!file_exists(FPP_CONFIG_FILE)) {
        return array(false, 'FPP config not found (' . FPP_CONFIG_FILE . '); skipping UI level patch.');
    }
    $contents = @file_get_contents(FPP_CONFIG_FILE);
    if ($contents === false) {
        return array(false, 'Could not read ' . FPP_CONFIG_FILE . ' for UI level patching.');
    }
    if (strpos($contents, FPP_UI_LEVEL_START) !== false) {
        return array(true, 'FPP UI level patch already applied.');
    }
    if (strpos($contents, FPP_UI_LEVEL_ANCHOR) === false) {
        return array(false, 'Could not find the uiLevel anchor in ' . FPP_CONFIG_FILE . '; skipping UI level patch.');
    }

    $groupsPath = GROUPS_FILE;
    $block = "\n" . FPP_UI_LEVEL_START . "\n"
        . 'if (!empty($_SERVER[\'HTTP_X_FPP_EXT_USER\'] ?? \'\')) {' . "\n"
        . '    $__efppGroupsFile = ' . var_export($groupsPath, true) . ';' . "\n"
        . '    $__efppIsAdmin = false;' . "\n"
        . '    if (is_readable($__efppGroupsFile)) {' . "\n"
        . '        foreach (file($__efppGroupsFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $__efppLine) {' . "\n"
        . '            if (stripos($__efppLine, \'efpp-admin:\') === 0) {' . "\n"
        . '                list(, $__efppMembers) = explode(\':\', $__efppLine, 2);' . "\n"
        . '                $__efppAdmins = preg_split(\'/\\s+/\', trim($__efppMembers), -1, PREG_SPLIT_NO_EMPTY);' . "\n"
        . '                $__efppIsAdmin = in_array($_SERVER[\'HTTP_X_FPP_EXT_USER\'], $__efppAdmins, true);' . "\n"
        . '                break;' . "\n"
        . '            }' . "\n"
        . '        }' . "\n"
        . '    }' . "\n"
        . '    if (!$__efppIsAdmin) {' . "\n"
        . "        \$settings['uiLevel'] = 0;" . "\n"
        . '    }' . "\n"
        . '    unset($__efppGroupsFile, $__efppIsAdmin, $__efppLine, $__efppMembers, $__efppAdmins);' . "\n"
        . '}' . "\n"
        . '// ExternalFPP plugin: force the Basic UI for non-Admin external proxy users. (end)' . "\n";

    $newContents = str_replace(FPP_UI_LEVEL_ANCHOR, $block . FPP_UI_LEVEL_ANCHOR, $contents);
    if ($newContents === $contents) {
        return array(false, 'UI level patch produced no change to ' . FPP_CONFIG_FILE . '.');
    }

    // Back up the pristine FPP file once so the patch is easy to remove by hand.
    $backup = FPP_CONFIG_FILE . '.efpp-bak';
    if (!file_exists($backup)) {
        @copy(FPP_CONFIG_FILE, $backup);
    }

    if (efppIsRoot()) {
        $ok = @file_put_contents(FPP_CONFIG_FILE, $newContents);
    } else {
        // Writing as non-root: sudo-tee it through a root shell.
        $tmp = sys_get_temp_dir() . '/fpp-ui-level.patch.php';
        if (@file_put_contents($tmp, $newContents)) {
            $r = efppRun('sudo cp ' . escapeshellarg($tmp) . ' ' . escapeshellarg(FPP_CONFIG_FILE));
            @unlink($tmp);
            $ok = ($r['code'] === 0);
        } else {
            $ok = false;
        }
    }
    if (!$ok) {
        return array(false, 'Could not write the UI level patch to ' . FPP_CONFIG_FILE . '.');
    }
    efppLog('Patched FPP config.php for external Basic UI (non-Admin) enforcement');
    return array(true, 'Patched FPP config.php to force the Basic UI for external non-Admin users.');
}

/**
 * Writes every user to the Apache password file. Uses the stored bcrypt hash
 * directly (Apache accepts $2y$ natively), so no plaintext is ever written.
 * Returns array(true, message) on success, array(false, error) on failure.
 */
function efppWriteGroupFile($users) {
    $users = efppUsersFromSettings(array('users' => $users));

    // mod_authz_groupfile reads the group list per request, so Apache picks up
    // role changes immediately without needing a reload or a conf regen.
    $content = ADMIN_GROUP_NAME . ':';
    foreach ($users as $u) {
        if ($u['role'] === 'admin') {
            $content .= ' ' . $u['username'];
        }
    }
    $content .= "\n";

    if (@file_put_contents(GROUPS_FILE, $content) === false) {
        return false;
    }
    efppRun('chown fpp:fpp ' . escapeshellarg(GROUPS_FILE) . ' 2>/dev/null');
    efppRun('chmod 644 ' . escapeshellarg(GROUPS_FILE) . ' 2>/dev/null');
    return true;
}

    function efppWriteHtpasswd($users) {
    $users = efppUsersFromSettings(array('users' => $users));

    efppWriteGroupFile($users);

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
function efppBuildVhostBody($backendPort, $htpasswdFile, $loginPageFile, $changePwFile, $https, $groupsFile, $accessDeniedFile) {
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
    $lines[] = '    # Tell the backend who the visitor connected to and over which scheme,';
    $lines[] = '    # the way FPP\'s own reverse-proxying (a "proxy subnet" setup) expects,';
    $lines[] = '    # so links FPP generates for external users stay on the right host';
    $lines[] = '    # and scheme (e.g. the Help > SSH Shell link). Disable mod_proxy\'s';
    $lines[] = '    # automatic forwarding headers so only these explicit, unspoofable';
    $lines[] = '    # values (reset from the real request) are sent upstream.';
    $lines[] = '    ProxyAddHeaders Off';
    $lines[] = '    RequestHeader unset X-Forwarded-Host';
    $lines[] = '    RequestHeader set X-Forwarded-Host "expr=%{req:host}"';
    $lines[] = '    RequestHeader unset X-Forwarded-Proto';
    $lines[] = '    RequestHeader set X-Forwarded-Proto "' . ($https ? 'https' : 'http') . '"';
    $lines[] = '';
    $lines[] = '    # Serve the login page directly from the plugin instead of proxying it.';
    $lines[] = '    # The "!" marks the URL as not-proxied so the Alias below can serve it.';
    $lines[] = '    ProxyPass ' . LOGIN_PAGE_URL . ' !';
    $lines[] = '    ProxyPass ' . CHANGE_PW_URL . ' !';
    $lines[] = '    ProxyPass ' . ACCESS_DENIED_URL . ' !';
    $lines[] = '    # Keep the logout handler local too (ProxyPass would otherwise override';
    $lines[] = '    # its SetHandler and send /logout to the FPP backend).';
    $lines[] = '    ProxyPass /logout !';
    $lines[] = '    Alias ' . LOGIN_PAGE_URL . ' ' . $loginPageFile;
    $lines[] = '    Alias ' . CHANGE_PW_URL . ' ' . $changePwFile;
    $lines[] = '    Alias ' . ACCESS_DENIED_URL . ' ' . $accessDeniedFile;
    $lines[] = '    <Directory ' . $loginPageDir . '>';
    $lines[] = '        Require all granted';
    $lines[] = '    </Directory>';
    $lines[] = '    # The login page and the access-denied page must be reachable without a';
    $lines[] = '    # session or logging in loops (the access-denied page is the ErrorDocument';
    $lines[] = '    # target for accounts that ARE logged in but lack Admin rights).';
    $lines[] = '    <Location ' . LOGIN_PAGE_URL . '>';
    $lines[] = '        AuthType None';
    $lines[] = '        Require all granted';
    $lines[] = '    </Location>';
    $lines[] = '    <Location ' . ACCESS_DENIED_URL . '>';
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
    $lines[] = '    ProxyPass ' . SHELL_PROXY_PATH . ' ' . SHELL_PROXY_TARGET;
    $lines[] = '    ProxyPassReverse ' . SHELL_PROXY_PATH . ' ' . SHELL_PROXY_TARGET;
    $lines[] = '';
    $lines[] = '    ProxyPass / http://127.0.0.1:' . $backendPort . '/';
    $lines[] = '    ProxyPassReverse / http://127.0.0.1:' . $backendPort . '/';
    $lines[] = '    ProxyPassReverse / https://127.0.0.1:' . $backendPort . '/';
    $lines[] = '';
    $lines[] = '    <Proxy *>';
    $lines[] = '        Require all granted';
    $lines[] = '    </Proxy>';
    $lines[] = '';
    $lines[] = '    # Everything except the local pages (login, change password, access';
    $lines[] = '    # denied, settings, network config, plugins, file manager, backup) is';
    $lines[] = '    # protected by a form login. Requests without a valid session are';
    $lines[] = '    # redirected to the login page; the login form POSTs back here and on';
    $lines[] = '    # success Apache sets the session cookie. The admin-only pages get their';
    $lines[] = '    # own rule below.';
    $lines[] = '    <LocationMatch "^/(?!(login\\.html|change-password\\.html|access-denied\\.html|settings\\.php|plugin\\.php|plugins\\.php|filemanager\\.php|backup\\.php|networkconfig\\.php))">';
    $lines[] = '        AuthType Form';
    $lines[] = '        AuthName "' . REALM . '"';
    $lines[] = '        AuthFormProvider file';
    $lines[] = '        AuthUserFile ' . $htpasswdFile;
    $lines[] = '        AuthFormLoginRequiredLocation ' . LOGIN_PAGE_URL;
    $lines[] = '        AuthFormLoginSuccessLocation ' . LOGIN_SUCCESS_URL;
    $lines[] = '        AuthFormLogoutLocation ' . LOGIN_PAGE_URL;
    $lines[] = '        Require valid-user';
    $lines[] = '        # FPP reads this header (see efppPatchFppUiLevel) to strip non-Admin';
    $lines[] = '        # accounts down to the Basic UI. Set after auth so %{REMOTE_USER} is';
    $lines[] = '        # populated, and unset first so clients cannot spoof the header.';
    $lines[] = '        RequestHeader unset X-FPP-Ext-User';
    $lines[] = '        RequestHeader set X-FPP-Ext-User "%{REMOTE_USER}s"';
    $lines[] = '    </LocationMatch>';
    $lines[] = '';
    $lines[] = '    # settings.php, networkconfig.php, plugin.php, plugins.php, filemanager.php';
    $lines[] = '    # and backup.php are admin-only. This more specific section combines with';
    $lines[] = '    # the form auth above (both conditions must pass): visitors without a';
    $lines[] = '    # session are still sent to the login page, while logged-in non-Admin';
    $lines[] = '    # accounts are shown the access-denied page via the ErrorDocument below.';
    $lines[] = '    # Membership is read from the group file on every request, so role';
    $lines[] = '    # changes apply immediately without reloading Apache.';
    $lines[] = '    <LocationMatch "^/(settings\\.php|networkconfig\\.php|plugin\\.php|plugins\\.php|filemanager\\.php|backup\\.php)$">';
    $lines[] = '        AuthType Form';
    $lines[] = '        AuthName "' . REALM . '"';
    $lines[] = '        AuthFormProvider file';
    $lines[] = '        AuthUserFile ' . $htpasswdFile;
    $lines[] = '        AuthGroupFile ' . $groupsFile;
    $lines[] = '        AuthFormLoginRequiredLocation ' . LOGIN_PAGE_URL;
    $lines[] = '        AuthFormLoginSuccessLocation ' . LOGIN_SUCCESS_URL;
    $lines[] = '        AuthFormLogoutLocation ' . LOGIN_PAGE_URL;
    $lines[] = '        <RequireAll>';
    $lines[] = '            Require valid-user';
    $lines[] = '            Require group ' . ADMIN_GROUP_NAME;
    $lines[] = '        </RequireAll>';
    $lines[] = '        RequestHeader unset X-FPP-Ext-User';
    $lines[] = '        RequestHeader set X-FPP-Ext-User "%{REMOTE_USER}s"';
    $lines[] = '        ErrorDocument 401 ' . ACCESS_DENIED_URL;
    $lines[] = '        ErrorDocument 403 ' . ACCESS_DENIED_URL;
    $lines[] = '    </LocationMatch>';
    return $lines;
}

function efppBuildApacheConf($port, $httpsPort, $backendPort, $htpasswdFile, $loginPageFile, $changePwFile, $accessDeniedFile, $enableHttp, $enableHttps) {
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
        $lines = array_merge($lines, efppBuildVhostBody($backendPort, $htpasswdFile, $loginPageFile, $changePwFile, false, GROUPS_FILE, ACCESS_DENIED_FILE));
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
        $lines = array_merge($lines, efppBuildVhostBody($backendPort, $htpasswdFile, $loginPageFile, $changePwFile, true, GROUPS_FILE, ACCESS_DENIED_FILE));
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
    $required = array('proxy', 'proxy_http', 'headers', 'authn_file', 'authz_groupfile', 'auth_form', 'session', 'session_cookie', 'request', 'alias', 'ssl', 'rewrite');
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
    efppPatchFppUiLevel();

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
        $conf = efppBuildApacheConf($port, $httpsPort, $backendPort, HTPASSWD_FILE, LOGIN_PAGE_FILE, CHANGE_PW_FILE, ACCESS_DENIED_FILE, $enableHttp, $enableHttps);
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
