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
                <b>new port</b> (default <code>8080</code>) that serves the exact same UI but first asks
                for a <b>username and password</b>. Nothing on the normal port is changed, so local
                users keep working exactly as before.
            </p>

            <hr>

            <h3>Quick Start</h3>
            <ol>
                <li>Open <b>Content Setup &rarr; External FPP</b></li>
                <li>Set a <b>Listen port</b> (e.g. <code>8080</code>)</li>
                <li>Add at least one <b>user</b> in the <b>Users</b> tab (username + password)</li>
                <li>Click <b>Save &amp; Apply</b>, then use the toggle button to <b>enable</b> the external access</li>
                <li>Browse to <code>http://&lt;fpp-ip&gt;:8080/</code> &mdash; you will be asked to log in</li>
            </ol>

            <hr>

            <h3>Accessing the Protected UI</h3>
            <p>
                When you open the extra port you are taken to a <b>login page</b> (a sign-in
                form styled like FPP). Enter the configured username/password to reach the
                FPP web UI. Requests without a valid session are redirected back to the
                login page.
            </p>

            <hr>

            <h3>Customizing the Login Page</h3>
            <p>
                Open the <b>Login Page</b> tab to edit the HTML of the login page and to see
                the code that is required for logging in to work (the
                <code>&lt;form method="post"&gt;</code> and the <code>httpd_username</code> /
                <code>httpd_password</code> fields). The page is stored in the plugin's
                <code>www/login.html</code> file and is read by Apache on every request, so
                changes take effect as soon as you save them.
            </p>
            <p>
                To sign out, visit <code>/logout</code> in the browser (this removes the session
                cookie and returns you to the login page).
            </p>

            <hr>

            <h3>Changing the Listen Port</h3>
            <p>
                Change the <b>Listen port</b> in the Config tab and click <b>Save &amp; Apply</b>.
                The plugin rewrites the Apache virtual host and reloads Apache automatically.
            </p>

            <hr>

            <h3>Security Considerations</h3>
            <ul>
                <li>The extra port uses <b>plain HTTP</b>. The login password is submitted as
                    plain form data, and the session is tracked with a cookie that stores the
                    login details in a reversible form. Both can be read by anyone on the
                    network. Do not expose this port to the public internet &mdash; put it
                    behind a VPN or a TLS reverse proxy for remote access.</li>
                <li>If FPP's built-in <b>UI Password</b> is also configured, requests through this
                    port may additionally be challenged by that password, depending on FPP's Apache
                    auth configuration.</li>
            </ul>

            <hr>

            <h3>Troubleshooting</h3>

            <h4>The external port is not reachable</h4>
            <ul>
                <li>Check the Status tab: the <b>Apache vhost enabled</b> and <b>port listening</b>
                    indicators should both show green.</li>
                <li>Check the plugin log:
                    <pre>tail -20 /home/fpp/media/logs/plugin-fpp-ExternalFPP.log</pre>
                </li>
                <li>Check Apache:
                    <pre>sudo apachectl -S | grep -i 8080
sudo tail -50 /home/fpp/media/logs/apache2-externalfpp-error.log</pre>
                </li>
                <li>Make sure the port isn't already in use by another service on the FPP.</li>
            </ul>

            <h4>Getting a 503 / Bad Gateway</h4>
            <p>
                The backend FPP web server isn't reachable on <code>127.0.0.1:&lt;backend port&gt;</code>.
                Confirm the backend port in the Config tab matches where FPP actually serves its UI
                (normally <code>80</code>).
            </p>

            <h4>Getting sent back to the login page even with the right password</h4>
            <p>
                The password may have been changed but not written to the password file yet, or the
                password file may have been overwritten. Set the user's password again in the
                <b>Users</b> tab (add the user or use <b>Change Password</b>). Also check the
                <b>Login Page</b> tab: if the saved page is missing the required
                <code>httpd_username</code> / <code>httpd_password</code> form fields, logging in
                cannot work.
            </p>

            <h4>Asked for a password again after logging in</h4>
            <p>
                The plugin now uses a <b>login page + session cookie</b> instead of HTTP Basic Auth,
                so the old &quot;prompts forever&quot; loop is gone. If you are still prompted for a
                password after signing in, it is <b>FPP's built-in UI password</b> (Status/Control
                &rarr; FPP Settings &rarr; UI tab). FPP's own Apache adds that check on the regular
                port 80, and the prompt appears through the proxied pages too.
            </p>
            <p>
                On a standard FPP this plugin normally avoids the clash because the extra port
                proxies through the FPP itself, which FPP exempts from its own password. If you still
                see the prompt, the cleanest fix is to <b>turn off FPP's built-in UI password</b> &mdash;
                this plugin provides its own password, so FPP's is no longer needed:
                <b>Status/Control &rarr; FPP Settings &rarr; UI tab &rarr; enable &quot;Enable UI
                password&quot; = No</b>, then click <b>Save &amp; Apply</b> in this plugin again.
            </p>
            <p>
                Alternatively, when you are prompted a second time on the external port, enter FPP's
                own admin credentials (username <code>admin</code> and the password you set for FPP's
                UI) and you should get through.
            </p>
        </div>
    </fieldset>
</div>

<?php include __DIR__ . '/footer.inc'; ?>
