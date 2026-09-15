<h3>Pages &mdash; Custom HTML Pages</h3>
<p>Edit the three HTML pages Apache serves on the external port. Files live in <code>www/</code> (saved) with defaults in <code>templates/</code>; Apache reads them on every request so changes apply immediately.</p>

<h4>Sub-Tabs</h4>
<ul>
    <li><b>Login Page</b> (<code>www/login.html</code> &rarr; <code>templates/login.html</code>) &mdash; Shown to visitors who are not signed in. Hold the three subtabs: Login, Change Password, Access Denied.</li>
    <li><b>Change Password Page</b> (<code>www/change-password.html</code>) &mdash; Visitors land here right after signing in when marked <b>must change password at next login</b>; others are forwarded to <code>/</code>.</li>
    <li><b>Access Denied Page</b> (<code>www/access-denied.html</code>) &mdash; Shown to signed-in <b>User</b>-role accounts that try to open admin-only pages (e.g. <code>settings.php</code>, <code>networkconfig.php</code>).</li>
</ul>

<h4>Editor (each sub-tab)</h4>
<ul>
    <li>Monospace <code>&lt;textarea&gt;</code> pre-filled from <code>www/</code> or the template fallback. Badge shows <span style="background:#8a6d1a;color:#ffd166;padding:2px 9px;border-radius:10px;font-size:11px">Customized</span> vs <span style="background:#3a3f46;color:#9aa0a6;padding:2px 9px;border-radius:10px;font-size:11px">Default</span> &mdash; computed server-side via <code>efppPageCustomized()</code> / <code>efppNormalizePageText()</code> and seeded in <code>EFPP_CUSTOM</code>.</li>
    <li><b>Preview:</b> link to <code>&lt;scheme&gt;://&lt;host&gt;:&lt;port&gt;/login.html</code> (and the other two URLs) when external access is enabled, otherwise shows disabled notice. Built from <code>GET api/plugin/fpp-ExternalFPP/status</code>.</li>
    <li><b>Save &hellip; Page</b> &mdash; <code>POST api/plugin/fpp-ExternalFPP/save-&lt;page&gt;-page</code> with <code>{content}</code>; updates the badge from <code>data.customized</code> and shows <b>Errors</b>/<b>Warnings</b>/<b>OK</b> from the validator.</li>
    <li><b>Reset to Default</b> &mdash; Confirms, then <code>POST api/plugin/fpp-ExternalFPP/reset-&lt;page&gt;-page</code> and re-fetches via <code>GET api/plugin/fpp-ExternalFPP/&lt;page&gt;-page</code>. Admin-only.</li>
</ul>

<h4>Login Page &mdash; Required Code</h4>
<p>Minimal working code:</p>
<pre>&lt;form method="post" action="/"&gt;
  &lt;input type="text" name="httpd_username" placeholder="Username" required&gt;
  &lt;input type="password" name="httpd_password" placeholder="Password" required&gt;
  &lt;button type="submit"&gt;Sign In&lt;/button&gt;
&lt;/form&gt;</pre>
<ul>
    <li>Must have <code>method="post"</code>, an <code>action</code> posting to a protected URL (e.g. <code>/</code>), and inputs <code>httpd_username</code> + <code>httpd_password</code>. Missing fields produce a warning on save.</li>
    <li>On success the visitor goes to the FPP UI, unless <b>must_change</b> holds them on the Change Password Page. Sign out via <code>/logout</code>.</li>
</ul>

<h4>Change Password Page &mdash; Required Code</h4>
<p>Must ask for the new password twice and POST it to <code>/api/plugin/fpp-ExternalFPP/change-my-password</code> as JSON <code>{password, password_confirm}</code>. The username is taken from the Apache session cookie (<code>fppefpp</code>), never typed. Should also call <code>GET /api/plugin/fpp-ExternalFPP/session-user</code> and redirect to <code>/</code> when <code>must_change</code> is false. Password &ge; 6 chars, both fields must match. The API re-issues the session cookie so the redirect stays signed in.</p>

<h4>Access Denied Page &mdash; Required Code</h4>
<p>Give the visitor a way out &mdash; a <code>&lt;a href="/"&gt;Home&lt;/a&gt;</code> link and a <code>history.back()</code> button. Everything else is optional.</p>
<pre>&lt;p&gt;Your account does not have permission to open this page.&lt;/p&gt;
&lt;a href="/"&gt;Home&lt;/a&gt;
&lt;button onclick="history.back()"&gt;Go Back&lt;/button&gt;</pre>
<ul>
    <li>Validator warns if <code>href="/"</code> or <code>history.back()</code> is missing.</li>
</ul>

<h4>Permissions</h4>
<p>Saving or resetting any page requires an <b>Admin</b> account when the request comes through the external port; <b>User</b>-role sessions receive <code>This action requires an Administrator account.</code></p>
