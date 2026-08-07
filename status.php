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
        $srvHttp = !empty($s['enable_http'] ?? 0);
        $srvHttps = !empty($s['enable_https'] ?? 0);
        $srvEnabled = ($srvHttp || $srvHttps) ? 1 : 0;
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

    <fieldset class="border p-3" style="margin-top:12px;">
        <legend>Public Accessibility (External URL)</legend>
        <div class="p-3">
            <div id="efpp_public_table">
                <span class="text-secondary">Not checked yet.</span>
            </div>
            <p style="margin-top:10px;">
                <input type="button" class="buttons" value="&#8635; Check now" onclick="efppStatus.checkPublic(true);">
                <span class="text-secondary" style="margin-left:8px;">Asks servers on the internet to try connecting to the external ports, to show whether your router forwards them beyond your local network.</span>
            </p>
        </div>
    </fieldset>
</div>

<script>
var efppStatus = {
    apiBase: 'api/plugin/fpp-ExternalFPP',

    checkPublic: function(force) {
        $('#efpp_public_table').html('<span class="text-warning">Checking from the internet - this can take up to ~15s...</span>');
        $.ajax({
            url: efppStatus.apiBase + '/public-check' + (force ? '?force=1' : ''),
            type: 'GET',
            dataType: 'json',
            success: function(d) {
                efppStatus.renderPublic(d);
            },
            error: function() {
                $('#efpp_public_table').html('<span class="text-danger">Could not reach the plugin API.</span>');
            }
        });
    },

    renderPublic: function(d) {
        if (!d.success) {
            $('#efpp_public_table').html('<span class="text-danger">' + escHtml(d.error || 'Public check failed.') + '</span>');
            return;
        }
        var html = '<table class="fppTable" style="width:auto;">';
        html += '<tr><td style="padding:4px;"><b>Public IP:</b></td><td style="padding:4px;">' +
            escHtml(d.public_ip) +
            ' <span class="text-secondary">(the address the internet sees, from the FPP\'s own outbound connection)</span></td></tr>';
        if (!d.ports.length) {
            html += '<tr><td style="padding:4px;"><b>Ports:</b></td><td style="padding:4px;">' +
                '<span class="text-secondary">No external ports are enabled. Enable HTTP and/or HTTPS in the Config tab.</span></td></tr>';
        }
        for (var i = 0; i < d.ports.length; i++) {
            var p = d.ports[i];
            var dot = p.reachable === true ? '<span class="text-success">&#9679;</span>'
                : (p.reachable === false ? '<span class="text-danger">&#9679;</span>'
                : '<span class="text-warning">&#9679;</span>');
            var txt = p.reachable === true ? 'Reachable'
                : (p.reachable === false ? 'Not reached' : 'Unknown');
            var color = p.reachable === true ? 'text-success'
                : (p.reachable === false ? 'text-danger' : 'text-warning');
            html += '<tr><td style="padding:4px;"><b>' + (p.scheme === 'https' ? 'HTTPS' : 'HTTP') + ' port (' + p.port + '):</b></td>' +
                '<td style="padding:4px;"><span class="' + color + '">' + dot + ' ' + txt + '</span>' +
                ' <a href="' + escAttr(p.url) + '" target="_blank">' + escHtml(p.url) + '</a><br>' +
                '<span class="text-secondary">' + escHtml(p.detail) + '</span></td></tr>';
        }
        html += '</table>';
        html += '<p class="text-secondary" style="margin-top:8px;">"Reachable" means an internet server could open a TCP connection to the public IP on that port (i.e. your router is forwarding it). A check can be inconclusive if a firewall blocks the checking service.</p>';
        $('#efpp_public_table').html(html);
    },

    refresh: function() {
        $.ajax({
            url: efppStatus.apiBase + '/status',
            type: 'GET',
            dataType: 'json',
            success: function(s) {
                var host = window.location.hostname;
                var urls = [];
                if (s.enable_http) {
                    var hu = 'http://' + host + ':' + s.port + '/';
                    urls.push('<a href="' + escAttr(hu) + '" target="_blank">' + escHtml(hu) + '</a>');
                }
                if (s.enable_https) {
                    var su = 'https://' + host + ':' + s.https_port + '/';
                    urls.push('<a href="' + escAttr(su) + '" target="_blank">' + escHtml(su) + '</a>');
                }
                var rows = [
                    ['Plugin configured', s.configured ? '<span class="text-success">Yes</span>' : '<span class="text-danger">No</span>'],
                    ['FPP UI Reachable', s.backend_port],
                    ['External access', s.enabled ? '<span class="text-success">&#9679; Enabled</span>' : '<span class="text-danger">&#9679; Disabled</span>'],
                    ['HTTP enabled', s.enable_http ? '<span class="text-success">Yes</span>' : '<span class="text-secondary">No</span>'],
                    ['HTTP port', s.enable_http ? s.port : '<span class="text-secondary">-</span>'],
                    ['HTTP port listening', s.enable_http ? (s.listening ? '<span class="text-success">Yes</span>' : '<span class="text-danger">No</span>') : '<span class="text-secondary">-</span>'],
                    ['HTTPS enabled', s.enable_https ? '<span class="text-success">Yes</span>' : '<span class="text-secondary">No</span>'],
                    ['HTTPS port', s.enable_https ? s.https_port : '<span class="text-secondary">-</span>'],
                    ['HTTPS port listening', s.enable_https ? (s.https_listening ? '<span class="text-success">Yes</span>' : '<span class="text-danger">No</span>') : '<span class="text-secondary">-</span>'],

                    ['Users', s.user_count > 0
                        ? s.users.map(escHtml).join(', ')
                        : '<span class="text-danger">None (' + (s.enabled ? 'plugin cannot stay enabled' : 'add a user to enable') + ')</span>'],
                    ['Users count', s.user_count],
                    ['Apache vhost enabled', s.apache_conf_enabled ? '<span class="text-success">Yes</span>' : '<span class="text-danger">No</span>'],
                    ['Password file present', s.htpasswd_exists ? '<span class="text-success">Yes</span>' : '<span class="text-danger">No</span>'],
                    ['Internal URL', s.enabled && urls.length ? urls.join('<br>') : '<span class="text-secondary">-</span>']
                ];
                var html = '<table class="fppTable" style="width:auto;">';
                for (var i = 0; i < rows.length; i++) {
                    html += '<tr><td style="padding:4px;"><b>' + rows[i][0] + ':</b></td><td style="padding:4px;">' + rows[i][1] + '</td></tr>';
                }
                html += '</table>';
                if (!s.enabled && s.configured) {
                    html += '<p class="text-warning" style="margin-top:8px;">The external port is currently disabled. Go to the Config tab to enable it.</p>';
                }
                if (!s.enabled && s.user_count === 0) {
                    html += '<p class="text-danger" style="margin-top:8px;">No users are configured. Add at least one user in the Users tab before enabling the plugin.</p>';
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

function escAttr(s) {
    return escHtml(s).replace(/'/g, '&#39;');
}

$(document).ready(function() {
    efppStatus.refresh();
    setInterval(efppStatus.refresh, 5000);
    efppStatus.checkPublic(false);
});
</script>

<?php include __DIR__ . '/footer.inc'; ?>
