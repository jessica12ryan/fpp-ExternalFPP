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
$httpsPort = 8443;
$enableHttp = 1;
$enableHttps = 1;
if (file_exists($settingsFile)) {
    $s = json_decode(file_get_contents($settingsFile), true);
    if (is_array($s)) {
        $enabled = !empty($s['enabled']) ? 1 : 0;
        $port = (int)($s['port'] ?? 8080);
        $backendPort = (int)($s['backend_port'] ?? 80);
        $httpsPort = (int)($s['https_port'] ?? 8443);
        $enableHttp = !empty($s['enable_http'] ?? 0) ? 1 : 0;
        $enableHttps = !empty($s['enable_https'] ?? 0) ? 1 : 0;
        $enabled = ($enableHttp || $enableHttps) ? 1 : 0;
    }
}
?>

<?php include __DIR__ . '/tabs.inc'; ?>

<style>
#efpp_config_table input[type=number]::-webkit-outer-spin-button,
#efpp_config_table input[type=number]::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
}
#efpp_config_table input[type=number] {
    -moz-appearance: textfield;
}
</style>

<div style="margin:0 auto;">
    <fieldset class="border p-3">
        <legend>External Web Access</legend>
        <div class="p-3">
            <p>
                This plugin opens an additional TCP port that serves the FPP web UI behind a
                <b>login page</b>. The normal FPP UI (port 80) is not changed. Configure
                <b>who can log in</b> in the <b>Users</b> tab.
            </p>
            <table id="efpp_config_table">
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
                    <td style="padding: 4px;"><b>Enable HTTP port:</b></td>
                    <td style="padding: 4px;">
                        <input type="checkbox" id="efpp_enable_http"
                               onchange="efpp.togglePort();"
                               <?php echo $enableHttp ? 'checked' : ''; ?>>
                        <?php echo efppHelp('When checked, the HTTP port below is served over plain HTTP.'); ?>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 4px;"><b>HTTP port:</b></td>
                    <td style="padding: 4px;">
                        <input type="number" id="efpp_port" min="1" max="65535" size="8"
                               onblur="efpp.onBlur('efpp_port');"
                               value="<?php echo htmlspecialchars($port); ?>">
                        <?php echo efppHelp('HTTP port.'); ?>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 4px;"><b>Enable HTTPS port:</b></td>
                    <td style="padding: 4px;">
                        <input type="checkbox" id="efpp_enable_https"
                               onchange="efpp.togglePort();"
                               <?php echo $enableHttps ? 'checked' : ''; ?>>
                        <?php echo efppHelp('When checked, the HTTPS port below is served over TLS using FPP\'s built-in self-signed certificate.'); ?>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 4px;"><b>HTTPS port:</b></td>
                    <td style="padding: 4px;">
                        <input type="number" id="efpp_https_port" min="1" max="65535" size="8"
                               onblur="efpp.onBlur('efpp_https_port');"
                               value="<?php echo htmlspecialchars($httpsPort); ?>">
                        <?php echo efppHelp('TLS (https) port. The plugin uses FPP\'s built-in self-signed certificate.'); ?>
                    </td>
                </tr>
                <?php if (!empty($efpp_ui_level) && $efpp_ui_level >= 2): ?>
                <tr>
                    <td style="padding: 4px;"><i class="fas fa-fw fa-flask ui-level-2"></i> <b>Backend port (FPP web):</b></td>
                    <td style="padding: 4px;">
                        <input type="number" id="efpp_backend_port" min="1" max="65535" size="8"
                               onblur="efpp.onBlur('efpp_backend_port');"
                               value="<?php echo htmlspecialchars($backendPort); ?>">
                        <?php echo efppHelp("Normally 80; only change if FPP's UI is served on another port."); ?>
                    </td>
                </tr>
                <?php else: ?>
                <input type="hidden" id="efpp_backend_port" value="<?php echo htmlspecialchars($backendPort); ?>">
                <?php endif; ?>
                <tr>
                    <td style="padding: 4px;">&nbsp;</td>
                    <td style="padding: 4px;">
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
                    <td style="padding: 4px;"><b>Internal URL:</b></td>
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
                <li>Check <b>Enable HTTP port</b> and/or <b>Enable HTTPS port</b> &mdash; changes apply immediately when you
                    tick or untick them (unchecking both turns external access off). Port numbers are saved
                    automatically as soon as you leave the field. When HTTPS is enabled, the port is served
                    over TLS using FPP's built-in self-signed certificate (your browser will show a
                    certificate warning, which is normal for a self-signed cert). To force HTTPS, enable only
                    the HTTPS port.</li>
                <li>This plugin uses FPP's existing Apache web server &mdash; no additional packages are required.</li>
            </ul>
        </div>
    </fieldset>
