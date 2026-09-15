<h3>Status &mdash; Overview</h3>
<p>This page gives you a live, at-a-glance view of the external web-access proxy that sits in front of FPP's normal web server (port 80).</p>

<h4>External FPP Status</h4>
<ul>
    <li><b>Plugin Status</b> &mdash; <span style="color:#198754">&#9679; Enabled</span> when either <b>Enable HTTP port</b> or <b>Enable HTTPS port</b> is checked on the <b>Config</b> tab; <span style="color:#dc3545">&#9679; Disabled</span> otherwise.</li>
    <li><b>Backend port (FPP UI)</b> &mdash; Shown only at <b>Experimental</b> UI Level and only when it is not the default <code>80</code>. The port where FPP itself serves the UI; the proxy forwards to it. An icon <i class="fas fa-flask"></i> marks the level.</li>
    <li><b>HTTP enabled / HTTPS enabled</b> &mdash; Whether each listener is currently enabled (<span style="color:#198754">Yes</span> / <span style="color:#6c757d">No</span>).</li>
    <li><b>HTTP port / HTTPS port</b> &mdash; TCP ports the proxy listens on (defaults <code>8080</code> / <code>8443</code>).</li>
    <li><b>HTTP port listening / HTTPS port listening</b> &mdash; Live <code>Yes</code>/<code>No</code> check via a local TCP open test to <code>127.0.0.1:port</code>.</li>
    <li><b><i class="fas fa-graduation-cap"></i> Forwarded HTTP/HTTPS port</b> &mdash; Shown only at <b>Advanced</b> UI Level when <b>Use Custom Port via Router Firewall</b> is on. The public port your router forwards to the internal port.</li>
    <li><b>Users</b> &mdash; Total users configured in <code>config/settings.json</code>.</li>
    <li><b><i class="fas fa-graduation-cap"></i> Apache vhost enabled</b> &mdash; <b>Advanced</b> only; whether <code>/etc/apache2/conf-enabled/fpp-externalfpp.conf</code> is present.</li>
    <li><b><i class="fas fa-graduation-cap"></i> Password file present</b> &mdash; <b>Advanced</b> only; whether <code>config/plugin.fpp-ExternalFPP.htpasswd</code> exists.</li>
    <li><b>Internal URL</b> &mdash; Direct LAN link(s) like <code>http://&lt;host&gt;:8080/</code> and <code>https://&lt;host&gt;:8443/</code> for enabled listeners.</li>
    <li><b>External URL</b> &mdash; Public link(s) built from the last cached <b>Public IP</b> plus the forwarded (or internal) port; empty until a public check has run.</li>
</ul>

<h4>Public Accessibility (External URL)</h4>
<p>Asks servers on the internet to try opening a TCP connection to your public IP on each enabled port, proving whether your router forwards the port beyond your LAN. Uses <code>check-host.net</code> and caches the result for ~2 minutes (<code>config/public-check.json</code>).</p>
<ul>
    <li><b>Public IP</b> &mdash; The address the internet sees, obtained from the FPP's own outbound connection (via <code>api.ipify.org</code> / <code>icanhazip</code> / <code>ifconfig.me</code>).</li>
    <li><b>HTTP port (n) / HTTPS port (n)</b> &mdash; Each row shows <span style="color:#198754">&#9679; Reachable</span> (at least one node connected), <span style="color:#dc3545">&#9679; Not reached</span>, or <span style="color:#fd7e14">&#9679; Unknown</span> plus a public <code>http(s)://&lt;ip&gt;:&lt;port&gt;/</code> link and a detail string.</li>
    <li><b>&#8635; Check now</b> &mdash; Re-runs the check with <code>?force=1</code> (<code>GET api/plugin/fpp-ExternalFPP/public-check</code>). Can take ~15 seconds.</li>
</ul>

<h4>Auto-Refresh</h4>
<p>Both sections poll via <code>GET api/plugin/fpp-ExternalFPP/status</code> (every 5 s) and the public section re-checks automatically on load. Click <b>Configure</b> to jump to <code>plugin.php?plugin=fpp-ExternalFPP&amp;page=config.php</code>.</p>

<h4>Troubleshooting</h4>
<ul>
    <li><b>Disabled but configured</b> &mdash; Either no <b>Admin</b> users exist (add one on <b>Users</b>) or both HTTP/HTTPS are unchecked (enable one on <b>Config</b>).</li>
    <li><b>Port not listening</b> &mdash; Check that the port isn't already used by another service and that the Apache vhost is enabled; see <b>Config &rarr; Test</b> for details.</li>
    <li><b>External URL says Not reached</b> &mdash; Port is not forwarded on your router, or a firewall blocks the check servers; verify NAT/port-forward rules.</li>
</ul>
