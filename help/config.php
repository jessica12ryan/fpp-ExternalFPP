<h3>Config &mdash; External FPP Settings</h3>
<p>Open an additional TCP port that serves the same FPP web UI behind a <b>login page</b>. The normal FPP UI on port <code>80</code> is never changed. Configure who can log in on the <b>Users</b> tab.</p>

<h4>Status</h4>
<ul>
    <li><b>Status:</b> &mdash; <span style="color:#198754">&#9679; Enabled</span> when either HTTP or HTTPS is enabled; <span style="color:#dc3545">&#9679; Disabled</span> otherwise (polled every 10 s via <code>GET api/plugin/fpp-ExternalFPP/status</code>).</li>
    <li>Warning <b>Add at least one Admin user</b> &mdash; Shown until an <b>Admin</b> user exists; while shown the <b>Enable HTTP/HTTPS</b> checkboxes are disabled because the login page would otherwise be unreachable.</li>
</ul>

<h4>Enable HTTP Port / HTTP Port</h4>
<ul>
    <li><b>Enable HTTP port</b> &mdash; When checked the HTTP port is served over plain HTTP. Uncheck to force HTTPS-only. Applies immediately on toggle via <code>POST api/plugin/fpp-ExternalFPP/save</code>. Disabled until an <b>Admin</b> user exists. Stored as <code>enable_http</code> in <code>config/settings.json</code>.</li>
    <li><b>HTTP port</b> &mdash; TCP port for plain HTTP (default <code>8080</code>). Saves on blur only if changed. Must be <code>1-65535</code> and differ from the HTTPS and backend ports when enabled. Stored as <code>port</code>.</li>
</ul>

<h4>Enable HTTPS Port / HTTPS Port</h4>
<ul>
    <li><b>Enable HTTPS port</b> &mdash; When checked the HTTPS port is served over TLS using FPP's built-in self-signed certificate (browser will show a certificate warning &mdash; expected). Applies immediately on toggle. Disabled until an <b>Admin</b> user exists. Stored as <code>enable_https</code>.</li>
    <li><b>HTTPS port</b> &mdash; TLS port (default <code>8443</code>). Same validation as HTTP port. Must differ from HTTP and backend ports. Stored as <code>https_port</code>.</li>
</ul>

<h4><i class="fas fa-graduation-cap"></i> Use Custom Port via Router Firewall</h4>
<p>Only visible at <b>Advanced</b> UI Level or higher. Tick when your router forwards a <b>different</b> public port to the plugin's internal port (e.g. router forwards <code>9999</code> to internal <code>8081</code>). When on, the two fields below appear and the <b>Status</b> tab's <b>Public Accessibility</b> probes the <b>forwarded</b> port instead of the internal one.</p>
<ul>
    <li><b>Forwarded HTTP port</b> &mdash; Public port the internet connects to for HTTP; blank means use the internal HTTP port. Stored as <code>http_public_port</code>.</li>
    <li><b>Forwarded HTTPS port</b> &mdash; Public port for HTTPS; blank means use the internal HTTPS port. Stored as <code>https_public_port</code>.</li>
    <li>Toggling the checkbox saves immediately; leaving a forwarded-port field saves on blur. Blanks are allowed.</li>
</ul>

<h4><i class="fas fa-flask"></i> Backend Port (FPP UI)</h4>
<p>Only visible at <b>Experimental</b> UI Level. Normally <code>80</code>; change only if FPP's own web server is on another port. Must differ from any enabled HTTP/HTTPS port. Stored as <code>backend_port</code>. Saves on blur. Hidden input preserves the value at lower UI levels so a save doesn't wipe it.</p>

<h4>Buttons &amp; Links</h4>
<ul>
    <li><b>Test</b> &mdash; Calls <code>POST api/plugin/fpp-ExternalFPP/test</code> and shows a table: Backend reachable, HTTP/HTTPS listening, login-page redirect, and user-in-password-file check.</li>
    <li><b>Internal URL</b> &mdash; Live <code>http://&lt;host&gt;:&lt;port&gt;/</code> / <code>https://&lt;host&gt;:&lt;port&gt;/</code> link(s) for enabled listeners; shows <span style="color:#6c757d">Disabled</span> when off.</li>
</ul>

<h4>Saving Behaviour</h4>
<ul>
    <li>Checkbox toggles (<b>Enable HTTP/HTTPS</b>, <b>Use Custom Port</b>) call <code>save</code> immediately.</li>
    <li>Port number fields call <code>save</code> on <b>blur</b> only if the value changed (tracked in <code>efpp.saved</code>). On failure the fields revert to the last server-confirmed values.</li>
    <li>At lower UI levels the forwarded-port and backend-port fields are not rendered and are omitted from the save payload so stored values are not cleared.</li>
    <li>All saves write <code>config/settings.json</code> and run <code>scripts/apply.php</code> (via <code>sudo</code> when not root) to regenerate <code>/etc/apache2/conf-enabled/fpp-externalfpp.conf</code> and the password/group files.</li>
</ul>

<h4>Important Notes</h4>
<ul>
    <li>Anyone who knows the external URL sees a <b>login page</b> first; Apache redirects unauthenticated requests back to it.</li>
    <li>The login page itself is editable on the <b>Pages</b> tab.</li>
    <li>At least one <b>Admin</b> user is required before external access can be enabled.</li>
    <li>Enabling only HTTPS is recommended for internet exposure; HTTP sends credentials and cookie in clear text.</li>
</ul>
