<?php
/**
 * #############################################################
 * ## External FPP Web Access (fpp-ExternalFPP)               ##
 * ## Author: jessica12ryan                                ##
 * ## URL: https://github.com/jessica12ryan/fpp-ExternalFPP##
 * #############################################################
 * ## logs.php                                                ##
 * ## Displays the plugin log file.                           ##
 * #############################################################
 */

$logDir = getenv('LOGDIR') ?: '/home/fpp/media/logs';
$logFile = $logDir . '/plugin-fpp-ExternalFPP.log';
?>

<?php include __DIR__ . '/tabs.inc'; ?>

<div style="margin:0 auto;">
    <fieldset class="border p-3">
        <legend>External FPP Log</legend>
        <div class="p-3">
            <p>
                <b>Log file:</b> <code><?php echo htmlspecialchars($logFile); ?></code>
                &nbsp;&nbsp;
                <input type="button" class="buttons" value="&#8635; Refresh" onclick="efppLogs.refresh();">
            </p>
            <div id="efpp_log_container" style="max-height: 600px; overflow-y: auto; border: 1px solid var(--bs-border-color, #dee2e6); border-radius: 4px;">
                <table style="width:100%; border-collapse: collapse; font-family: 'Courier New', monospace; font-size: 12px;">
                    <thead>
                        <tr>
                            <th style="width: 160px; text-align:left; padding:6px 8px; border-bottom: 2px solid var(--bs-border-color, #dee2e6);">Date/Time</th>
                            <th style="width: 70px; text-align:left; padding:6px 8px; border-bottom: 2px solid var(--bs-border-color, #dee2e6);">Level</th>
                            <th style="width: 120px; text-align:left; padding:6px 8px; border-bottom: 2px solid var(--bs-border-color, #dee2e6);">Source</th>
                            <th style="text-align:left; padding:6px 8px; border-bottom: 2px solid var(--bs-border-color, #dee2e6);">Message</th>
                        </tr>
                    </thead>
                    <tbody id="efpp_log_body">
                        <tr><td colspan="4" style="text-align:center;padding:20px;color:var(--bs-secondary-color, #6c757d);">Loading logs...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </fieldset>
</div>

<script>
var efppLogs = {
    apiBase: 'api/plugin/fpp-ExternalFPP',

    refresh: function() {
        $('#efpp_log_body').html('<tr><td colspan="4" style="text-align:center;padding:20px;color:var(--bs-secondary-color, #6c757d);">Loading logs...</td></tr>');
        $.ajax({
            url: efppLogs.apiBase + '/logs',
            type: 'GET',
            dataType: 'json',
            success: function(data) {
                if (data.success && data.entries) {
                    if (data.entries.length === 0) {
                        $('#efpp_log_body').html('<tr><td colspan="4" style="text-align:center;padding:20px;color:var(--bs-secondary-color, #6c757d);">No log entries found.</td></tr>');
                    } else {
                        var html = '';
                        for (var i = 0; i < data.entries.length; i++) {
                            var e = data.entries[i];
                            var color = '';
                            if (e.level === 'ERROR') color = 'color:#dc3545;';
                            else if (e.level === 'SUCCESS') color = 'color:#198754;';
                            else if (e.level === 'WARNING') color = 'color:#fd7e14;';
                            html += '<tr>' +
                                '<td style="white-space:nowrap; padding:4px 8px; border-bottom:1px solid var(--bs-border-color, #dee2e6);">' + escHtml(e.timestamp) + '</td>' +
                                '<td style="padding:4px 8px; border-bottom:1px solid var(--bs-border-color, #dee2e6); ' + color + '"><b>' + escHtml(e.level) + '</b></td>' +
                                '<td style="padding:4px 8px; border-bottom:1px solid var(--bs-border-color, #dee2e6);">' + escHtml(e.source) + '</td>' +
                                '<td style="padding:4px 8px; border-bottom:1px solid var(--bs-border-color, #dee2e6);">' + escHtml(e.message) + '</td>' +
                                '</tr>';
                        }
                        $('#efpp_log_body').html(html);
                    }
                } else {
                    $('#efpp_log_body').html('<tr><td colspan="4" style="text-align:center;padding:20px;color:#dc3545;">Error loading logs.</td></tr>');
                }
            },
            error: function() {
                $('#efpp_log_body').html('<tr><td colspan="4" style="text-align:center;padding:20px;color:#dc3545;">Could not reach the plugin API.</td></tr>');
            }
        });
    }
};

function escHtml(s) {
    if (s == null) return '';
    return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

$(document).ready(function() {
    efppLogs.refresh();
});
</script>

<?php include __DIR__ . '/footer.inc'; ?>
