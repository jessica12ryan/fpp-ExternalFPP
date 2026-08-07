<?php
/**
 * #############################################################
 * ## External FPP Web Access (fpp-ExternalFPP)               ##
 * ## Author: jessica12ryan                                ##
 * ## URL: https://github.com/jessica12ryan/fpp-ExternalFPP##
 * #############################################################
 * ## api.php                                                 ##
 * ## Backend endpoints for the plugin UI.                    ##
 * ## Loaded by FPP's www/api/index.php which calls           ##
 * ## getEndpointsfppExternalFPP() to register routes.        ##
 * #############################################################
 */

define('EFPP_PLUGIN_DIR', __DIR__);
define('EFPP_SETTINGS_FILE', EFPP_PLUGIN_DIR . '/config/settings.json');
define('EFPP_HTPASSWD_FILE', EFPP_PLUGIN_DIR . '/config/plugin.fpp-ExternalFPP.htpasswd');
define('EFPP_APPLY_SCRIPT', EFPP_PLUGIN_DIR . '/scripts/apply.php');
define('EFPP_LOGIN_PAGE_DIR', EFPP_PLUGIN_DIR . '/www');
define('EFPP_LOGIN_PAGE_FILE', EFPP_LOGIN_PAGE_DIR . '/login.html');
define('EFPP_LOGIN_PAGE_TEMPLATE', EFPP_PLUGIN_DIR . '/templates/login.html');
define('EFPP_LOGIN_PAGE_URL', '/login.html');
define('EFPP_CHANGE_PW_FILE', EFPP_LOGIN_PAGE_DIR . '/change-password.html');
define('EFPP_CHANGE_PW_TEMPLATE', EFPP_PLUGIN_DIR . '/templates/change-password.html');
define('EFPP_CHANGE_PW_URL', '/change-password.html');
define('EFPP_APACHE_CONF_NAME', 'fpp-externalfpp');
define('EFPP_APACHE_CONF_ENABLED', '/etc/apache2/conf-enabled/fpp-externalfpp.conf');
define('EFPP_LOG_DIR', getenv('LOGDIR') ?: '/home/fpp/media/logs');
define('EFPP_LOG_FILE', EFPP_LOG_DIR . '/plugin-fpp-ExternalFPP.log');
// Must match the Apache vhost (scripts/apply.php). The form-login session cookie
// is the only identity that is guaranteed reachable from the backend PHP, since
// the X-Remote-User header forwarded by the external vhost can arrive as the
// literal string "(null)" when mod_headers runs before mod_auth_form populates
// REMOTE_USER on proxied requests.
define('EFPP_SESSION_COOKIE', 'fppefpp');
define('EFPP_SESSION_REALM', 'FPP External Web Access');

function efppIsRoot() {
    if (function_exists('posix_geteuid')) {
        return posix_geteuid() === 0;
    }
    return getenv('USER') === 'root';
}

function efppLog($msg) {
    @file_put_contents(EFPP_LOG_FILE, date('Y-m-d H:i:s') . ' fpp-ExternalFPP api: ' . $msg . "\n", FILE_APPEND | LOCK_EX);
}

function efppLoadSettings() {
    $defaults = array(
        'port' => 8080,
        'backend_port' => 80,
        'https_port' => 8443,
        'enable_http' => 0,
        'enable_https' => 0,
        'users' => array()
    );
    if (!file_exists(EFPP_SETTINGS_FILE)) {
        return $defaults;
    }
    $s = json_decode(file_get_contents(EFPP_SETTINGS_FILE), true);
    if (!is_array($s)) {
        return $defaults;
    }
    $s = array_merge($defaults, $s);
    // 'enabled' no longer exists as a stored setting: external access is
    // derived from whether any port is enabled. Drop it (and any stale value
    // left by older versions) so it never gets written back to disk.
    unset($s['enabled']);
    // Migrate the old single username/password layout to the users list.
    if (empty($s['users']) && !empty($s['username']) && !empty($s['password'])) {
        $s['users'] = array(array('username' => $s['username'], 'password' => $s['password']));
    }
    unset($s['username'], $s['password']);
    return $s;
}

