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

// Load the shared backend helpers (efppPageCustomized/efppNormalizePageText,
// constants for page/template paths). tabs.inc requires api.php too, but only
// when efppSessionIsUser is undefined; require it up front so the badge state
// below can be computed before the tab bar is rendered.
if (!function_exists('efppPageCustomized')) {
	require_once __DIR__ . '/api.php';
}

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
$deniedPageContent = efppPageContent($pluginDir, '/www/access-denied.html', '/templates/access-denied.html');

// Whether each page differs from its bundled template. Computed server-side
// (direct file comparison) so the badge reflects the saved state, and is not
// thrown off by HTML entity/whitespace round-tripping in the textarea.
// Reuses efppPageCustomized/efppNormalizePageText from api.php (loaded via
// tabs.inc below), which compare full file paths against full template paths.
$loginCustom = efppPageCustomized($pluginDir . '/www/login.html', $pluginDir . '/templates/login.html');
$changeCustom = efppPageCustomized($pluginDir . '/www/change-password.html', $pluginDir . '/templates/change-password.html');
$deniedCustom = efppPageCustomized($pluginDir . '/www/access-denied.html', $pluginDir . '/templates/access-denied.html');
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
        <input type="button" class="buttons efpp-tab" value="Access Denied Page" data-page="denied" onclick="efppPages.switchTo('denied');">
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
                    This is the page visitors land on when they open the external port and are not
                    signed in. It lets them sign in with the credentials configured in the
                    <b>Users</b> tab so they can continue to the FPP web UI.
                </p>

                <p>Minimal working code:</p>
                <pre>&lt;!DOCTYPE html&gt;
&lt;html lang="en"&gt;
&lt;head&gt;
&lt;meta charset="utf-8"&gt;
&lt;title&gt;External FPP Web Access&lt;/title&gt;
&lt;/head&gt;
&lt;body&gt;
  &lt;form method="post" action="/"&gt;
    &lt;input type="text" name="httpd_username" placeholder="Username" required&gt;
    &lt;input type="password" name="httpd_password" placeholder="Password" required&gt;
    &lt;button type="submit"&gt;Sign In&lt;/button&gt;
  &lt;/form&gt;
&lt;/body&gt;
&lt;/html&gt;</pre>

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
                <p>Minimal working code:</p>
                <pre>&lt;!DOCTYPE html&gt;
&lt;html lang="en"&gt;
&lt;head&gt;
&lt;meta charset="utf-8"&gt;
&lt;title&gt;Change Password&lt;/title&gt;
&lt;/head&gt;
&lt;body&gt;
  &lt;input type="password" id="pw1" placeholder="New password"&gt;
  &lt;input type="password" id="pw2" placeholder="Confirm new password"&gt;
  &lt;button id="save"&gt;Change Password&lt;/button&gt;
  &lt;script&gt;
    fetch("/api/plugin/fpp-ExternalFPP/session-user")
      .then(r =&gt; r.json())
      .then(s =&gt; { if (s.success &amp;&amp; !s.must_change) location.replace("/"); });
    document.getElementById("save").onclick = function () {
      fetch("/api/plugin/fpp-ExternalFPP/change-my-password", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ password: pw1.value, password_confirm: pw2.value })
      }).then(r =&gt; r.json())
        .then(d =&gt; d.success ? location.replace("/") : alert((d.errors || []).join(" ")));
    };
  &lt;/script&gt;
&lt;/body&gt;
&lt;/html&gt;</pre>

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

    <div id="efpp_page_denied" class="efpp-page">
        <fieldset class="border p-3">
            <legend>Access Denied Page <span id="efpp_badge_denied" class="efpp-custom-badge"></span></legend>
            <div class="p-3">
                <p>
                    This is the page a visitor sees when they are signed in but their account is
                    <b>not an Admin</b> and they try to open an admin-only page such as
                    <b>settings.php</b> or <b>networkconfig.php</b>. Customize it so blocked users
                    can easily get back to the FPP UI.
                </p>
                <p>
                    <b>Preview:</b> <span id="efpp_preview_denied">loading...</span>
                </p>
                <textarea id="efpp_denied_page" rows="24" style="width:100%;font-family:monospace;font-size:12px;"
                          spellcheck="false"><?php echo htmlspecialchars($deniedPageContent, ENT_QUOTES); ?></textarea>
                <div style="margin-top:6px;">
                    <input type="button" class="buttons" value="Save Access Denied Page" onclick="efppPages.save('denied');">
                    <input type="button" class="buttons" value="Reset to Default" onclick="efppPages.reset('denied');">
                </div>
                <div id="efpp_result_denied" style="margin-top:6px;"></div>
            </div>
        </fieldset>

        <fieldset class="border p-3">
            <legend>Required Code &mdash; Access Denied Page</legend>
            <div class="p-3">
                <p>Give the visitor a way out &mdash; back to the FPP UI or the page they came from.
                    Everything else (styling, messaging, branding) is optional.</p>
                <p>Minimal working code:</p>
                <pre>&lt;!DOCTYPE html&gt;
