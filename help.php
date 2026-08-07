<?php
/**
 * #############################################################
 * ## External FPP Web Access (fpp-ExternalFPP)               ##
 * ## Author: jessica12ryan                                ##
 * ## URL: https://github.com/jessica12ryan/fpp-ExternalFPP##
 * #############################################################
 * ## help.php                                                ##
 * ## Help & usage guide for the plugin.                      ##
 * #############################################################
 */

$pluginDir = __DIR__;
?>

<?php include __DIR__ . '/tabs.inc'; ?>

<div style="margin:0 auto;">
    <fieldset class="border p-3">
        <legend>External FPP - Help &amp; Usage Guide</legend>
        <div class="p-3">

            <h3>What This Plugin Does</h3>
            <p>
                FPP's web UI is normally served without any password on port 80. This plugin adds a
                <b>new web address</b> (default <code>8080</code>, with an HTTPS address on
                <code>8443</code>) that shows the exact same UI but first asks for a
                <b>username and password</b>. Nothing on the normal port is changed,
                so local users keep working exactly as before.
            </p>

            <hr>

            <h3>Quick Start</h3>
            <ol>
                <li>Open <b>Content Setup &rarr; External FPP</b></li>
                <li>Set an <b>HTTP port</b> (e.g. <code>8080</code>) and an <b>HTTPS port</b> (e.g. <code>8443</code>)</li>
                <li>Add at least one <b>user</b> in the <b>Users</b> tab (username + password)</li>
                <li>Leave <b>Enable HTTP port</b> and <b>Enable HTTPS port</b> checked to serve both, or uncheck either to force HTTPS-only or HTTP-only</li>
                <li>Edit fields as needed &mdash; port numbers save automatically when you click out of the field,
                    and ticking <b>Enable HTTP port</b> / <b>Enable HTTPS port</b> applies immediately</li>
                <li>Browse to <code>https://&lt;fpp-ip&gt;:8443/</code> &mdash; you will be asked to log in</li>
            </ol>
            <p>
                Because FPP uses a <b>self-signed certificate</b>, your browser will warn you about
                the connection before the login page loads. This is expected &mdash; click through
                to continue.
            </p>

            <hr>

            <h3>Accessing the Protected UI</h3>
            <p>
                When you open the extra port you are taken to a <b>login page</b> (a sign-in
                form styled like FPP). Enter the configured username/password to reach the
                FPP web UI. Requests without a valid session are redirected back to the
                login page.
            </p>

            <hr>

            <h3>Managing Users</h3>
            <ul>
                <li><b>Add a user:</b> use the <b>Users</b> tab and pick a username and password.</li>
                <li><b>Change a password:</b> use <b>Change Password</b> on that user's row in the <b>Users</b> tab.</li>
                <li><b>Force a password reset:</b> tick <b>must change password at next login</b> for the user. The next time they sign in they will be held on the password page until they set a new one.</li>
                <li><b>Remove a user:</b> use the delete button on that user's row (you can't delete the last user while external access is enabled).</li>
            </ul>

            <hr>

            <h3>Customizing the Pages</h3>
            <p>
                Open the <b>Pages</b> tab to edit the HTML of the <b>Login Page</b> (shown to
                visitors who are not signed in) and the <b>Change Password Page</b> (shown right
                after signing in). Each editor lists the code that is required for the page to
                work &mdash; for example the <code>&lt;form method="post"&gt;</code> and the
                <code>httpd_username</code> / <code>httpd_password</code> fields on the login
                page. Pages are read on every request, so changes take effect as soon as you
                save them.
            </p>
            <p>
                To sign out, visit <code>/logout</code> in the browser (this removes your login
                and returns you to the login page).
            </p>

            <hr>

            <h3>Changing the Listen / HTTPS Port</h3>
            <p>
                Change the <b>HTTP port</b> or <b>HTTPS port</b> in the Config tab and click out of the field.
                It saves and applies automatically &mdash; just use your new address from then on. The
                HTTP port and the HTTPS port must be different from each other and from the backend
                (FPP web) port.
            </p>
            <p>
                If your <b>router firewall</b> forwards a different port to the plugin (for example it
                forwards <code>9999</code> to the HTTP port <code>8081</code> on the FPP), tick
                <b>Use Custom Port via Router Firewall</b> in the Config tab. When checked, the
                <b>HTTP public port</b> and <b>HTTPS public port</b> fields appear &mdash; set these to
                the ports the internet actually connects to (the ones your router forwards). The
                <b>Public Accessibility (External URL)</b> check on the Status tab will then probe those
                public ports, and show the correct public URL, instead of wrongly testing the internal
                port. Leave the checkbox unticked to use the HTTP/HTTPS ports directly.
            </p>

            <hr>

            <h3>Security Considerations</h3>
            <ul>
                <li>When the <b>HTTPS port</b> is enabled, it is served over <b>TLS</b> using
                    FPP's built-in self-signed certificate, so login details and cookies are
                    encrypted in transit.</li>
                <li>If you enable the <b>HTTP port</b>, it uses <b>plain HTTP</b>:
                    the login password is submitted as plain form data, and the session is tracked
                    with a cookie that stores the login details in a reversible form. Both can be
                    read by anyone on the network. Do not expose the HTTP port to the public
                    internet &mdash; put it behind a VPN or a TLS reverse proxy for remote access.
                    To force HTTPS only, uncheck <b>Enable HTTP port</b>.</li>
                <li>This plugin provides its own password protection. If FPP's built-in
                    <b>UI Password</b> is also switched on, you may be asked for a second
                    password. The simplest fix is to turn FPP's built-in UI password off
                    (Status/Control &rarr; FPP Settings &rarr; UI tab), or enter FPP's own
                    admin credentials when prompted.</li>
            </ul>

            <hr>

            <h3>Troubleshooting</h3>

            <h4>The external port is not reachable</h4>
            <ul>
                <li>Check the <b>Status</b> tab: the <b>Apache vhost enabled</b> and <b>port listening</b>
                    indicators should both be green.</li>
                <li>The <b>Public Accessibility (External URL)</b> section on the <b>Status</b> tab lists the
                    <b>Internal URL</b> of each enabled port &mdash; that is the address to browse to on your
                    LAN. To make it reachable from the public internet, forward/publish that port on your router.</li>
                <li>If only the <b>HTTPS port</b> is enabled, browse to <code>https://&lt;fpp-ip&gt;:8443/</code>,
                    not <code>:8080</code>.</li>
                <li>Make sure the port isn't already in use by another service on your FPP.</li>
                <li>If you changed the port, remember to browse to the <b>new</b> address.</li>
            </ul>

            <h4>Getting a 503 / Bad Gateway</h4>
            <p>
                FPP's own web server can't be reached. Confirm the <b>backend port</b> in the
                Config tab matches where FPP actually serves its UI (normally <code>80</code>),
                then click out of that field to re-apply.
            </p>

            <h4>Getting sent back to the login page even with the right password</h4>
            <ul>
                <li>Set the user's password again in the <b>Users</b> tab (add the user or use
                    <b>Change Password</b>).</li>
                <li>Check the <b>Pages</b> tab: if the saved login page is missing the required
                    <code>httpd_username</code> / <code>httpd_password</code> form fields, logging in
                    cannot work.</li>
            </ul>

            <h4>Asked for a password again after logging in</h4>
            <p>
                This plugin now uses a <b>login page + session cookie</b> instead of HTTP Basic Auth,
                so the old &quot;prompts forever&quot; loop is gone. If you are still prompted for a
                password after signing in, it is <b>FPP's built-in UI password</b> (Status/Control
                &rarr; FPP Settings &rarr; UI tab).
            </p>
            <p>
                The cleanest fix is to <b>turn off FPP's built-in UI password</b> &mdash; this plugin
                provides its own, so FPP's is no longer needed: go to <b>Status/Control &rarr; FPP
                Settings &rarr; UI tab &rarr; set &quot;Enable UI password&quot; to No</b>. No need to re-save
                anything in this plugin.
            </p>
            <p>
                Alternatively, when you are prompted a second time on the external port, enter FPP's
                own admin credentials (username <code>admin</code> and the password you set for FPP's
                UI) and you should get through.
            </p>

            <h4>I logged in but I don't see the plugin's Logs tab</h4>
            <p>
                The <b>Logs</b> tab is hidden unless FPP's interface is set to <b>Advanced</b> or
                higher (Status/Control &rarr; FPP Settings &rarr; UI tab &rarr; User Interface Level).
            </p>
        </div>
    </fieldset>
</div>

<?php include __DIR__ . '/footer.inc'; ?>
