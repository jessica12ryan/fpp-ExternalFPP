<h3>Help &mdash; Usage Guide</h3>
<p>This page is the full <b>Help &amp; Usage Guide</b> for the External FPP plugin. It also appears here via <b>F1</b> so the guide is available as a popup from every tab.</p>

<h4>What This Plugin Does</h4>
<p>FPP's web UI is normally served without a password on port <code>80</code>. This plugin adds a <b>new web address</b> (default <code>8080</code> HTTP and <code>8443</code> HTTPS) that shows the <b>exact same UI</b> but first asks for a <b>username and password</b> on a customizable login page. Nothing on port <code>80</code> is changed, so local users keep working as before. It uses only FPP's built-in Apache (&mdash; no extra packages) and survives reboots.</p>

<h4>Quick Start</h4>
<ol>
    <li>Open <b>Content Setup &rarr; External FPP</b> &rarr; <b>Config</b>.</li>
    <li>Add at least one <b>Admin</b> user on the <b>Users</b> tab (username + password &ge; 6 chars).</li>
    <li>Back on <b>Config</b>, check <b>Enable HTTP port</b> and/or <b>Enable HTTPS port</b> and set the port numbers (blur the field to save, or toggle the checkbox to apply immediately).</li>
    <li>Browse to <code>http://&lt;fpp-ip&gt;:8080/</code> or <code>https://&lt;fpp-ip&gt;:8443/</code> &mdash; you will hit the Login Page. The HTTPS port uses FPP's self-signed cert, so expect a browser warning.</li>
    <li>Sign in; to sign out visit <code>/logout</code> on the external port.</li>
</ol>

<h4>Managing Users</h4>
<ul>
    <li><b>Add a user</b> on the <b>Users</b> tab; pick <b>Admin</b> (full dashboard) or <b>User</b> (no plugin dashboard, can still use FPP).</li>
    <li><b>Change a password</b> via <b>Change Password</b> on the user's row, or tick <b>must change password at next login</b> to force a reset.</li>
    <li><b>Remove a user</b> with <b>Delete</b>; the last <b>Admin</b> (and last user) cannot be deleted while external access is enabled.</li>
</ul>

<h4>Custom Pages</h4>
<p>The <b>Pages</b> tab edits three HTML files (<code>www/login.html</code>, <code>www/change-password.html</code>, <code>www/access-denied.html</code>) with live <b>Save</b>/<b>Reset to Default</b> and a <b>Preview</b> link. Each has required code (e.g. <code>httpd_username</code> / <code>httpd_password</code> on Login); saving without it shows a warning.</p>

<h4>Changing Ports &amp; Router Forwarding</h4>
<p>Change the HTTP/HTTPS port on <b>Config</b> and click out of the field &mdash; it saves and applies via <code>scripts/apply.php</code> immediately. If your router forwards a different public port, enable <b>Use Custom Port via Router Firewall</b> (<b>Advanced</b> UI Level) and set <b>Forwarded HTTP/HTTPS port</b> so <b>Status &rarr; Public Accessibility</b> probes the correct address. The <b>Backend port</b> field appears at <b>Experimental</b> level.</p>

<h4>Security Notes</h4>
<ul>
    <li>HTTPS is TLS-encrypted (self-signed); HTTP is plain text &mdash; do not expose HTTP to the internet; put it behind a VPN or TLS reverse proxy, or disable it.</li>
    <li>If FPP's built-in <b>UI Password</b> is also on (Status/Control &rarr; FPP Settings &rarr; UI tab), you'll be asked for a second password; turn FPP's off or enter its <code>admin</code> credentials.</li>
</ul>

<h4>Troubleshooting</h4>
<ul>
    <li><b>External port not reachable</b> &rarr; Check <b>Status</b> for <b>Apache vhost enabled</b> + <b>port listening</b> (both green) and use the <b>Internal URL</b>.</li>
    <li><b>503 / Bad Gateway</b> &rarr; Backend port doesn't match where FPP serves the UI (normally <code>80</code>); fix it on <b>Config</b>.</li>
    <li><b>Sent back to login with right password</b> &rarr; Re-set the password on <b>Users</b> and verify the <b>Pages &rarr; Login Page</b> has the required fields.</li>
    <li><b>Prompted again after login</b> &rarr; That's FPP's own UI password, not this plugin's &mdash; disable it in FPP Settings &rarr; UI tab.</li>
    <li><b>No Logs tab</b> &rarr; Set UI Level to <b>Advanced</b> or higher (FPP Settings &rarr; UI tab).</li>
</ul>
