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
                <li>Enter a <b>Username</b> and <b>Password</b></li>
                <li>Click <b>Save &amp; Apply</b></li>
                <li>Browse to <code>http://&lt;fpp-ip&gt;:8080/</code> &mdash; you will be asked to log in</li>
            </ol>

            <hr>

            <h3>Accessing the Protected UI</h3>
            <p>
                When you open the extra port, the browser shows a login dialog.
                Enter the configured username/password to reach the FPP web UI.
                Requests without valid credentials are answered with
                <code>401 Unauthorized</code>.
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
                <li>The extra port uses <b>plain HTTP</b> with HTTP Basic Auth. Do not expose it
                    directly to the public internet. Put it behind a VPN or a TLS reverse proxy
                    for remote access.</li>
                <li>Basic Auth credentials are sent Base64-encoded, not encrypted.</li>
                <li>If FPP's built-in <b>UI Password</b> is also configured, requests through this
                    port may be challenged by that password too, depending on FPP's Apache auth
                    configuration.</li>
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

            <h4>Getting 401 even with the right password</h4>
            <p>
                The password may have been changed without re-applying, or the password file may have
                been overwritten. Re-enter the password in the Config tab and click <b>Save &amp; Apply</b>.
            </p>

            <h4>Logged in, but the browser keeps asking for a password again</h4>
            <p>
                This is the classic <b>double Basic Auth</b> symptom. It happens when <b>FPP's built-in
                UI password</b> (Status/Control &rarr; FPP Settings &rarr; UI tab) is also enabled. FPP's
                own Apache then adds a second password check (realm <em>"Falcon Player"</em>) on the
                regular port 80, and the browser cannot satisfy both layers at once, so it prompts
                repeatedly.
            </p>
            <p>
                On a standard FPP this plugin normally avoids the clash because the extra port
                proxies through the FPP itself, which FPP exempts from its own password. If you still
                see the loop, the cleanest fix is to <b>turn off FPP's built-in UI password</b> &mdash;
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
