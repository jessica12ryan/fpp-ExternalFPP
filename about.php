<?php
/**
 * #############################################################
 * ## External FPP Web Access (fpp-ExternalFPP)               ##
 * ## Author: jessica12ryan                                ##
 * ## URL: https://github.com/jessica12ryan/fpp-ExternalFPP##
 * #############################################################
 * ## about.php                                               ##
 * ## About page for the plugin.                              ##
 * #############################################################
 */

$pluginDir = __DIR__;
?>

<?php include __DIR__ . '/tabs.inc'; ?>

<div style="margin:0 auto;"> <br />
    <fieldset class="border p-3">
        <legend>About External FPP Web Access</legend>
        <div class="p-3">
            <div id='credits'>
                <h3 style="margin-top:0;">External FPP Web Access</h3>

                <p>
                    Adds a <b>second, password-protected port</b> that serves the same FPP web UI.
                    The normal UI on port 80 is left completely unchanged.
                </p>

                <h4>Features</h4>
                <ul>
                    <li>Reverse-proxies FPP's web UI to an additional TCP port of your choice</li>
                    <li>Protects that port with HTTP Basic Auth (username + password)</li>
                    <li>Uses FPP's existing Apache web server &mdash; no extra packages or daemons</li>
                    <li>Survives reboots (the extra port comes up at boot)</li>
                    <li>Password hashes written with bcrypt when available</li>
                    <li>One-click enable / disable / test from the UI</li>
                </ul>

                <h4>How It Works</h4>
                <ol>
                    <li>The plugin writes an Apache virtual host that listens on the extra port</li>
                    <li>Every request is checked against the configured username/password
                        (<code>mod_auth_basic</code> + a local <code>.htpasswd</code> file)</li>
                    <li>Authenticated requests are reverse-proxied to the normal FPP web server
                        on <code>127.0.0.1:80</code></li>
                    <li>The <code>Host</code> header is preserved so cookies and sessions work as expected</li>
                </ol>

                <h4>Links</h4>
                <p>
                    <a href="https://github.com/jessica12ryan/fpp-ExternalFPP" target="_blank">GitHub Repository</a><br>
                    <a href="https://github.com/jessica12ryan/fpp-ExternalFPP/issues" target="_blank">Issue Tracker &amp; Feature Requests</a>
                </p>

                <h4>Plugin Info</h4>
                <p>
                    Name: <b>External FPP Web Access</b><br>
                    Author: <b>jessica12ryan</b><br>
                    License: <b>MIT</b><br>
                </p>
            </div>
        </div>
    </fieldset>
</div>

<?php include __DIR__ . '/footer.inc'; ?>
