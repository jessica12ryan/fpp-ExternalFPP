<?php
/**
 * #############################################################
 * ## External FPP Web Access (fpp-ExternalFPP)               ##
 * ## Author: jessica12ryan                                ##
 * ## URL: https://github.com/jessica12ryan/fpp-ExternalFPP##
 * #############################################################
 * ## pages.php                                              ##
 * ## Editor for the pages Apache serves on the external      ##
 * ## port: the login landing page and the change-password    ##
 * ## page.                                                   ##
 * #############################################################
 */

$pluginDir = __DIR__;

function efppPageContent($pluginDir, $file, $template) {
    $path = $pluginDir . $file;
    if (file_exists($path)) {
        $c = (string)@file_get_contents($path);
        if ($c !== '') {
            return $c;
        }
    }
    $tpl = $pluginDir . $template;
    if (file_exists($tpl)) {
        return (string)@file_get_contents($tpl);
    }
    return '';
}

$loginPageContent = efppPageContent($pluginDir, '/www/login.html', '/templates/login.html');
$changePwContent = efppPageContent($pluginDir, '/www/change-password.html', '/templates/change-password.html');
?>

<?php include __DIR__ . '/tabs.inc'; ?>

<style>
    .efpp-subtabs { margin-bottom: 0; }
    .efpp-subtabs .buttons { border-bottom: none; }
    .efpp-subtabs .buttons.active { background: #f2a21c; color: #1c1e21; }
    .efpp-page { display: none; }
    .efpp-page.active { display: block; }
</style>

<div style="margin:0 auto;">
    <div class="efpp-subtabs">
        <input type="button" class="buttons efpp-tab active" value="Login Page" data-page="login" onclick="efppPages.switchTo('login');">
        <input type="button" class="buttons efpp-tab" value="Change Password Page" data-page="change" onclick="efppPages.switchTo('change');">
    </div>

    <div id="efpp_page_login" class="efpp-page active">
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
                    <b>Preview:</b> <span id="efpp_preview_login">loading...</span>
                </p>
                <textarea id="efpp_login_page" rows="24" style="width:100%;font-family:monospace;font-size:12px;"
                          spellcheck="false"><?php echo htmlspecialchars($loginPageContent, ENT_QUOTES); ?></textarea>
                <div style="margin-top:6px;">
                    <input type="button" class="buttons" value="Save Login Page" onclick="efppPages.save('login');">
                    <input type="button" class="buttons" value="Reset to Default" onclick="efppPages.reset('login');">
                </div>
                <div id="efpp_result_login" style="margin-top:6px;"></div>
            </div>
        </fieldset>

        <fieldset class="border p-3">
            <legend>Required Code &mdash; Login Page</legend>
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
                    <li>After a successful login the visitor lands on the <b>Change Password Page</b>, which
                        forwards to the FPP web UI unless a new password is required.</li>
                    <li>To sign out, visit <code>/logout</code> in the browser.</li>
                    <li>When the fields above are missing, saving shows a warning so you can fix the
                        page before someone tries to use it.</li>
                </ul>
            </div>
        </fieldset>
    </div>

    <div id="efpp_page_change" class="efpp-page">
        <fieldset class="border p-3">
            <legend>Change Password Page</legend>
            <div class="p-3">
                <p>
                    Visitors land on this page right after signing in on the external port. Users that an
                    administrator marked as <b>must change password at next login</b> are held here until
                    they set a new password; everyone else is forwarded straight to the FPP web UI.
                </p>
                <p>
                    <b>Preview:</b> <span id="efpp_preview_change">loading...</span>
                </p>
                <textarea id="efpp_change_pw_page" rows="24" style="width:100%;font-family:monospace;font-size:12px;"
                          spellcheck="false"><?php echo htmlspecialchars($changePwContent, ENT_QUOTES); ?></textarea>
                <div style="margin-top:6px;">
                    <input type="button" class="buttons" value="Save Change Password Page" onclick="efppPages.save('change');">
                    <input type="button" class="buttons" value="Reset to Default" onclick="efppPages.reset('change');">
                </div>
                <div id="efpp_result_change" style="margin-top:6px;"></div>
            </div>
        </fieldset>

        <fieldset class="border p-3">
            <legend>Required Code &mdash; Change Password Page</legend>
            <div class="p-3">
                <p>
                    The page must ask for the new password twice and post it to the plugin API. The
                    username is taken from the signed-in Apache session, never typed by the visitor.
                </p>
                <p>Minimal working form:</p>
                <pre>&lt;input type="password" id="pw1" placeholder="New password"&gt;
&lt;input type="password" id="pw2" placeholder="Confirm new password"&gt;
&lt;button onclick="submit()"&gt;Change Password&lt;/button&gt;
&lt;script&gt;
function submit() {
  fetch("/api/plugin/fpp-ExternalFPP/change-my-password", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({
      password: document.getElementById("pw1").value,
      password_confirm: document.getElementById("pw2").value
    })
  }).then(function (r) { return r.json(); }).then(function (d) {
    if (d.success) { window.location.href = "/"; }
    else { alert((d.errors || []).join(" ")); }
  });
}
&lt;/script&gt;</pre>

                <p>Notes:</p>
                <ul>
                    <li>The password must be at least 6 characters long and must differ from the current
                        password.</li>
                    <li>Passwords are stored in the plugin's own password file &mdash; FPP's UI password
                        is never touched.</li>
                    <li>If your page does not call the API, or the API rejects the password, the visitor
                        stays on this page and keeps being blocked from the FPP UI.</li>
                </ul>
            </div>
        </fieldset>
    </div>
