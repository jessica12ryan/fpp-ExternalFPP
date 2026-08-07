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
                    Adds a <b>second, password-protected web address</b> that serves the same FPP
                    web UI. The normal UI on port 80 is left completely unchanged.
                </p>

                <h4>Features</h4>
                <ul>
                    <li>Serves FPP's web UI on an extra port of your choice</li>
                    <li>Protects that port with a username + password login page</li>
                    <li>Shows the <b>Internal URL</b> of each enabled port under
                        <b>Public Accessibility (External URL)</b> on the Status and Config tabs</li>
                    <li>Optional <b>custom public ports</b> when your router forwards a different
                        port than the plugin listens on</li>
                    <li>No extra packages or daemons to install &mdash; it uses FPP's own web server</li>
                    <li>Survives reboots (the extra port comes up when FPP starts)</li>
                    <li>Supports multiple users (add / delete / change password from the UI)</li>
                    <li>One-click enable / disable / test from the UI</li>
                    <li>Editable login and password pages</li>
                    <li>Forced password change on next login for selected users</li>
                </ul>

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
