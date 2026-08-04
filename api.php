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
define('EFPP_APACHE_CONF_NAME', 'fpp-externalfpp');
define('EFPP_APACHE_CONF_ENABLED', '/etc/apache2/conf-enabled/fpp-externalfpp.conf');
define('EFPP_LOG_DIR', getenv('LOGDIR') ?: '/home/fpp/media/logs');
define('EFPP_LOG_FILE', EFPP_LOG_DIR . '/plugin-fpp-ExternalFPP.log');

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
        'enabled' => 0,
        'port' => 8080,
        'backend_port' => 80,
        'username' => '',
        'password' => ''
    );
    if (!file_exists(EFPP_SETTINGS_FILE)) {
        return $defaults;
    }
    $s = json_decode(file_get_contents(EFPP_SETTINGS_FILE), true);
    if (!is_array($s)) {
        return $defaults;
    }
    return array_merge($defaults, $s);
}

function efppSaveSettingsFile($s) {
    if (!is_dir(EFPP_PLUGIN_DIR . '/config')) {
        @mkdir(EFPP_PLUGIN_DIR . '/config', 0775, true);
    }
    return @file_put_contents(EFPP_SETTINGS_FILE, json_encode($s, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
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

function efppHttpStatus($host, $port, $path = '/', $user = null, $pass = null) {
    $port = (int)$port;
    $fp = @fsockopen($host, $port, $errno, $errstr, 3);
    if (!$fp) {
        return array('code' => 0, 'body' => '');
    }
    $req = 'GET ' . $path . " HTTP/1.1\r\n";
    $req .= 'Host: ' . $host . "\r\n";
    if ($user !== null) {
        $req .= 'Authorization: Basic ' . base64_encode($user . ':' . $pass) . "\r\n";
    }
    $req .= "Connection: close\r\n\r\n";
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
    return array('code' => $code, 'body' => $resp);
}

function efppStatusData() {
    $s = efppLoadSettings();
    $port = (int)$s['port'];
    $backendPort = (int)$s['backend_port'];
    return array(
        'configured' => file_exists(EFPP_SETTINGS_FILE) ? 1 : 0,
        'enabled' => !empty($s['enabled']) ? 1 : 0,
        'port' => $port,
        'backend_port' => $backendPort,
        'username' => $s['username'],
        'has_password' => !empty($s['password']) ? 1 : 0,
        'apache_conf_enabled' => file_exists(EFPP_APACHE_CONF_ENABLED) ? 1 : 0,
        'htpasswd_exists' => file_exists(EFPP_HTPASSWD_FILE) ? 1 : 0,
        'listening' => efppTcpOpen('127.0.0.1', $port) ? 1 : 0,
        'backend_reachable' => efppTcpOpen('127.0.0.1', $backendPort) ? 1 : 0,
        'hostname' => php_uname('n')
    );
}

function efppValidateData($data, $existing) {
    $errors = array();
    $clean = $existing;

    $clean['enabled'] = !empty($data['enabled']) ? 1 : 0;

    $port = (int)($data['port'] ?? $existing['port']);
    if ($port < 1 || $port > 65535) {
        $errors[] = 'Listen port must be between 1 and 65535.';
    }
    $clean['port'] = $port;

    $backendPort = (int)($data['backend_port'] ?? $existing['backend_port']);
    if ($backendPort < 1 || $backendPort > 65535) {
        $errors[] = 'Backend (FPP web) port must be between 1 and 65535.';
    }
    $clean['backend_port'] = $backendPort;

    if ($port === $backendPort) {
        $errors[] = 'The listen port and the backend (FPP web) port must be different.';
    }

    if (array_key_exists('username', $data)) {
        $username = trim((string)$data['username']);
        if (strpos($username, ':') !== false) {
            $errors[] = 'Username cannot contain a colon (:).';
        }
        $clean['username'] = $username;
    }

    if (array_key_exists('password', $data) && $data['password'] !== '') {
        $pw = (string)$data['password'];
        if (strlen($pw) < 6) {
            $errors[] = 'Password must be at least 6 characters long.';
        }
        if ($pw !== (string)($data['password_confirm'] ?? '')) {
            $errors[] = 'Password and confirmation do not match.';
        }
        $clean['password'] = $pw;
    }

    if ($clean['enabled'] && ($clean['username'] === '' || $clean['password'] === '')) {
        $errors[] = 'You must set both a username and a password before enabling the plugin.';
    }

    return array($clean, $errors);
}

function efppStatusEndpoint() {
    return json(efppStatusData());
}

function efppSaveEndpoint() {
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
    efppLog('Settings saved (enabled=' . $clean['enabled'] . ', port=' . $clean['port'] . ')');
    return json($result);
}

function efppSetEnabled($enabled) {
    $s = efppLoadSettings();
    $s['enabled'] = $enabled ? 1 : 0;

    $errors = array();
    if ($enabled && ($s['username'] === '' || $s['password'] === '')) {
        $errors[] = 'Set a username and password in the Config tab before enabling the plugin.';
        return json(array('success' => false, 'messages' => array(), 'errors' => $errors));
    }
    if ($enabled && ((int)$s['port'] < 1 || (int)$s['port'] > 65535 || (int)$s['port'] === (int)$s['backend_port'])) {
        $errors[] = 'Configure a valid listen port in the Config tab before enabling the plugin.';
        return json(array('success' => false, 'messages' => array(), 'errors' => $errors));
    }

    efppSaveSettingsFile($s);
    $result = efppRunApply();
    $result['settings'] = efppStatusData();
    return json($result);
}

function efppStartEndpoint() {
    efppLog('Start requested');
    return efppSetEnabled(true);
}

function efppStopEndpoint() {
    efppLog('Stop requested');
    return efppSetEnabled(false);
}

function efppRestartEndpoint() {
    efppLog('Restart requested');
    $result = efppRunApply();
    $result['settings'] = efppStatusData();
    return json($result);
}

function efppTestEndpoint() {
    $s = efppLoadSettings();
    $port = (int)$s['port'];
    $backendPort = (int)$s['backend_port'];

    if (!empty($s['enabled']) === false) {
        return json(array('success' => false, 'errors' => array('The plugin is not enabled. Enable it first, then test.')));
    }

    $results = array();
    $results[] = array('check' => 'Backend FPP web server (port ' . $backendPort . ')', 'ok' => efppTcpOpen('127.0.0.1', $backendPort) ? 1 : 0);
    $results[] = array('check' => 'External port ' . $port . ' listening', 'ok' => efppTcpOpen('127.0.0.1', $port) ? 1 : 0);

    $allOk = true;
    foreach ($results as $r) {
        if (!$r['ok']) $allOk = false;
    }

    if ($allOk && $s['username'] !== '' && $s['password'] !== '') {
        $noAuth = efppHttpStatus('127.0.0.1', $port, '/');
        $results[] = array(
            'check' => 'No credentials returns 401 (auth required)',
            'ok' => ($noAuth['code'] === 401) ? 1 : 0,
            'detail' => 'HTTP ' . $noAuth['code']
        );
        $withAuth = efppHttpStatus('127.0.0.1', $port, '/', $s['username'], $s['password']);
        $ok = $withAuth['code'] >= 200 && $withAuth['code'] < 400;
        $results[] = array(
            'check' => 'Valid credentials can reach the UI',
            'ok' => $ok ? 1 : 0,
            'detail' => 'HTTP ' . $withAuth['code']
        );
        if ($noAuth['code'] !== 401) $allOk = false;
        if (!$ok) $allOk = false;
    } else {
        $results[] = array('check' => 'Credentials configured', 'ok' => 0, 'detail' => 'username/password missing');
        $allOk = false;
    }

    efppLog('Test completed: ' . ($allOk ? 'OK' : 'FAILED'));
    return json(array('success' => $allOk, 'results' => $results));
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

function getEndpointsfppExternalFPP() {
    $result = array();

    $result[] = array('method' => 'GET', 'endpoint' => 'status', 'callback' => 'efppStatusEndpoint');
    $result[] = array('method' => 'POST', 'endpoint' => 'save', 'callback' => 'efppSaveEndpoint');
    $result[] = array('method' => 'POST', 'endpoint' => 'start', 'callback' => 'efppStartEndpoint');
    $result[] = array('method' => 'POST', 'endpoint' => 'stop', 'callback' => 'efppStopEndpoint');
    $result[] = array('method' => 'POST', 'endpoint' => 'restart', 'callback' => 'efppRestartEndpoint');
    $result[] = array('method' => 'POST', 'endpoint' => 'test', 'callback' => 'efppTestEndpoint');
    $result[] = array('method' => 'GET', 'endpoint' => 'logs', 'callback' => 'efppLogsEndpoint');
    $result[] = array('method' => 'GET', 'endpoint' => 'icon', 'callback' => 'efppIconEndpoint');

    return $result;
}
