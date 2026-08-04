<?php
/**
 * #############################################################
 * ## External FPP Web Access (fpp-ExternalFPP)               ##
 * ## Author: jessica12ryan                                ##
 * ## URL: https://github.com/jessica12ryan/fpp-ExternalFPP##
 * #############################################################
 * ## loginpage.php                                           ##
 * ## Editor for the login landing page shown on the          ##
 * ## external port.                                          ##
 * #############################################################
 */

$pluginDir = __DIR__;
$loginPageFile = $pluginDir . '/www/login.html';

$content = '';
if (file_exists($loginPageFile)) {
    $content = (string)@file_get_contents($loginPageFile);
} else {
    $tpl = $pluginDir . '/templates/login.html';
    if (file_exists($tpl)) {
        $content = (string)@file_get_contents($tpl);
    }
}
?>

<?php include __DIR__ . '/tabs.inc'; ?>

<div style="margin:0 auto;">
    <fieldset class="border p-3">
        <legend>Login Page</legend>
        <div class="p-3">
            <p>
                This is the page visitors see when they open the external port and are not
                signed in. Edit the HTML below to customize it, then click
                <b>Save Login Page</b>. The change is applied immediately &mdash; Apache
                reads the file on every request, so no apply step is needed.
            </p>
            <p>
                <b>Preview:</b> <span id="efpp_preview">loading...</span>
            </p>
            <textarea id="efpp_login_page" rows="24" style="width:100%;font-family:monospace;font-size:12px;"
                      spellcheck="false"><?php echo htmlspecialchars($content, ENT_QUOTES); ?></textarea>
            <div style="margin-top:6px;">
                <input type="button" class="buttons" value="Save Login Page" onclick="efppLp.save();">
                <input type="button" class="buttons" value="Reset to Default" onclick="efppLp.reset();">
            </div>
            <div id="efpp_lp_result" style="margin-top:6px;"></div>
        </div>
    </fieldset>

    <br />

    <fieldset class="border p-3">
        <legend>Required Code</legend>
        <div class="p-3">
            <p>
                Apache's form login handler (mod_auth_form) reads the page when a visitor is not
                signed in. The page <b>must</b> contain all of the following, otherwise logging
                in will not work:
            </p>
            <ul>
                <li>A <code>&lt;form&gt;</code> element that uses <code>method="post"</code> and
                    posts to the protected port, e.g. <code>action="/"</code>.</li>
                <li>An <code>&lt;input name="httpd_username"&gt;</code> field for the username.</li>
                <li>An <code>&lt;input name="httpd_password"&gt;</code> field for the password.</li>
                <li>Everything else (styling, branding, extra fields) is optional.</li>
            </ul>

            <p>Minimal working form:</p>
            <pre>&lt;form method="post" action="/"&gt;
    &lt;input type="text"     name="httpd_username" placeholder="Username" required&gt;
    &lt;input type="password" name="httpd_password" placeholder="Password" required&gt;
    &lt;button type="submit"&gt;Sign In&lt;/button&gt;
&lt;/form&gt;</pre>

            <p>Notes:</p>
            <ul>
                <li>The username/password submitted here is checked against the users configured in the
                    <b>Users</b> tab.</li>
                <li>After a successful login the visitor lands on <code>/</code> (the FPP web UI).</li>
                <li>To sign out, visit <code>/logout</code> in the browser.</li>
                <li>When the fields above are missing, saving shows a warning so you can fix the
                    page before someone tries to use it.</li>
            </ul>
        </div>
    </fieldset>
</div>

<script>
var efppLp = {
    apiBase: 'api/plugin/fpp-ExternalFPP',

    renderResult: function(data) {
        var html = '';
        if (data.errors && data.errors.length > 0) {
            html += '<span class="text-danger"><b>Errors:</b><br>' + data.errors.map(escHtml).join('<br>') + '</span>';
        }
        if (data.warnings && data.warnings.length > 0) {
            html += '<span class="text-warning"><b>Warnings:</b><br>' + data.warnings.map(escHtml).join('<br>') + '</span>';
        }
        if (data.messages && data.messages.length > 0) {
            html += '<span class="text-success"><b>' + (data.success ? 'OK' : 'Notes') + ':</b><br>' + data.messages.map(escHtml).join('<br>') + '</span>';
        }
        if (data.success === false && !data.errors) {
            html += '<span class="text-danger">Unknown error occurred.</span>';
        }
        $('#efpp_lp_result').html(html);
    },

    save: function() {
        $('#efpp_lp_result').html('<span class="text-warning">Saving...</span>');
        $.ajax({
            url: efppLp.apiBase + '/save-login-page',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ content: $('#efpp_login_page').val() }),
            dataType: 'json',
            success: function(data) {
                efppLp.renderResult(data);
            },
            error: function(xhr) {
                efppLp.renderResult({ success: false, errors: ['Could not reach the plugin API. Check the FPP web server logs.'] });
            }
        });
    },

    reset: function() {
        if (!confirm('Replace the current login page with the default template? Your customizations will be lost.')) {
            return;
        }
        $('#efpp_lp_result').html('<span class="text-warning">Resetting...</span>');
        $.ajax({
            url: efppLp.apiBase + '/reset-login-page',
            type: 'POST',
            contentType: 'application/json',
            data: '{}',
            dataType: 'json',
            success: function(data) {
                efppLp.renderResult(data);
                if (data.success) {
                    efppLp.reload();
                }
            },
            error: function(xhr) {
                efppLp.renderResult({ success: false, errors: ['Could not reach the plugin API. Check the FPP web server logs.'] });
            }
        });
    },

    reload: function() {
        $.ajax({
            url: efppLp.apiBase + '/login-page',
            type: 'GET',
            dataType: 'json',
            success: function(data) {
                if (data.success) {
                    $('#efpp_login_page').val(data.content);
                }
            }
        });
    },

    renderPreview: function(s) {
        if (s.enabled) {
            var url = 'http://' + window.location.hostname + ':' + s.port + '/login.html';
            $('#efpp_preview').html('<a href="' + url + '" target="_blank">' + url + '</a>');
        } else {
            $('#efpp_preview').html('<span class="text-secondary">External port is disabled (enable it in the Config tab)</span>');
        }
    }
};

$(document).ready(function() {
    $.ajax({
        url: efppLp.apiBase + '/status',
        type: 'GET',
        dataType: 'json',
        success: efppLp.renderPreview
    });
});
</script>

<?php include __DIR__ . '/footer.inc'; ?>