&lt;html lang="en"&gt;
&lt;head&gt;
&lt;meta charset="utf-8"&gt;
&lt;title&gt;Access Denied&lt;/title&gt;
&lt;/head&gt;
&lt;body&gt;
  &lt;p&gt;Your account does not have permission to open this page.&lt;/p&gt;
  &lt;a href="/"&gt;Home&lt;/a&gt;
  &lt;button type="button" onclick="history.back()"&gt;Go Back&lt;/button&gt;
&lt;/body&gt;
&lt;/html&gt;</pre>
            </div>
        </fieldset>
    </div>
</div>

<script>
// Initial customized state, computed server-side by comparing each page file
// against its bundled template. The badge is updated only when a page is saved
// or reset (see efppPages.save / efppPages.reset), so it reflects the saved
// state rather than live edits in the textarea.
var EFPP_CUSTOM = {
    login: <?php echo (int)$loginCustom; ?>,
    change: <?php echo (int)$changeCustom; ?>,
    denied: <?php echo (int)$deniedCustom; ?>
};

// Per-page editor ids and API endpoints.
function efppPageFields(page) {
    return {
        ta: '#efpp_' + page + '_page',
        badge: '#efpp_badge_' + page
    };
}
function efppPageEndpoints(page) {
    var ep = page === 'login' ? 'login-page' : page === 'change' ? 'change-password-page' : 'access-denied-page';
    return {
        save: 'save-' + ep,
        reset: 'reset-' + ep,
        get: ep
    };
}

// Set the badge from a server-computed customized flag (1 = customized).
function efppSetBadge(page, custom) {
    var f = efppPageFields(page);
    custom = custom ? true : false;
    EFPP_CUSTOM[page] = custom ? 1 : 0;
    $(f.badge)
        .text(custom ? 'Customized' : 'Default')
        .attr('class', 'efpp-custom-badge ' + (custom ? 'efpp-custom' : 'efpp-default'));
}

function efppCustomBadge(page) {
    efppSetBadge(page, EFPP_CUSTOM[page] === 1);
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
        var f = efppPageFields(page);
        var ep = efppPageEndpoints(page).save;
        $('#efpp_result_' + page).html('<span class="text-warning">Saving...</span>');
        $.ajax({
            url: efppPages.apiBase + '/' + ep,
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ content: $(f.ta).val() }),
            dataType: 'json',
            success: function(data) {
                efppPages.renderResult(page, data);
                if (data.customized !== undefined) {
                    efppSetBadge(page, data.customized);
                } else {
                    efppCustomBadge(page);
                }
            },
            error: function(xhr) {
                efppPages.renderResult(page, { success: false, errors: ['Could not reach the plugin API. Check the FPP web server logs.'] });
            }
        });
    },

    reset: function(page) {
        var label = page === 'login' ? 'login page' : page === 'change' ? 'change password page' : 'access denied page';
        if (!confirm('Replace the current ' + label + ' with the default template? Your customizations will be lost.')) {
            return;
        }
        var ep = efppPageEndpoints(page);
        var f = efppPageFields(page);
        $('#efpp_result_' + page).html('<span class="text-warning">Resetting...</span>');
        $.ajax({
            url: efppPages.apiBase + '/' + ep.reset,
            type: 'POST',
            contentType: 'application/json',
            data: '{}',
            dataType: 'json',
            success: function(data) {
                efppPages.renderResult(page, data);
                if (data.customized !== undefined) {
                    efppSetBadge(page, data.customized);
                } else {
                    efppCustomBadge(page);
                }
                if (data.success) {
                    $.ajax({
                        url: efppPages.apiBase + '/' + ep.get,
                        type: 'GET',
                        dataType: 'json',
                        success: function(d) {
                            if (d.success) { $(f.ta).val(d.content); }
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
            $('#efpp_preview_denied').html('<a href="' + base + '/access-denied.html" target="_blank">' + base + '/access-denied.html</a>');
        } else {
            $('#efpp_preview_login').html('<span class="text-secondary">External access is disabled (check "Enable HTTP port" or "Enable HTTPS port" in the Config tab)</span>');
            $('#efpp_preview_change').html('<span class="text-secondary">External access is disabled (check "Enable HTTP port" or "Enable HTTPS port" in the Config tab)</span>');
            $('#efpp_preview_denied').html('<span class="text-secondary">External access is disabled (check "Enable HTTP port" or "Enable HTTPS port" in the Config tab)</span>');
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
    efppCustomBadge('denied');

    $.ajax({
        url: efppPages.apiBase + '/status',
        type: 'GET',
        dataType: 'json',
        success: efppPages.renderPreview
    });
});
</script>

<?php include __DIR__ . '/footer.inc'; ?>
