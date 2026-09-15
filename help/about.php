<h3>About &mdash; Plugin Information</h3>
<p>Adds a <b>second, password-protected web address</b> that serves the same FPP web UI. The normal UI on port <code>80</code> is left completely unchanged. Works behind NGINX Proxy Manager and uses only what is already built into FPP.</p>

<h4>Features</h4>
<ul>
    <li>Serves FPP's web UI on an extra port of your choice (HTTP <code>8080</code>, HTTPS <code>8443</code> by default).</li>
    <li>Protects that port with a username + password login page (multi-user, Admin/User roles).</li>
    <li>Shows the <b>Internal URL</b> of each enabled port under <b>Public Accessibility</b> on Status and Config.</li>
    <li>Optional <b>custom public ports</b> when your router forwards a different port than the plugin listens on.</li>
    <li>No extra packages or daemons &mdash; uses FPP's own Apache + <code>mod_auth_form</code>.</li>
    <li>Survives reboots (extra port comes up when FPP starts via <code>scripts/apply.php</code>).</li>
    <li>Multiple users: add / delete / change password + forced password change on next login.</li>
    <li>Editable login, change-password, and access-denied pages (<b>Pages</b> tab).</li>
    <li>Live status, public reachability check (via <code>check-host.net</code>), and log viewer.</li>
</ul>

<h4>How It Works</h4>
<ol>
    <li>You add users and enable HTTP/HTTPS on the <b>Config</b> / <b>Users</b> tabs (saved to <code>config/settings.json</code> and <code>config/plugin.fpp-ExternalFPP.htpasswd</code> / <code>.groups</code>).</li>
    <li><code>scripts/apply.php</code> generates <code>/etc/apache2/conf-enabled/fpp-externalfpp.conf</code> and (re)starts Apache on those ports.</li>
    <li>Visitors on the external port hit a form-login page; Apache's <code>mod_auth_form</code> sets cookie <code>fppefpp</code> (realm <code>FPP External Web Access</code>).</li>
    <li>The vhost proxies authenticated requests to <code>127.0.0.1:&lt;backend_port&gt;</code> (FPP) with <code>X-Remote-User</code> / <code>X-Forwarded-For</code>.</li>
</ol>

<h4>Links (on page)</h4>
<ul>
    <li><b>GitHub Repository</b> &mdash; <code>https://github.com/jessica12ryan/fpp-ExternalFPP</code></li>
    <li><b>Issue Tracker &amp; Feature Requests</b> &mdash; <code>https://github.com/jessica12ryan/fpp-ExternalFPP/issues</code></li>
</ul>

<h4>Plugin Info</h4>
<p>Name: <b>External FPP Web Access</b> &bull; Author: <b>jessica12ryan</b> &bull; License: <b>MIT</b> &bull; FPP is &copy; Falcon Christmas. This plugin is independent and not affiliated with or endorsed by the Falcon Christmas project.</p>