</div>

<script>
var efpp = {
    apiBase: 'api/plugin/fpp-ExternalFPP',
    enabled: false,
    // Last values the server confirmed, used to only save on change.
    saved: { port: null, https_port: null, backend_port: null },

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
        // Keep the checkboxes in sync with what actually got applied (a failed
        // save reverts them so the UI never shows an un-applied state).
        $('#efpp_enable_http').prop('checked', !!s.enable_http);
        $('#efpp_enable_https').prop('checked', !!s.enable_https);
        // Remember the confirmed port values (do NOT overwrite the inputs while
        // the user is typing; this is only used for change detection / revert).
        efpp.saved.port = String(s.port);
        efpp.saved.https_port = String(s.https_port);
        efpp.saved.backend_port = String(s.backend_port);
        var host = window.location.hostname;
        var urls = [];
        if (s.enable_http) urls.push('<a href="http://' + host + ':' + s.port + '/" target="_blank">http://' + host + ':' + s.port + '/</a>');
        if (s.enable_https) urls.push('<a href="https://' + host + ':' + s.https_port + '/" target="_blank">https://' + host + ':' + s.https_port + '/</a>');
        $('#efpp_status_text').html(efpp.enabled
            ? '<span class="text-success">&#9679; Enabled</span>'
            : '<span class="text-danger">&#9679; Disabled</span>');
        $('#efpp_url_cell').html(efpp.enabled && urls.length
            ? urls.join('<br>')
            : '<span class="text-secondary">Disabled</span>');
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

    // Toggling either port checkbox applies the change immediately.
    togglePort: function() {
        efpp.save();
    },

    // A port field lost focus: save only if its value actually changed.
    onBlur: function(field) {
        var v = $('#' + field).val();
        if (efpp.saved[field] === null || efpp.saved[field] !== v) {
            efpp.save();
        }
    },

    // After a failed save, put the field values back to what the server last
    // confirmed (checkboxes are reverted by renderStatus).
    revert: function(vals) {
        $('#efpp_port').val(vals.port);
        $('#efpp_https_port').val(vals.https_port);
        $('#efpp_backend_port').val(vals.backend_port);
        $('#efpp_enable_http').prop('checked', !!vals.enable_http);
        $('#efpp_enable_https').prop('checked', !!vals.enable_https);
    },

    save: function() {
        var vals = {
            port: $('#efpp_port').val(),
            backend_port: $('#efpp_backend_port').val(),
            https_port: $('#efpp_https_port').val(),
            enable_http: $('#efpp_enable_http').is(':checked') ? 1 : 0,
            enable_https: $('#efpp_enable_https').is(':checked') ? 1 : 0
        };
        $('#efpp_result').html('<span class="text-warning">Saving and applying...</span>');
        $.ajax({
            url: efpp.apiBase + '/save',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(vals),
            dataType: 'json',
            success: function(data) {
                efpp.handleResponse(data);
                if (!data.success) {
                    efpp.revert(vals);
                }
            },
            error: function(xhr) {
                efpp.revert(vals);
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
    // Seed change detection with the server-rendered values so clicking through
    // a field without editing it doesn't trigger a needless save.
    efpp.saved.port = $('#efpp_port').val();
    efpp.saved.https_port = $('#efpp_https_port').val();
    efpp.saved.backend_port = $('#efpp_backend_port').val();
    efpp.refreshStatus();
    setInterval(efpp.refreshStatus, 10000);
});
</script>

<?php include __DIR__ . '/footer.inc'; ?>
