<?php
/**
 * #############################################################
 * ## External FPP Web Access (fpp-ExternalFPP)               ##
 * ## Author: jessica12ryan                                ##
 * ## URL: https://github.com/jessica12ryan/fpp-ExternalFPP##
 * #############################################################
 * ## status.php                                              ##
 * ## Live status page for the plugin.                        ##
 * #############################################################
 */

$pluginDir = __DIR__;
$settingsFile = $pluginDir . '/config/settings.json';

$srvEnabled = 0;
$srvPort = 8080;
if (file_exists($settingsFile)) {
    $s = json_decode(file_get_contents($settingsFile), true);
    if (is_array($s)) {
        $srvEnabled = !empty($s['enabled']) ? 1 : 0;
        $srvPort = (int)($s['port'] ?? 8080);
    }
}
?>

<?php include __DIR__ . '/tabs.inc'; ?>

<div style="margin:0 auto;">
    <fieldset class="border p-3">
        <legend>External FPP Status</legend>
        <div class="p-3">
            <div id="efpp_status_table">
                <span class="text-secondary">Loading status...</span>
            </div>
            <p style="margin-top:10px;">
                <a class="buttons" href="plugin.php?plugin=fpp-ExternalFPP&page=config.php">Configure</a>
            </p>
        </div>
    </fieldset>
</div>

<script>
var efppStatus = {
    apiBase: 'api/plugin/fpp-ExternalFPP',

    refresh: function() {
        $.ajax({
            url: efppStatus.apiBase + '/status',
            type: 'GET',
            dataType: 'json',
            success: function(s) {
                var host = window.location.hostname;
                var url = 'http://' + host + ':' + s.port + '/';
                var rows = [
                    ['Plugin configured', s.configured ? '<span class="text-success">Yes</span>' : '<span class="text-danger">No</span>'],
                    ['External access', s.enabled ? '<span class="text-success">&#9679; Enabled</span>' : '<span class="text-danger">&#9679; Disabled</span>'],
                    ['Listen port', s.port],
                    ['Backend (FPP web) port', s.backend_port],
                    ['Username', s.username ? escHtml(s.username) : '<span class="text-danger">Not set</span>'],
                    ['Password configured', s.has_password ? '<span class="text-success">Yes</span>' : '<span class="text-danger">No</span>'],
                    ['Apache vhost enabled', s.apache_conf_enabled ? '<span class="text-success">Yes</span>' : '<span class="text-danger">No</span>'],
                    ['Password file present', s.htpasswd_exists ? '<span class="text-success">Yes</span>' : '<span class="text-danger">No</span>'],
                    ['External port listening', s.listening ? '<span class="text-success">Yes</span>' : '<span class="text-danger">No</span>'],
                    ['Backend FPP web reachable', s.backend_reachable ? '<span class="text-success">Yes</span>' : '<span class="text-danger">No</span>'],
                    ['External URL', s.enabled ? '<a href="' + url + '" target="_blank">' + url + '</a>' : '<span class="text-secondary">-</span>']
                ];
                var html = '<table class="fppTable" style="width:auto;">';
                for (var i = 0; i < rows.length; i++) {
                    html += '<tr><td style="padding:4px;"><b>' + rows[i][0] + ':</b></td><td style="padding:4px;">' + rows[i][1] + '</td></tr>';
                }
                html += '</table>';
                if (!s.enabled && s.configured) {
                    html += '<p class="text-warning" style="margin-top:8px;">The external port is currently disabled. Go to the Config tab to enable it.</p>';
                }
                $('#efpp_status_table').html(html);
            },
            error: function() {
                $('#efpp_status_table').html('<span class="text-danger">Could not reach the plugin API. Check the FPP web server logs.</span>');
            }
        });
    }
};

function escHtml(s) {
    if (s == null) return '';
    return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

$(document).ready(function() {
    efppStatus.refresh();
    setInterval(efppStatus.refresh, 5000);
});
</script>

<?php include __DIR__ . '/footer.inc'; ?>
