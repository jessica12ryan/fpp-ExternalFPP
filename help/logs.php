<h3>Logs &mdash; Plugin Log Viewer</h3>
<p>View the plugin's log file to debug saves, logins, tests, and page edits. This tab only appears when FPP <b>UI Level</b> is at least <b>Advanced (1)</b> (seen in <code>tabs.inc:efpp_ui_level()</code>).</p>

<h4>Log File</h4>
<p>Path shown at the top: <code>/home/fpp/media/logs/plugin-fpp-ExternalFPP.log</code> (or <code>$LOGDIR</code> if set, from <code>EFPP_LOG_FILE</code> in <code>api.php:33</code>). Also accessible via SSH: <code>tail -n 50 /home/fpp/media/logs/plugin-fpp-ExternalFPP.log</code>. Entries are appended by <code>efppLog()</code> in both <code>api.php</code> and <code>scripts/apply.php</code>.</p>

<h4>Table Columns</h4>
<ul>
    <li><b>Date/Time</b> &mdash; <code>YYYY-MM-DD HH:MM:SS</code> (width 160 px, nowrap).</li>
    <li><b>Level</b> &mdash; <span style="color:#198754"><b>SUCCESS</b></span> (green), <span style="color:#dc3545"><b>ERROR</b></span> (red), <span style="color:#fd7e14"><b>WARNING</b></span> (orange), <b>INFO</b> (default). Derived from the message prefix (<code>ERROR:</code>, <code>SUCCESS</code>, <code>WARNING</code>) in <code>api.php:efppLogsEndpoint()</code>.</li>
    <li><b>Source</b> &mdash; Component that logged it: <code>api</code> or <code>apply</code> (parsed from <code>YYYY-MM-DD HH:MM:SS fpp-ExternalFPP source: message</code>).</li>
    <li><b>Message</b> &mdash; Details, e.g. <code>Settings saved (port=8080, http=1, https=1)</code>, <code>SUCCESS Login: alice from 192.168.1.10</code>, <code>User added: bob</code>, <code>Test completed: OK</code>.</li>
</ul>

<h4>Refresh Button</h4>
<p>Click <b>&#8635; Refresh</b> to re-fetch the last 100 lines via <code>GET api/plugin/fpp-ExternalFPP/logs</code> (shown newest-first). The table is limited to 100 entries; beyond that older lines are trimmed server-side via <code>array_slice(..., -100)</code>.</p>

<h4>How to Use</h4>
<ul>
    <li>After a save or <b>Test</b> that fails, look for <span style="color:#dc3545">ERROR</span> rows.</li>
    <li>After a login, check for <code>SUCCESS Login: &lt;user&gt; from &lt;ip&gt;</code> (IP comes from <code>X-Forwarded-For</code> via the external vhost, falling back to <code>REMOTE_ADDR</code>).</li>
    <li>If the table says <b>No log entries found</b> the file doesn't exist yet &mdash; trigger a save or login to generate entries.</li>
</ul>
