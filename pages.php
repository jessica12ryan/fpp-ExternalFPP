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

// The bundled originals, embedded into the page so the editor can flag when the
// live page differs from them.
$loginTpl = (string)@file_get_contents($pluginDir . '/templates/login.html');
$changeTpl = (string)@file_get_contents($pluginDir . '/templates/change-password.html');
?>

<?php include __DIR__ . '/tabs.inc'; ?>

<style>
    .efpp-subtabs { margin-bottom: 0; }
    .efpp-subtabs .buttons { border-bottom: none; }
    .efpp-subtabs .buttons.active { background: #f2a21c; color: #1c1e21; }
    .efpp-page { display: none; }
    .efpp-page.active { display: block; }
    .efpp-custom-badge {
        margin-left: 8px;
        padding: 2px 9px;
        border-radius: 10px;
        font-size: 11px;
        font-weight: 600;
        vertical-align: middle;
        display: inline-block;
    }
    .efpp-custom-badge.efpp-custom { background: #8a6d1a; color: #ffd166; }
    .efpp-custom-badge.efpp-default { background: #3a3f46; color: #9aa0a6; }
</style>

<div style="margin:0 auto;">
    <div class="efpp-subtabs">
        <input type="button" class="buttons efpp-tab active" value="Login Page" data-page="login" onclick="efppPages.switchTo('login');">
        <input type="button" class="buttons efpp-tab" value="Change Password Page" data-page="change" onclick="efppPages.switchTo('change');">
    </div>

    <div id="efpp_page_login" class="efpp-page active">
        <fieldset class="border p-3">
            <legend>Login Page <span id="efpp_badge_login" class="efpp-custom-badge"></span></legend>
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
                    <li>After a successful login the visitor is sent straight to the FPP web UI,
                        unless their account is marked <b>must change password at next login</b>, in
                        which case they are taken to the Change Password Page and held there.</li>
                    <li>To sign out, visit <code>/logout</code> in the browser.</li>
                    <li>When the fields above are missing, saving shows a warning so you can fix the
                        page before someone tries to use it.</li>
                </ul>
            </div>
        </fieldset>
    </div>

    <div id="efpp_page_change" class="efpp-page">
        <fieldset class="border p-3">
            <legend>Change Password Page <span id="efpp_badge_change" class="efpp-custom-badge"></span></legend>
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
&lt;button id="save"&gt;Change Password&lt;/button&gt;
&lt;script&gt;
// Users who do not have to change their password are sent on to the FPP UI.
fetch("/api/plugin/fpp-ExternalFPP/session-user")
  .then(function (r) { return r.json(); })
  .then(function (s) {
    if (s.success &amp;&amp; !s.must_change) { window.location.replace("/"); }
  });

document.getElementById("save").addEventListener("click", function () {
  fetch("/api/plugin/fpp-ExternalFPP/change-my-password", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({
      password: document.getElementById("pw1").value,
      password_confirm: document.getElementById("pw2").value
    })
  }).then(function (r) { return r.json(); }).then(function (d) {
    if (d.success) {
      // The API re-issues the login session cookie with the new password, so
      // the visitor stays signed in and continues straight into the FPP UI.
      window.location.replace("/");
    } else {
      alert((d.errors || []).join(" "));
    }
  });
});
&lt;/script&gt;</pre>

                <p>Notes:</p>
                <ul>
                    <li>Changing the password also refreshes the login session: the API re-issues the
                        session cookie with the new password, so the visitor stays signed in and the
                        redirect to <code>/</code> goes straight into the FPP UI (no login prompt).</li>
                    <li>The password must be at least 6 characters long and both fields must match.
                        Reusing the current password is allowed.</li>
                    <li>The page should call <code>session-user</code> and forward to <code>/</code> when
                        <code>must_change</code> is false, otherwise every login would be forced through a
                        password change.</li>
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
// Bundled originals, embedded safely for JavaScript. JSON_HEX_TAG escapes the
// angle brackets as \u003C/\u003E (which decode back to the exact original), so
// a closing script tag inside a template can't break out of this script block
// while the comparison in efppCustomBadge still sees the true template text.
// NOTE: never write a literal closing-script sequence in this comment, since
// the HTML parser would end the script element there and swallow the rest.
var EFPP_TPL = {
    login: <?php echo json_encode($loginTpl, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES); ?>,
    change: <?php echo json_encode($changeTpl, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES); ?>
};

function efppCustomBadge(page) {
    var ta = page === 'login' ? '#efpp_login_page' : '#efpp_change_pw_page';
    var badge = page === 'login' ? '#efpp_badge_login' : '#efpp_badge_change';
    var current = String($(ta).val()).replace(/\r\n/g, "\n").trim();
    var original = String(EFPP_TPL[page]).replace(/\r\n/g, "\n").trim();
    var custom = current !== original;
    $(badge)
        .text(custom ? 'Customized' : 'Default')
        .attr('class', 'efpp-custom-badge ' + (custom ? 'efpp-custom' : 'efpp-default'));
}

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
                efppCustomBadge(page);
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
                            efppCustomBadge(page);
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
        if (s.enabled) {
            var scheme = s.enable_https ? 'https' : 'http';
            var uport = s.enable_https ? s.https_port : s.port;
            var base = scheme + '://' + window.location.hostname + ':' + uport;
            $('#efpp_preview_login').html('<a href="' + base + '/login.html" target="_blank">' + base + '/login.html</a>');
            $('#efpp_preview_change').html('<a href="' + base + '/change-password.html" target="_blank">' + base + '/change-password.html</a>');
        } else {
            $('#efpp_preview_login').html('<span class="text-secondary">External access is disabled (check "Enable HTTP port" or "Enable HTTPS port" in the Config tab)</span>');
            $('#efpp_preview_change').html('<span class="text-secondary">External access is disabled (check "Enable HTTP port" or "Enable HTTPS port" in the Config tab)</span>');
        }
    }
};

function escHtml(s) {
    if (s == null) return '';
    return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

$(document).ready(function() {
    efppCustomBadge('login');
    efppCustomBadge('change');
    $('#efpp_login_page').on('input', function() { efppCustomBadge('login'); });
    $('#efpp_change_pw_page').on('input', function() { efppCustomBadge('change'); });

    $.ajax({
        url: efppPages.apiBase + '/status',
        type: 'GET',
        dataType: 'json',
        success: efppPages.renderPreview
    });
});
</script>

<?php include __DIR__ . '/footer.inc'; ?>