</div>

<script>
var efppPages = {
    apiBase: 'api/plugin/fpp-ExternalFPP',

    switchTo: function(page) {
        $('.efpp-page').removeClass('active');
        $('#efpp_page_' + page).addClass('active');
        $('.efpp-tab').removeClass('active');
        $('.efpp-tab[data-page="' + page + '"]').addClass('active');
    },

    renderResult: function(page, data) {
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
        $('#efpp_result_' + page).html(html);
    },

    save: function(page) {
        var ta = page === 'login' ? '#efpp_login_page' : '#efpp_change_pw_page';
        var ep = page === 'login' ? 'save-login-page' : 'save-change-password-page';
        $('#efpp_result_' + page).html('<span class="text-warning">Saving...</span>');
        $.ajax({
            url: efppPages.apiBase + '/' + ep,
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ content: $(ta).val() }),
            dataType: 'json',
            success: function(data) {
                efppPages.renderResult(page, data);
            },
            error: function(xhr) {
                efppPages.renderResult(page, { success: false, errors: ['Could not reach the plugin API. Check the FPP web server logs.'] });
            }
        });
    },

    reset: function(page) {
        var label = page === 'login' ? 'login page' : 'change password page';
        if (!confirm('Replace the current ' + label + ' with the default template? Your customizations will be lost.')) {
            return;
        }
        var ep = page === 'login' ? 'reset-login-page' : 'reset-change-password-page';
        var ta = page === 'login' ? '#efpp_login_page' : '#efpp_change_pw_page';
        $('#efpp_result_' + page).html('<span class="text-warning">Resetting...</span>');
        $.ajax({
            url: efppPages.apiBase + '/' + ep,
            type: 'POST',
            contentType: 'application/json',
            data: '{}',
            dataType: 'json',
            success: function(data) {
                efppPages.renderResult(page, data);
                if (data.success) {
                    $.ajax({
                        url: efppPages.apiBase + '/' + (page === 'login' ? 'login-page' : 'change-password-page'),
                        type: 'GET',
                        dataType: 'json',
                        success: function(d) {
                            if (d.success) { $(ta).val(d.content); }
                        }
                    });
                }
            },
            error: function(xhr) {
                efppPages.renderResult(page, { success: false, errors: ['Could not reach the plugin API. Check the FPP web server logs.'] });
            }
        });
    },

    renderPreview: function(s) {
        var preview = 'External port is disabled (enable it in the Config tab)';
        if (s.enabled) {
            var base = 'http://' + window.location.hostname + ':' + s.port;
            preview = '<a href="' + base + '/login.html" target="_blank">' + base + '/login.html</a>'
                + ' &mdash; <a href="' + base + '/change-password.html" target="_blank">' + base + '/change-password.html</a>';
        }
        $('#efpp_preview_login').html(preview);
        $('#efpp_preview_change').html(preview);
    }
};

$(document).ready(function() {
    $.ajax({
        url: efppPages.apiBase + '/status',
        type: 'GET',
        dataType: 'json',
        success: efppPages.renderPreview
    });
});
</script>

<?php include __DIR__ . '/footer.inc'; ?>
