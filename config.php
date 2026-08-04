<?php
/**
 * #############################################################
 * ## External FPP Web Access (fpp-ExternalFPP)               ##
 * ## Author: jessica12ryan                                ##
 * ## URL: https://github.com/jessica12ryan/fpp-ExternalFPP##
 * #############################################################
 * ## config.php                                              ##
 * ## Plugin settings page (Content Setup -> External FPP).   ##
 * #############################################################
 */

$pluginDir = __DIR__;
$settingsFile = $pluginDir . '/config/settings.json';

$enabled = 0;
$port = 8080;
$backendPort = 80;
$username = '';
$hasPassword = false;
if (file_exists($settingsFile)) {
    $s = json_decode(file_get_contents($settingsFile), true);
    if (is_array($s)) {
        $enabled = !empty($s['enabled']) ? 1 : 0;
        $port = (int)($s['port'] ?? 8080);
        $backendPort = (int)($s['backend_port'] ?? 80);
        $username = $s['username'] ?? '';
        $hasPassword = !empty($s['password']);
    }
}
?>

<?php include __DIR__ . '/tabs.inc'; ?>

<div style="margin:0 auto;">
    <fieldset class="border p-3">
        <legend>External Web Access</legend>
        <div class="p-3">
            <p>
                This plugin opens an additional TCP port that serves the FPP web UI behind a
                <b>username and password</b>. The normal FPP UI (port 80) is not changed.
            </p>
            <table>
                <tr>
                    <td style="padding: 4px;"><b>Status:</b></td>
                    <td style="padding: 4px;">
                        <?php if ($enabled): ?>
                            <span class="text-success">&#9679; Enabled</span>
                        <?php else: ?>
                            <span class="text-danger">&#9679; Disabled</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 4px;"><b>Enable external access:</b></td>
                    <td style="padding: 4px;">
                        <select id="efpp_enabled">
                            <option value="0"<?php echo $enabled ? '' : ' selected'; ?>>Disabled</option>
                            <option value="1"<?php echo $enabled ? ' selected' : ''; ?>>Enabled</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 4px;"><b>Listen port:</b></td>
                    <td style="padding: 4px;">
                        <input type="number" id="efpp_port" min="1" max="65535" size="8"
                               value="<?php echo htmlspecialchars($port); ?>">
                        <i>(the new password-protected port, e.g. 8080)</i>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 4px;"><b>Backend port (FPP web):</b></td>
                    <td style="padding: 4px;">
                        <input type="number" id="efpp_backend_port" min="1" max="65535" size="8"
                               value="<?php echo htmlspecialchars($backendPort); ?>">
                        <i>(normally 80, only change if FPP's UI is served on another port)</i>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 4px;"><b>Username:</b></td>
                    <td style="padding: 4px;">
                        <input type="text" id="efpp_username" size="30" autocomplete="off"
                               value="<?php echo htmlspecialchars($username); ?>">
                    </td>
                </tr>
                <tr>
                    <td style="padding: 4px;"><b>Password:</b></td>
                    <td style="padding: 4px;">
                        <input type="password" id="efpp_password" size="30" autocomplete="new-password"
                               placeholder="Leave blank to keep the current password">
                    </td>
                </tr>
                <tr>
                    <td style="padding: 4px;"><b>Confirm password:</b></td>
                    <td style="padding: 4px;">
                        <input type="password" id="efpp_password_confirm" size="30" autocomplete="new-password"
                               placeholder="Repeat the new password">
                    </td>
                </tr>
                <tr>
                    <td style="padding: 4px;"><b>Password configured:</b></td>
                    <td style="padding: 4px;">
                        <?php if ($hasPassword): ?>
                            <span class="text-success">Yes</span>
                        <?php else: ?>
                            <span class="text-danger">No</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 4px;"></td>
                    <td style="padding: 4px;">
                        <input type="button" class="buttons" value="Save &amp; Apply" onclick="efpp.save();">
                        <input type="button" class="buttons" value="Test" onclick="efpp.test();">
                        <input type="button" class="buttons" value="Disable" onclick="efpp.stop();">
                    </td>
                </tr>
                <tr>
                    <td></td>
                    <td>
                        <div id="efpp_result" style="margin-top:4px;"></div>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 4px;"><b>External URL:</b></td>
                    <td style="padding: 4px;" id="efpp_url_cell">
                        <span class="text-secondary">Enabled to see URL</span>
                    </td>
                </tr>
            </table>
        </div>
    </fieldset>

    <br />

    <fieldset class="border p-3">
        <legend>Important Notes</legend>
        <div class="p-3">
            <ul>
                <li>Anyone who knows the URL of the extra port will be prompted for the username/password. Without valid credentials Apache returns <code>401 Unauthorized</code>.</li>
                <li>The extra port is plain HTTP. Do not expose it directly to the internet &mdash; use a VPN or a TLS-terminating reverse proxy for remote access.</li>
                <li>If FPP's built-in UI Password is also set, access through this port may prompt for that password as well, depending on FPP's configuration.</li>
                <li>This plugin uses FPP's existing Apache web server &mdash; no additional packages are required.</li>
            </ul>
        </div>
    </fieldset>
</div>

<script>
var efpp = {
    apiBase: 'api/plugin/fpp-ExternalFPP',

    showError: function(msg) {
        $('#efpp_result').html('<span class="text-danger">' + msg + '</span>');
    },

    showSuccess: function(msg) {
        $('#efpp_result').html('<span class="text-success">' + msg + '</span>');
    },

    handleResponse: function(data) {
        var html = '';
        if (data.errors && data.errors.length > 0) {
            html += '<span class="text-danger"><b>Errors:</b><br>' + data.errors.map(escHtml).join('<br>') + '</span>';
        }
        if (data.messages && data.messages.length > 0) {
            html += '<span class="text-success"><b>' + (data.success ? 'OK' : 'Notes') + ':</b><br>' + data.messages.map(escHtml).join('<br>') + '</span>';
        }
        if (data.success === false && !data.errors) {
            html += '<span class="text-danger">Unknown error occurred.</span>';
        }
        $('#efpp_result').html(html);
        if (data.settings) {
            efpp.renderStatus(data.settings);
        }
    },

    renderStatus: function(s) {
        var host = window.location.hostname;
        var url = 'http://' + host + ':' + s.port + '/';
        $('#efpp_url_cell').html(s.enabled ? '<a href="' + url + '" target="_blank">' + url + '</a>' : '<span class="text-secondary">Enabled to see URL</span>');
    },

    save: function() {
        $('#efpp_result').html('<span class="text-warning">Saving and applying...</span>');
        $.ajax({
            url: efpp.apiBase + '/save',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                enabled: $('#efpp_enabled').val(),
                port: $('#efpp_port').val(),
                backend_port: $('#efpp_backend_port').val(),
                username: $('#efpp_username').val(),
                password: $('#efpp_password').val(),
                password_confirm: $('#efpp_password_confirm').val()
            }),
            dataType: 'json',
            success: function(data) {
                efpp.handleResponse(data);
            },
            error: function(xhr) {
                efpp.showError('Could not reach the plugin API. Check the FPP web server logs.');
            }
        });
    },

    test: function() {
        $('#efpp_result').html('<span class="text-warning">Testing...</span>');
        $.ajax({
            url: efpp.apiBase + '/test',
            type: 'POST',
            contentType: 'application/json',
            data: '{}',
            dataType: 'json',
            success: function(data) {
                var html = '';
                if (data.results) {
                    html += '<table class="fppTable" style="width:auto;">';
                    for (var i = 0; i < data.results.length; i++) {
                        var r = data.results[i];
                        html += '<tr><td>' + escHtml(r.check) + '</td><td>' +
                            (r.ok ? '<span class="text-success">&#10003;</span>' : '<span class="text-danger">&#10007;</span>') +
                            (r.detail ? ' <span class="text-secondary">(' + escHtml(r.detail) + ')</span>' : '') +
                            '</td></tr>';
                    }
                    html += '</table>';
                }
                if (data.errors && data.errors.length) {
                    html += '<span class="text-danger"><b>Errors:</b><br>' + data.errors.map(escHtml).join('<br>') + '</span>';
                }
                $('#efpp_result').html(html);
            },
            error: function(xhr) {
                efpp.showError('Could not reach the plugin API. Check the FPP web server logs.');
            }
        });
    },

    stop: function() {
        if (!confirm('Disable the external (password-protected) port? The normal FPP UI is unaffected.')) {
            return;
        }
        $('#efpp_result').html('<span class="text-warning">Disabling...</span>');
        $.ajax({
            url: efpp.apiBase + '/stop',
            type: 'POST',
            contentType: 'application/json',
            data: '{}',
            dataType: 'json',
            success: function(data) {
                efpp.handleResponse(data);
            },
            error: function(xhr) {
                efpp.showError('Could not reach the plugin API. Check the FPP web server logs.');
            }
        });
    }
};

function escHtml(s) {
    if (s == null) return '';
    return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

$(document).ready(function() {
    $.ajax({
        url: efpp.apiBase + '/status',
        type: 'GET',
        dataType: 'json',
        success: function(s) {
            efpp.renderStatus(s);
        }
    });
});
</script>

<?php include __DIR__ . '/footer.inc'; ?>