function efppSaveSettingsFile($s) {
    if (!is_dir(EFPP_PLUGIN_DIR . '/config')) {
        @mkdir(EFPP_PLUGIN_DIR . '/config', 0775, true);
    }
    return @file_put_contents(EFPP_SETTINGS_FILE, json_encode($s, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
}

function efppUsersFromSettings($s) {
    $users = array();
    foreach (($s['users'] ?? array()) as $u) {
        if (is_array($u) && isset($u['username']) && trim((string)$u['username']) !== '') {
            // A named role defaults to admin so accounts created before roles
            // existed remain fully privileged. Only the literal "user" is
            // treated as the limited role.
            $role = (string)($u['role'] ?? 'admin');
            $users[] = array(
                'username' => trim((string)$u['username']),
                'password' => (string)($u['password'] ?? ''),
                'must_change' => !empty($u['must_change']) ? 1 : 0,
                'role' => ($role === 'user') ? 'user' : 'admin'
            );
        }
    }
    return $users;
}

function efppUsersList() {
    return efppUsersFromSettings(efppLoadSettings());
}

function efppPasswordIsHash($password) {
    return is_string($password) && preg_match('/^\$2[abxy]\$/', $password) === 1;
}

function efppHashPassword($plain) {
    return password_hash((string)$plain, PASSWORD_BCRYPT);
}

/**
 * Writes every user to the Apache password file. Runs as the web (fpp) user,
 * which owns the plugin config directory, so no sudo is required here.
 * Uses the stored bcrypt hash directly, since Apache accepts $2y$ natively.
 */
function efppWriteHtpasswd($users) {
    $users = efppUsersFromSettings(array('users' => $users));

    if (empty($users)) {
        if (file_exists(EFPP_HTPASSWD_FILE)) {
            @unlink(EFPP_HTPASSWD_FILE);
        }
        return array(true, 'Removed password file (no users configured)');
    }

    $content = '';
    foreach ($users as $u) {
        $hash = efppPasswordIsHash($u['password']) ? $u['password'] : efppHashPassword($u['password']);
        $content .= $u['username'] . ':' . $hash . "\n";
    }
    if (@file_put_contents(EFPP_HTPASSWD_FILE, $content) === false) {
        return array(false, 'Could not write the password file to ' . EFPP_HTPASSWD_FILE);
    }
    return array(true, 'Password file written for ' . count($users) . ' user(s) using bcrypt');
}

function efppValidateLoginUser($username, $password, $confirm, $checkUnique, $existingUsers) {
    $errors = array();
    $username = trim((string)$username);

    if ($username === '') {
        $errors[] = 'Username is required.';
    } elseif (strpos($username, ':') !== false) {
        $errors[] = 'Username cannot contain a colon (:).';
    }

    $password = (string)$password;
    if (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters long.';
    }
    if ($password !== (string)$confirm) {
        $errors[] = 'Password and confirmation do not match.';
    }

    if ($checkUnique && $username !== '') {
        foreach ($existingUsers as $u) {
            if (strcasecmp($u['username'], $username) === 0) {
                $errors[] = 'A user with that username already exists.';
                break;
            }
        }
    }

    return $errors;
}

function efppRunApply() {
    $phpBin = file_exists('/usr/bin/php') ? '/usr/bin/php' : 'php';
    $cmd = (efppIsRoot() ? '' : 'sudo ') . $phpBin . ' ' . escapeshellarg(EFPP_APPLY_SCRIPT) . ' 2>&1';
    $output = array();
    $code = 0;
    exec($cmd, $output, $code);
    $text = trim(implode("\n", $output));
    $messages = array();
    $errors = array();
    foreach (preg_split('/\R/', $text) as $line) {
        $line = trim($line);
        if ($line === '') continue;
        if (strpos($line, '  ') === 0) {
            $messages[] = trim($line);
        } else {
            $messages[] = $line;
        }
    }
    if ($code !== 0) {
        $errors = $messages;
        $messages = array();
    }
    return array('success' => ($code === 0), 'messages' => $messages, 'errors' => $errors);
}

function efppTcpOpen($host, $port) {
    $port = (int)$port;
    if ($port < 1 || $port > 65535) return false;
    $fp = @fsockopen($host, $port, $errno, $errstr, 1);
    if ($fp) {
        fclose($fp);
        return true;
    }
    return false;
}

function efppHttpRequest($method, $host, $port, $path, $extraHeaders = array(), $body = '', $https = false) {
    $port = (int)$port;
    if ($https) {
        $fp = @stream_socket_client('ssl://' . $host . ':' . $port, $errno, $errstr, 3,
            STREAM_CLIENT_CONNECT,
            stream_context_create(array('ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ))));
    } else {
        $fp = @fsockopen($host, $port, $errno, $errstr, 3);
    }
    if (!$fp) {
        return array('code' => 0, 'headers' => array(), 'body' => '');
    }
    $req = $method . ' ' . $path . " HTTP/1.1\r\n";
    $req .= 'Host: ' . $host . "\r\n";
    foreach ($extraHeaders as $h) {
        $req .= $h . "\r\n";
    }
    if ($body !== '') {
        $req .= 'Content-Type: application/x-www-form-urlencoded' . "\r\n";
        $req .= 'Content-Length: ' . strlen($body) . "\r\n";
    }
    $req .= "Connection: close\r\n\r\n";
    if ($body !== '') {
        $req .= $body;
    }
    fwrite($fp, $req);
    $resp = '';
    while (!feof($fp)) {
        $chunk = fread($fp, 8192);
        if ($chunk === false) break;
        $resp .= $chunk;
        if (strlen($resp) > 1048576) break;
    }
    fclose($fp);

    $code = 0;
    if (preg_match('#^HTTP/\S+\s+(\d{3})#', $resp, $m)) {
        $code = (int)$m[1];
    }
    $headers = array();
    $bodyOut = '';
    $parts = explode("\r\n\r\n", $resp, 2);
    $headerBlock = $parts[0];
    if (count($parts) > 1) {
        $bodyOut = $parts[1];
    }
    foreach (preg_split('/\r?\n/', $headerBlock) as $line) {
        if (strpos($line, ':') !== false) {
            list($k, $v) = explode(':', $line, 2);
            $headers[trim($k)] = trim($v);
        }
    }
    return array('code' => $code, 'headers' => $headers, 'body' => $bodyOut);
}

/**
 * Returns true if the given username has a bcrypt-hashed entry in the Apache
 * password file (the config-level equivalent of the old live-login test).
 */
function efppUserInHtpasswd($username) {
    if (!file_exists(EFPP_HTPASSWD_FILE)) {
        return false;
    }
    foreach (file(EFPP_HTPASSWD_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (strpos($line, ':') === false) {
            continue;
        }
        list($u, $h) = explode(':', $line, 2);
        if (strcasecmp(trim($u), $username) === 0 && efppPasswordIsHash(trim($h))) {
            return true;
        }
    }
    return false;
}

function efppFppUiPasswordEnabled() {
    $conf = '/home/fpp/media/config/ui-password-config.conf';
    if (!file_exists($conf)) {
        return false;
    }
    $content = @file_get_contents($conf);
    return is_string($content) && preg_match('/\bRequire\s+valid-user\b/i', $content);
}

function efppDefaultLoginPage() {
    if (file_exists(EFPP_LOGIN_PAGE_TEMPLATE)) {
        $c = @file_get_contents(EFPP_LOGIN_PAGE_TEMPLATE);
        if (is_string($c) && $c !== '') {
            return $c;
        }
    }
    return '<!DOCTYPE html><html><body style="font-family:sans-serif;background:#1c1e21;color:#eee;text-align:center;padding:60px;">'
        . '<h1>External FPP Web Access</h1>'
        . '<form method="post" action="/">'
        . '<input type="text" name="httpd_username" placeholder="Username"><br><br>'
        . '<input type="password" name="httpd_password" placeholder="Password"><br><br>'
        . '<button type="submit">Sign In</button></form></body></html>';
}

function efppLoginPageContent() {
    if (file_exists(EFPP_LOGIN_PAGE_FILE)) {
        $c = @file_get_contents(EFPP_LOGIN_PAGE_FILE);
        if (is_string($c)) {
            return $c;
        }
    }
    return efppDefaultLoginPage();
}

function efppValidateLoginPage($content) {
    if ($content === '') {
        return array(array('The login page cannot be empty.'), array());
    }
    $warnings = array();
    if (!preg_match('/<form\b[^>]*method\s*=\s*["\']?post/i', $content)) {
        $warnings[] = 'The <form> element is missing method="post". Without it, logins will not work.';
    }
    if (!preg_match('/<form\b[^>]*action\s*=/i', $content)) {
        $warnings[] = 'The <form> element is missing an action. It must post to a protected URL, e.g. action="/".';
    }
    if (!preg_match('/name\s*=\s*["\']httpd_username["\']/i', $content)) {
        $warnings[] = 'The form is missing the required <input name="httpd_username"> field.';
    }
    if (!preg_match('/name\s*=\s*["\']httpd_password["\']/i', $content)) {
        $warnings[] = 'The form is missing the required <input name="httpd_password"> field.';
    }
    return array(array(), $warnings);
}

function efppLoginPageEndpoint() {
    return json(array('success' => true, 'content' => efppLoginPageContent()));
}

function efppSaveLoginPageEndpoint() {
    if (efppSessionIsUser()) {
        return efppAdminOnlyError();
    }
    $data = $_POST;
    if (empty($data)) {
        $raw = json_decode(file_get_contents('php://input'), true);
        if (is_array($raw)) $data = $raw;
    }
    $content = $data['content'] ?? null;
    if (!is_string($content)) {
        return json(array('success' => false, 'messages' => array(), 'warnings' => array(), 'errors' => array('No page content received.')));
    }

    list($errors, $warnings) = efppValidateLoginPage($content);

    if (!is_dir(EFPP_LOGIN_PAGE_DIR)) {
        @mkdir(EFPP_LOGIN_PAGE_DIR, 0775, true);
    }
    if (@file_put_contents(EFPP_LOGIN_PAGE_FILE, $content) === false) {
        $errors[] = 'Could not write the login page to ' . EFPP_LOGIN_PAGE_FILE . '. Check file permissions.';
    }

    if (!empty($errors)) {
        return json(array('success' => false, 'messages' => array(), 'warnings' => $warnings, 'errors' => $errors));
    }
    efppLog('Login page saved');
    return json(array('success' => true, 'messages' => array('Login page saved.'), 'warnings' => $warnings, 'errors' => array()));
}

function efppResetLoginPageEndpoint() {
    if (efppSessionIsUser()) {
        return efppAdminOnlyError();
    }
    return efppResetPage(EFPP_LOGIN_PAGE_FILE, 'efppDefaultLoginPage', 'login page');
}

function efppDefaultChangePasswordPage() {
    if (file_exists(EFPP_CHANGE_PW_TEMPLATE)) {
        $c = @file_get_contents(EFPP_CHANGE_PW_TEMPLATE);
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

function efppChangePasswordPageContent() {
    if (file_exists(EFPP_CHANGE_PW_FILE)) {
        $c = @file_get_contents(EFPP_CHANGE_PW_FILE);
        if (is_string($c)) {
            return $c;
        }
    }
    return efppDefaultChangePasswordPage();
}

function efppValidateChangePasswordPage($content) {
    if ($content === '') {
        return array(array('The change password page cannot be empty.'), array());
    }
    $warnings = array();
    if (!preg_match('/password[\'\"]?\s*=/i', $content)) {
        $warnings[] = 'The page should submit a JSON payload with a password field (see the required code below).';
    }
    if (!preg_match('/"password_confirm"/', $content) && !preg_match("/'password_confirm'/", $content)) {
        $warnings[] = 'The page should submit a password_confirm field alongside the password.';
    }
    if (!preg_match('#change-my-password#', $content)) {
        $warnings[] = 'The page must post to the plugin API endpoint /api/plugin/fpp-ExternalFPP/change-my-password.';
    }
    return array(array(), $warnings);
}

function efppGetChangePasswordPageEndpoint() {
    return json(array('success' => true, 'content' => efppChangePasswordPageContent()));
}

function efppSaveChangePasswordPageEndpoint() {
    if (efppSessionIsUser()) {
        return efppAdminOnlyError();
    }
    return efppSavePage(EFPP_CHANGE_PW_FILE, 'efppValidateChangePasswordPage', 'change password page');
}

function efppResetChangePasswordPageEndpoint() {
    if (efppSessionIsUser()) {
        return efppAdminOnlyError();
    }
    return efppResetPage(EFPP_CHANGE_PW_FILE, 'efppDefaultChangePasswordPage', 'change password page');
}

function efppSavePage($file, $validatorFn, $label) {
    $data = $_POST;
    if (empty($data)) {
        $raw = json_decode(file_get_contents('php://input'), true);
        if (is_array($raw)) $data = $raw;
    }
    $content = $data['content'] ?? null;
    if (!is_string($content)) {
        return json(array('success' => false, 'messages' => array(), 'warnings' => array(), 'errors' => array('No page content received.')));
    }

    list($errors, $warnings) = call_user_func($validatorFn, $content);

    if (!is_dir(EFPP_LOGIN_PAGE_DIR)) {
        @mkdir(EFPP_LOGIN_PAGE_DIR, 0775, true);
    }
    if (@file_put_contents($file, $content) === false) {
        $errors[] = 'Could not write the ' . $label . ' to ' . $file . '. Check file permissions.';
    }

    if (!empty($errors)) {
        return json(array('success' => false, 'messages' => array(), 'warnings' => $warnings, 'errors' => $errors));
    }
    efppLog(ucfirst($label) . ' saved');
    return json(array('success' => true, 'messages' => array(ucfirst($label) . ' saved.'), 'warnings' => $warnings, 'errors' => array()));
}

function efppResetPage($file, $defaultFn, $label) {
    if (!is_dir(EFPP_LOGIN_PAGE_DIR)) {
        @mkdir(EFPP_LOGIN_PAGE_DIR, 0775, true);
    }
    if (@file_put_contents($file, call_user_func($defaultFn)) === false) {
        return json(array('success' => false, 'messages' => array(), 'warnings' => array(), 'errors' => array('Could not write the ' . $label . '. Check file permissions.')));
    }
    efppLog(ucfirst($label) . ' reset to default');
    return json(array('success' => true, 'messages' => array(ucfirst($label) . ' reset to the default template.'), 'warnings' => array(), 'errors' => array()));
}

function efppStatusData() {
    $s = efppLoadSettings();
    $port = (int)$s['port'];
    $backendPort = (int)$s['backend_port'];
    $users = efppUsersFromSettings($s);
    $usernames = array();
    foreach ($users as $u) {
        $usernames[] = $u['username'];
    }
    return array(
        'configured' => file_exists(EFPP_SETTINGS_FILE) ? 1 : 0,
        'enabled' => ((!empty($s['enable_http'] ?? 0)) || (!empty($s['enable_https'] ?? 0))) ? 1 : 0,
        'port' => $port,
        'backend_port' => $backendPort,
        'https_port' => (int)($s['https_port'] ?? 8443),
        'enable_http' => !empty($s['enable_http'] ?? 0) ? 1 : 0,
        'enable_https' => !empty($s['enable_https'] ?? 0) ? 1 : 0,
        'users' => $usernames,
        'user_count' => count($usernames),
        'apache_conf_enabled' => file_exists(EFPP_APACHE_CONF_ENABLED) ? 1 : 0,
        'htpasswd_exists' => file_exists(EFPP_HTPASSWD_FILE) ? 1 : 0,
        'login_page' => file_exists(EFPP_LOGIN_PAGE_FILE) ? 1 : 0,
        'listening' => efppTcpOpen('127.0.0.1', $port) ? 1 : 0,
        'https_listening' => efppTcpOpen('127.0.0.1', (int)($s['https_port'] ?? 8443)) ? 1 : 0,
        'backend_reachable' => efppTcpOpen('127.0.0.1', $backendPort) ? 1 : 0,
        'ssl_module' => file_exists('/etc/apache2/mods-enabled/ssl.load') ? 1 : 0,
        'fpp_ui_password' => efppFppUiPasswordEnabled() ? 1 : 0,
        'hostname' => php_uname('n')
    );
}

function efppValidateData($data, $existing) {
    $errors = array();
    $clean = $existing;

    $port = (int)($data['port'] ?? $existing['port']);
    if ($port < 1 || $port > 65535) {
        $errors[] = 'HTTP port must be between 1 and 65535.';
    }
    $clean['port'] = $port;

    $backendPort = (int)($data['backend_port'] ?? $existing['backend_port']);
    if ($backendPort < 1 || $backendPort > 65535) {
        $errors[] = 'Backend (FPP web) port must be between 1 and 65535.';
    }
    $clean['backend_port'] = $backendPort;

    $httpsPort = (int)($data['https_port'] ?? $existing['https_port']);
    if ($httpsPort < 1 || $httpsPort > 65535) {
        $errors[] = 'HTTPS port must be between 1 and 65535.';
    }
    $clean['https_port'] = $httpsPort;

    $clean['enable_http'] = !empty($data['enable_http']) ? 1 : 0;
    $clean['enable_https'] = !empty($data['enable_https']) ? 1 : 0;

    // Both ports off is a valid state: it simply turns external access off.
    // Only the ports that are actually enabled need to be sane.
    if ($clean['enable_http'] && $clean['enable_https']) {
        if ($httpsPort === $port || $httpsPort === $backendPort) {
            $errors[] = 'The HTTP port and the HTTPS port must be different, and the HTTPS port must differ from the backend (FPP web) port.';
        }
    } elseif ($clean['enable_https']) {
        if ($httpsPort === $backendPort) {
            $errors[] = 'The HTTPS port must be different from the backend (FPP web) port.';
        }
    } elseif ($clean['enable_http']) {
        if ($port === $backendPort) {
            $errors[] = 'The HTTP port and the backend (FPP web) port must be different.';
        }
    }

    return array($clean, $errors);
}

function efppStatusEndpoint() {
    return json(efppStatusData());
}

function efppSaveEndpoint() {
    if (efppSessionIsUser()) {
        return efppAdminOnlyError();
    }
    $data = $_POST;
    if (empty($data)) {
        $raw = json_decode(file_get_contents('php://input'), true);
        if (is_array($raw)) $data = $raw;
    }
    if (empty($data)) {
        return json(array('success' => false, 'messages' => array(), 'errors' => array('No data received.')));
    }

    $existing = efppLoadSettings();
    list($clean, $errors) = efppValidateData($data, $existing);

    if (!empty($errors)) {
        return json(array('success' => false, 'messages' => array(), 'errors' => $errors));
    }

    if (efppSaveSettingsFile($clean) === false) {
        $errors[] = 'Could not write the settings file. Check file permissions.';
        return json(array('success' => false, 'messages' => array(), 'errors' => $errors));
    }

    $result = efppRunApply();
    $result['settings'] = efppStatusData();
    efppLog('Settings saved (port=' . $clean['port'] . ', http=' . $clean['enable_http'] . ', https=' . $clean['enable_https'] . ')');
    return json($result);
}

function efppRestartEndpoint() {
    if (efppSessionIsUser()) {
        return efppAdminOnlyError();
    }
    efppLog('Restart requested');
    $result = efppRunApply();
    $result['settings'] = efppStatusData();
    return json($result);
}

function efppTestEndpoint() {
    $s = efppLoadSettings();
    $port = (int)$s['port'];
    $backendPort = (int)$s['backend_port'];
    $httpsPort = (int)($s['https_port'] ?? 8443);
    $enableHttp = !empty($s['enable_http'] ?? 0);
    $enableHttps = !empty($s['enable_https'] ?? 0);
    $users = efppUsersFromSettings($s);
    $testUser = !empty($users) ? $users[0] : null;

    if (!($enableHttp || $enableHttps)) {
        return json(array('success' => false, 'errors' => array('External access is disabled (no HTTP or HTTPS port is enabled). Enable a port in the Config tab first.')));
    }

    $results = array();
    $results[] = array('check' => 'Backend FPP web server (port ' . $backendPort . ')', 'ok' => efppTcpOpen('127.0.0.1', $backendPort) ? 1 : 0);
    if ($enableHttp) {
        $results[] = array('check' => 'HTTP port ' . $port . ' listening', 'ok' => efppTcpOpen('127.0.0.1', $port) ? 1 : 0);
    }
    if ($enableHttps) {
        $results[] = array('check' => 'HTTPS port ' . $httpsPort . ' listening', 'ok' => efppTcpOpen('127.0.0.1', $httpsPort) ? 1 : 0);
    }

    $allOk = true;
    foreach ($results as $r) {
        if (!$r['ok']) $allOk = false;
    }

    if ($allOk && $testUser !== null && $testUser['username'] !== '' && $testUser['password'] !== '') {
        if ($enableHttps) {
            // If HTTPS is enabled, check the login redirect on the HTTPS
            // listener (self-signed cert, so no peer verify).
            $noAuth = efppHttpRequest('GET', '127.0.0.1', $httpsPort, '/', array(), '', true);
            $authRequired = ($noAuth['code'] === 302 || $noAuth['code'] === 401);
            $results[] = array(
                'check' => 'No credentials are sent to the login page (https ' . $httpsPort . ')',
                'ok' => $authRequired ? 1 : 0,
                'detail' => 'HTTP ' . $noAuth['code']
            );
        } else {
            $noAuth = efppHttpRequest('GET', '127.0.0.1', $port, '/');
            $authRequired = ($noAuth['code'] === 302 || $noAuth['code'] === 401);
            $results[] = array(
                'check' => 'No credentials are sent to the login page',
                'ok' => $authRequired ? 1 : 0,
                'detail' => 'HTTP ' . $noAuth['code']
            );
        }
        $userInFile = efppUserInHtpasswd($testUser['username']);
        $results[] = array(
            'check' => 'Configured user is present in the password file',
            'ok' => $userInFile ? 1 : 0,
            'detail' => $userInFile ? '' : ($testUser !== null ? 'not found in ' . EFPP_HTPASSWD_FILE : 'no users configured')
        );
        if (!$authRequired) $allOk = false;
        if (!$userInFile) $allOk = false;
    } else {
        $results[] = array('check' => 'Users configured', 'ok' => $testUser !== null ? 1 : 0, 'detail' => $testUser !== null ? '' : 'no users configured');
        $allOk = false;
    }

    efppLog('Test completed: ' . ($allOk ? 'OK' : 'FAILED'));
    return json(array('success' => $allOk, 'results' => $results));
}

function efppUsersEndpoint() {
    $s = efppLoadSettings();
    $userList = array();
    foreach (efppUsersFromSettings($s) as $u) {
        $userList[] = array('username' => $u['username'], 'must_change' => !empty($u['must_change']) ? 1 : 0, 'role' => $u['role']);
    }
    return json(array('success' => true, 'users' => $userList, 'enabled' => ((!empty($s['enable_http'] ?? 0)) || (!empty($s['enable_https'] ?? 0))) ? 1 : 0));
}

function efppSessionUser() {
    // Preferred: the username Apache recorded in the session. The external vhost
    // forwards it as X-Remote-User, but that can arrive as the literal "(null)"
    // when the header is interpolated before REMOTE_USER is populated.
    $h = trim((string)($_SERVER['HTTP_X_REMOTE_USER'] ?? ''));
    if ($h !== '' && $h !== '(null)') {
        return $h;
    }

    // Fallback: read the username straight out of the form-login session cookie.
    // mod_auth_form stores it as "<realm>-user=<username>&<realm>-pw=<password>".
    if (isset($_COOKIE[EFPP_SESSION_COOKIE])) {
        $userKey = EFPP_SESSION_REALM . '-user=';
        foreach (explode('&', (string)$_COOKIE[EFPP_SESSION_COOKIE]) as $pair) {
            $pair = str_replace('+', ' ', $pair);
            if (strncmp($pair, $userKey, strlen($userKey)) === 0) {
                $u = urldecode(trim(substr($pair, strlen($userKey))));
                if ($u !== '') {
                    return $u;
                }
            }
        }
    }
    return '';
}

/**
 * Returns true only when the current visitor is a known account with the
 * limited "user" role. Accounts browsing the normal FPP port (no plugin
 * session) and administrators both return false, so those keep full access.
 */
function efppSessionIsUser() {
    $user = efppSessionUser();
    if ($user === '') {
        return false;
    }
    foreach (efppUsersList() as $u) {
        if (strcasecmp($u['username'], $user) === 0) {
            return $u['role'] === 'user';
        }
    }
    return false;
}

function efppAdminOnlyError() {
    return json(array('success' => false, 'messages' => array(), 'errors' => array('This action requires an Administrator account.')));
}

function efppGetSessionUserEndpoint() {
    $user = efppSessionUser();
    if ($user === '') {
        return json(array('success' => false, 'errors' => array('Session user not available on this connection.')));
    }
    $mustChange = 0;
    foreach (efppUsersList() as $u) {
        if (strcasecmp($u['username'], $user) === 0) {
            $mustChange = !empty($u['must_change']) ? 1 : 0;
            break;
        }
    }

    return json(array('success' => true, 'username' => $user, 'must_change' => $mustChange));
}

function efppLoginSuccessEndpoint() {
    // mod_auth_form sends every successful login here (AuthFormLoginSuccessLocation).
    // Send the visitor on to the FPP UI, or to the change-password page when their
    // account has the must_change flag. Doing it server-side (a 302) avoids flashing
    // the change-password page on accounts that do not require a forced change.
    $user = efppSessionUser();
    $target = '/';
    if ($user !== '') {
        foreach (efppUsersList() as $u) {
            if (strcasecmp($u['username'], $user) === 0) {
                if (!empty($u['must_change'])) {
                    $target = EFPP_CHANGE_PW_URL;
                }
                break;
            }
        }
    }

    // The real client IP is forwarded by the vhost as X-Forwarded-For (set
    // server-side by Apache), since the API is reached through the proxy and
    // REMOTE_ADDR alone would always be 127.0.0.1.
    $clientIp = trim((string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''));
    if ($clientIp === '') {
        $clientIp = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    }
    efppLog('SUCCESS Login: ' . $user . ($clientIp !== '' ? ' from ' . $clientIp : ''));

    header('Location: ' . $target, true, 302);
    exit;
}

function efppChangeMyPasswordEndpoint() {
    $data = efppRequestData();
    $user = efppSessionUser();
    if ($user === '') {
        return json(array('success' => false, 'messages' => array(), 'errors' => array('Session user not available. Log in first.')));
    }

    $password = (string)($data['password'] ?? '');
    $confirm = (string)($data['password_confirm'] ?? '');

    $s = efppLoadSettings();
    $users = efppUsersFromSettings($s);
    $found = -1;
    foreach ($users as $i => $u) {
        if (strcasecmp($u['username'], $user) === 0) {
            $found = $i;
            break;
        }
    }
    if ($found < 0) {
        return json(array('success' => false, 'messages' => array(), 'errors' => array('User not found: ' . $user)));
    }

    $errors = array();
    if (strlen($password) < 6) {
        $errors[] = 'New password must be at least 6 characters long.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Password and confirmation do not match.';
    }
    if (!empty($errors)) {
        return json(array('success' => false, 'messages' => array(), 'errors' => $errors));
    }

    $users[$found]['password'] = efppHashPassword($password);
    $users[$found]['must_change'] = 0;
    if (efppSaveSettingsFile(array_merge($s, array('users' => $users))) === false) {
        return json(array('success' => false, 'messages' => array(), 'errors' => array('Could not write the settings file. Check file permissions.')));
    }

    list($ok, $msg) = efppWriteHtpasswd($users);
    if (!$ok) {
        efppSaveSettingsFile($s);
        return json(array('success' => false, 'messages' => array(), 'errors' => array($msg)));
    }
    efppLog('User changed own password: ' . $user);

    // The form-login session cookie holds the user's password, and mod_auth_form
    // re-validates it against the password file on every request. Leaving the old
    // cookie in place would make the redirect to '/' fail validation and bounce
    // the user back to the login page, so re-issue the session cookie with the
    // new password here (plaintext, matching what Apache wrote at login time).
    $sessionValue = urlencode(EFPP_SESSION_REALM) . '-user=' . urlencode($user)
        . '&' . urlencode(EFPP_SESSION_REALM) . '-pw=' . urlencode($password);
    header('Set-Cookie: ' . EFPP_SESSION_COOKIE . '=' . $sessionValue . '; path=/; HttpOnly; SameSite=Lax');

    return json(array('success' => true, 'messages' => array('Password changed. Continue to the FPP web UI.'), 'errors' => array()));
}

function efppRequestData() {
    $data = $_POST;
    if (empty($data)) {
        $raw = json_decode(file_get_contents('php://input'), true);
        if (is_array($raw)) $data = $raw;
    }
    return $data;
}

function efppAddUserEndpoint() {
    if (efppSessionIsUser()) {
        return efppAdminOnlyError();
    }
    $data = efppRequestData();
    $username = trim((string)($data['username'] ?? ''));
    $password = (string)($data['password'] ?? '');
    $confirm = (string)($data['password_confirm'] ?? '');
    $role = strtolower(trim((string)($data['role'] ?? 'user')));

    $users = efppUsersList();
    $errors = efppValidateLoginUser($username, $password, $confirm, true, $users);
    if ($role !== 'admin' && $role !== 'user') {
        $errors[] = 'Role must be either "admin" or "user".';
    }
    if (!empty($errors)) {
        return json(array('success' => false, 'messages' => array(), 'errors' => $errors));
    }

    $users[] = array(
        'username' => $username,
        'password' => efppHashPassword($password),
        'must_change' => !empty($data['must_change']) ? 1 : 0,
        'role' => $role
    );
    if (efppSaveSettingsFile(array_merge(efppLoadSettings(), array('users' => $users))) === false) {
        return json(array('success' => false, 'messages' => array(), 'errors' => array('Could not write the settings file. Check file permissions.')));
    }

    list($ok, $msg) = efppWriteHtpasswd($users);
    efppLog('User added: ' . $username);

    $usernames = array();
    foreach ($users as $u) $usernames[] = $u['username'];
    return json(array('success' => $ok, 'messages' => array($msg), 'errors' => $ok ? array() : array($msg), 'users' => $usernames));
}

function efppSetPasswordEndpoint() {
    if (efppSessionIsUser()) {
        return efppAdminOnlyError();
    }
    $data = efppRequestData();
    $username = trim((string)($data['username'] ?? ''));
    $password = (string)($data['password'] ?? '');
    $confirm = (string)($data['password_confirm'] ?? '');

    $users = efppUsersList();
    $found = false;
    foreach ($users as $u) {
        if (strcasecmp($u['username'], $username) === 0) {
            $found = true;
            break;
        }
    }
    $errors = efppValidateLoginUser($username, $password, $confirm, false, $users);
    if (!$found) {
        $errors[] = 'User not found: ' . $username;
    }
    if (!empty($errors)) {
        return json(array('success' => false, 'messages' => array(), 'errors' => $errors));
    }

    foreach ($users as $i => $u) {
        if (strcasecmp($u['username'], $username) === 0) {
            $users[$i]['password'] = efppHashPassword($password);
            $users[$i]['must_change'] = !empty($data['must_change']) ? 1 : 0;
            break;
        }
    }
    if (efppSaveSettingsFile(array_merge(efppLoadSettings(), array('users' => $users))) === false) {
        return json(array('success' => false, 'messages' => array(), 'errors' => array('Could not write the settings file. Check file permissions.')));
    }

    list($ok, $msg) = efppWriteHtpasswd($users);
    efppLog('Password changed for user: ' . $username);

    $usernames = array();
    foreach ($users as $u) $usernames[] = $u['username'];
    return json(array('success' => $ok, 'messages' => array($msg), 'errors' => $ok ? array() : array($msg), 'users' => $usernames));
}

function efppDeleteUserEndpoint() {
    if (efppSessionIsUser()) {
        return efppAdminOnlyError();
    }
    $data = efppRequestData();
    $username = trim((string)($data['username'] ?? ''));

    $s = efppLoadSettings();
    $enabled = ((!empty($s['enable_http'] ?? 0)) || (!empty($s['enable_https'] ?? 0)));
    $users = efppUsersFromSettings($s);

    $found = false;
    $kept = array();
    foreach ($users as $u) {
        if (strcasecmp($u['username'], $username) === 0) {
            $found = true;
        } else {
            $kept[] = $u;
        }
    }
    if (!$found) {
        return json(array('success' => false, 'messages' => array(), 'errors' => array('User not found.')));
    }
    if ($enabled && count($users) <= 1) {
        return json(array('success' => false, 'messages' => array(), 'errors' => array('Cannot delete the last user while external access is enabled. Uncheck both "Enable HTTP port" and "Enable HTTPS port" first.')));
    }

    if (efppSaveSettingsFile(array_merge($s, array('users' => $kept))) === false) {
        return json(array('success' => false, 'messages' => array(), 'errors' => array('Could not write the settings file. Check file permissions.')));
    }

    list($ok, $msg) = efppWriteHtpasswd($kept);
    efppLog('User deleted: ' . $username);

    $usernames = array();
    foreach ($kept as $u) $usernames[] = $u['username'];
    return json(array('success' => $ok, 'messages' => array($msg), 'errors' => $ok ? array() : array($msg), 'users' => $usernames));
}

function efppSetUserRoleEndpoint() {
    if (efppSessionIsUser()) {
        return efppAdminOnlyError();
    }
    $data = efppRequestData();
    $username = trim((string)($data['username'] ?? ''));
    $role = strtolower(trim((string)($data['role'] ?? '')));
    if ($role !== 'admin' && $role !== 'user') {
        return json(array('success' => false, 'messages' => array(), 'errors' => array('Role must be either "admin" or "user".')));
    }

    $s = efppLoadSettings();
    $users = efppUsersFromSettings($s);

    $found = -1;
    foreach ($users as $i => $u) {
        if (strcasecmp($u['username'], $username) === 0) {
            $found = $i;
            break;
        }
    }
    if ($found < 0) {
        return json(array('success' => false, 'messages' => array(), 'errors' => array('User not found: ' . $username)));
    }

    if (strcasecmp($users[$found]['username'], efppSessionUser()) === 0) {
        return json(array('success' => false, 'messages' => array(), 'errors' => array('You cannot change your own role.')));
    }
    if ($users[$found]['role'] === 'admin' && $role === 'user') {
        $admins = 0;
        foreach ($users as $u) {
            if ($u['role'] === 'admin') $admins++;
        }
        if ($admins <= 1) {
            return json(array('success' => false, 'messages' => array(), 'errors' => array('Cannot demote the last admin.')));
        }
    }

    $users[$found]['role'] = $role;
    if (efppSaveSettingsFile(array_merge($s, array('users' => $users))) === false) {
        return json(array('success' => false, 'messages' => array(), 'errors' => array('Could not write the settings file. Check file permissions.')));
    }
    efppLog('Role changed for user: ' . $username . ' -> ' . $role);
    return json(array('success' => true, 'messages' => array('Role updated for ' . $username . '.'), 'errors' => array()));
}

function efppLogsEndpoint() {
    $logFile = EFPP_LOG_FILE;
    $lines = array();

    if (!file_exists($logFile)) {
        return json(array('success' => true, 'entries' => array()));
    }

    $fileLines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($fileLines === false) {
        return json(array('success' => false, 'error' => 'Could not read the plugin log file.'));
    }

    $fileLines = array_slice($fileLines, -100);

    foreach ($fileLines as $line) {
        if (preg_match('/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}) (fpp-ExternalFPP) (\S+): (.*)$/', $line, $m)) {
            $message = $m[4];
            $level = 'INFO';
            if (strpos($message, 'ERROR:') === 0) {
                $level = 'ERROR';
            } elseif (strpos($message, 'SUCCESS') === 0) {
                $level = 'SUCCESS';
            } elseif (strpos($message, 'WARNING') === 0) {
                $level = 'WARNING';
            }
            $lines[] = array(
                'timestamp' => $m[1],
                'source' => $m[3],
                'level' => $level,
                'message' => $message
            );
        } else {
            $lines[] = array('timestamp' => '', 'source' => '', 'level' => 'INFO', 'message' => $line);
        }
    }

    return json(array('success' => true, 'entries' => array_reverse($lines)));
}

function efppIconEndpoint() {
    $iconFile = EFPP_PLUGIN_DIR . '/icon.png';
    if (!file_exists($iconFile)) {
        header('HTTP/1.0 404 Not Found');
        return json(array('error' => 'Icon not found'));
    }
    $mtime = filemtime($iconFile);
    $etag = '"' . md5_file($iconFile) . '"';
    header('Content-Type: image/png');
    header('Content-Length: ' . filesize($iconFile));
    header('Cache-Control: no-cache, must-revalidate');
    header('ETag: ' . $etag);
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');

    if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag) {
        header('HTTP/1.1 304 Not Modified');
        exit;
    }

    readfile($iconFile);
    exit;
}

function efppHttpJson($url, $method = 'GET', $timeout = 8) {
    // Returns a decoded JSON array, or null if the HTTP request fails.
    $ch = curl_init($url);
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => 6,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => array(
            'Accept: application/json',
            'User-Agent: fpp-ExternalFPP/1.0 (public-access-check)'
        )
    ));
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($body === false || $code !== 200 || $body === '') {
        return null;
    }
    $decoded = json_decode($body, true);
    return is_array($decoded) ? $decoded : null;
}

function efppGetPublicIp() {
    foreach (array('https://api.ipify.org', 'https://ipv4.icanhazip.com', 'https://ifconfig.me/ip') as $url) {
        $ch = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_CONNECTTIMEOUT => 6,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER => array('User-Agent: fpp-ExternalFPP/1.0 (public-access-check)')
        ));
        $ip = trim((string)curl_exec($ch));
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        // Accept only a public (non-private, non-reserved) IPv4.
        if ($code === 200 && $ip !== ''
            && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
            return $ip;
        }
    }
    return null;
}

// Asks check-host.net's public nodes to try opening a TCP connection to
// $host:$port from the internet. Returns:
//   array('reachable' => true|false|null, 'detail' => string)
// true = at least one external node connected; null = could not determine.
function efppCheckHostPort($host, $port, $nodes = 2) {
    $target = $host . ':' . $port;
    $submit = efppHttpJson('https://check-host.net/check-tcp?host=' . rawurlencode($target) . '&max_nodes=' . $nodes, 'POST', 10);
    if (!is_array($submit) || empty($submit['request_id'])) {
        return array('reachable' => null, 'detail' => 'Could not start a remote port check (outbound request failed).');
    }
    $rid = $submit['request_id'];
    $deadline = microtime(true) + 12;
    $reach = false;
    $refused = false;
    $settled = 0;
    while (microtime(true) < $deadline) {
        $res = efppHttpJson('https://check-host.net/check-result/' . rawurlencode($rid), 'GET', 6);
        if (is_array($res)) {
            $settled = 0;
            foreach ($res as $val) {
                if ($val === null) {
                    continue; // node still running
                }
                $settled++;
                if (is_array($val)) {
                    foreach ($val as $item) {
                        if (is_array($item) && isset($item['time'])) {
                            $reach = true;
                        } elseif (is_array($item) && isset($item['error'])) {
                            $refused = true;
                        }
                    }
                }
            }
            if ($reach) {
                return array('reachable' => true, 'detail' => 'At least one internet node opened a TCP connection.');
            }
            if ($settled > 0 && $settled >= $nodes && $refused) {
                return array('reachable' => false, 'detail' => 'All checking nodes could not connect (timeout/refused).');
            }
            if ($settled > 0 && $settled >= $nodes) {
                return array('reachable' => false, 'detail' => 'Remote check completed but no node connected.');
            }
        }
        usleep(1100000);
    }
    return array('reachable' => null, 'detail' => 'Remote check did not finish in time (try again).');
}

function efppPublicCheck($force = false) {
    $cacheFile = EFPP_PLUGIN_DIR . '/config/public-check.json';
    if (!$force && is_file($cacheFile)) {
        $cached = json_decode(@file_get_contents($cacheFile), true);
        if (is_array($cached) && isset($cached['checked_at']) && (time() - (int)$cached['checked_at']) < 120) {
            return $cached;
        }
    }

    $s = efppLoadSettings();
    $host = efppGetPublicIp();
    if ($host === null) {
        return array('success' => false, 'checked_at' => time(), 'error' => 'Could not determine the public IP (no outbound internet?).');
    }

    $ports = array();
    if (!empty($s['enable_http'] ?? 0)) {
        $ports[] = array('scheme' => 'http', 'port' => (int)$s['port']);
    }
    if (!empty($s['enable_https'] ?? 0)) {
        $ports[] = array('scheme' => 'https', 'port' => (int)$s['https_port']);
    }

    $checks = array();
    foreach ($ports as $p) {
        $r = efppCheckHostPort($host, $p['port']);
        $checks[] = array(
            'scheme' => $p['scheme'],
            'port' => $p['port'],
            'reachable' => $r['reachable'],
            'detail' => $r['detail'],
            'url' => $p['scheme'] . '://' . $host . ':' . $p['port'] . '/'
        );
    }

    $out = array('success' => true, 'checked_at' => time(), 'public_ip' => $host, 'ports' => $checks);
    @file_put_contents($cacheFile, json_encode($out));
    return $out;
}

function efppPublicCheckEndpoint() {
    $force = !empty($_GET['force']);
    return json(efppPublicCheck($force));
}

function getEndpointsfppExternalFPP() {
    $result = array();

    $result[] = array('method' => 'GET', 'endpoint' => 'status', 'callback' => 'efppStatusEndpoint');
    $result[] = array('method' => 'GET', 'endpoint' => 'public-check', 'callback' => 'efppPublicCheckEndpoint');
    $result[] = array('method' => 'POST', 'endpoint' => 'save', 'callback' => 'efppSaveEndpoint');
    $result[] = array('method' => 'POST', 'endpoint' => 'restart', 'callback' => 'efppRestartEndpoint');
    $result[] = array('method' => 'POST', 'endpoint' => 'test', 'callback' => 'efppTestEndpoint');
    $result[] = array('method' => 'GET', 'endpoint' => 'users', 'callback' => 'efppUsersEndpoint');
    $result[] = array('method' => 'POST', 'endpoint' => 'add-user', 'callback' => 'efppAddUserEndpoint');
    $result[] = array('method' => 'POST', 'endpoint' => 'set-user-password', 'callback' => 'efppSetPasswordEndpoint');
    $result[] = array('method' => 'POST', 'endpoint' => 'delete-user', 'callback' => 'efppDeleteUserEndpoint');
    $result[] = array('method' => 'POST', 'endpoint' => 'set-user-role', 'callback' => 'efppSetUserRoleEndpoint');
    $result[] = array('method' => 'GET', 'endpoint' => 'session-user', 'callback' => 'efppGetSessionUserEndpoint');
    $result[] = array('method' => 'GET', 'endpoint' => 'login-success', 'callback' => 'efppLoginSuccessEndpoint');
    // Some clients (and curl -L) re-send the original method when following the
    // form-login 302, so accept POST here too rather than 404-ing them.
    $result[] = array('method' => 'POST', 'endpoint' => 'login-success', 'callback' => 'efppLoginSuccessEndpoint');
    $result[] = array('method' => 'POST', 'endpoint' => 'change-my-password', 'callback' => 'efppChangeMyPasswordEndpoint');
    $result[] = array('method' => 'GET', 'endpoint' => 'logs', 'callback' => 'efppLogsEndpoint');
    $result[] = array('method' => 'GET', 'endpoint' => 'icon', 'callback' => 'efppIconEndpoint');
    $result[] = array('method' => 'GET', 'endpoint' => 'login-page', 'callback' => 'efppLoginPageEndpoint');
    $result[] = array('method' => 'POST', 'endpoint' => 'save-login-page', 'callback' => 'efppSaveLoginPageEndpoint');
    $result[] = array('method' => 'POST', 'endpoint' => 'reset-login-page', 'callback' => 'efppResetLoginPageEndpoint');
    $result[] = array('method' => 'GET', 'endpoint' => 'change-password-page', 'callback' => 'efppGetChangePasswordPageEndpoint');
    $result[] = array('method' => 'POST', 'endpoint' => 'save-change-password-page', 'callback' => 'efppSaveChangePasswordPageEndpoint');
    $result[] = array('method' => 'POST', 'endpoint' => 'reset-change-password-page', 'callback' => 'efppResetChangePasswordPageEndpoint');

    return $result;
}
