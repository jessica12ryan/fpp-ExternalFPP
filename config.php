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
if (file_exists($settingsFile)) {
    $s = json_decode(file_get_contents($settingsFile), true);
    if (is_array($s)) {
        $enabled = !empty($s['enabled']) ? 1 : 0;
        $port = (int)($s['port'] ?? 8080);
        $backendPort = (int)($s['backend_port'] ?? 80);
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
                <b>login page</b>. The normal FPP UI (port 80) is not changed. Configure
                <b>who can log in</b> in the <b>Users</b> tab.
            </p>
            <table>
                <tr>
                    <td style="padding: 4px;"><b>Status:</b></td>
                    <td style="padding: 4px;" id="efpp_status_text">
                        <?php if ($enabled): ?>
                            <span class="text-success">&#9679; Enabled</span>
                        <?php else: ?>
                            <span class="text-danger">&#9679; Disabled</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 4px;"><b>External access:</b></td>
                    <td style="padding: 4px;">
                        <button type="button" id="efpp_toggle" class="buttons" onclick="efpp.toggle();">
                            <?php echo $enabled ? 'Disable External Access' : 'Enable External Access'; ?>
                        </button>
                        <?php echo efppHelp('Turns the password-protected port on/off without changing the settings below.'); ?>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 4px;"><b>Listen port:</b></td>
                    <td style="padding: 4px;">
                        <input type="number" id="efpp_port" min="1" max="65535" size="8"
                               value="<?php echo htmlspecialchars($port); ?>">
                        <?php echo efppHelp('The new password-protected port, e.g. 8080.'); ?>
                    </td>
                </tr>
                <?php if (!empty($efpp_ui_level) && $efpp_ui_level >= 2): ?>
                <tr>
                    <td style="padding: 4px;"><b>Backend port (FPP web):</b></td>
                    <td style="padding: 4px;">
                        <input type="number" id="efpp_backend_port" min="1" max="65535" size="8"
                               value="<?php echo htmlspecialchars($backendPort); ?>">
                        <?php echo efppHelp("Normally 80; only change if FPP's UI is served on another port."); ?>
                    </td>
                </tr>
                <?php else: ?>
                <input type="hidden" id="efpp_backend_port" value="<?php echo htmlspecialchars($backendPort); ?>">
                <?php endif; ?>
                <tr>
                    <td style="padding: 4px;"></td>
                    <td style="padding: 4px;">
                        <input type="button" class="buttons" value="Save &amp; Apply" onclick="efpp.save();">
                        <input type="button" class="buttons" value="Test" onclick="efpp.test();">
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
                <li>Anyone who knows the URL of the extra port will be shown a <b>login page</b>. Without valid
                    credentials Apache redirects back to the login page and the UI stays protected.</li>
                <li>The login page is fully customizable in the <b>Pages</b> tab.</li>
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
    enabled: false,

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
        } else {
            efpp.refreshStatus();
        }
    },

    renderStatus: function(s) {
        efpp.enabled = !!s.enabled;
        var host = window.location.hostname;
        var url = 'http://' + host + ':' + s.port + '/';
        $('#efpp_status_text').html(efpp.enabled
            ? '<span class="text-success">&#9679; Enabled</span>'
            : '<span class="text-danger">&#9679; Disabled</span>');
        $('#efpp_toggle').html(efpp.enabled ? 'Disable External Access' : 'Enable External Access');
        $('#efpp_url_cell').html(efpp.enabled
            ? '<a href="' + url + '" target="_blank">' + url + '</a>'
            : '<span class="text-secondary">Enabled to see URL</span>');
    },

    refreshStatus: function() {
        $.ajax({
            url: efpp.apiBase + '/status',
            type: 'GET',
            dataType: 'json',
            success: efpp.renderStatus,
            error: function() {}
        });
    },

    toggle: function() {
        if (efpp.enabled) {
            if (!confirm('Disable the external (password-protected) port? The normal FPP UI is unaffected.')) {
                return;
            }
            efpp.setEnabled(false);
        } else {
            efpp.setEnabled(true);
        }
    },

    setEnabled: function(enable) {
        $('#efpp_result').html('<span class="text-warning">' + (enable ? 'Enabling...' : 'Disabling...') + '</span>');
        $.ajax({
            url: efpp.apiBase + (enable ? '/start' : '/stop'),
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
    },

    save: function() {
        $('#efpp_result').html('<span class="text-warning">Saving and applying...</span>');
        $.ajax({
            url: efpp.apiBase + '/save',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                port: $('#efpp_port').val(),
                backend_port: $('#efpp_backend_port').val()
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
    }
};

function escHtml(s) {
    if (s == null) return '';
    return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

$(document).ready(function() {
    efpp.refreshStatus();
    setInterval(efpp.refreshStatus, 10000);
});
</script>

<?php include __DIR__ . '/footer.inc'; ?>
