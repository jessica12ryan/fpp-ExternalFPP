<h3>Users &mdash; External Login Accounts</h3>
<p>Accounts that can sign in on the external (password-protected) port via the <b>Login Page</b>. Each has a username, bcrypt-hashed password, a <b>Role</b> (Admin/User), and an optional <b>must change password at next login</b> flag.</p>

<h4>Users Table</h4>
<ul>
    <li><b>Username</b> &mdash; Unique, case-insensitive; cannot contain <code>:</code>. Shown in bold.</li>
    <li><b>Role</b> &mdash; Inline <code>&lt;select&gt;</code> per row. <b>Admin</b> = full access (can view and change this plugin's dashboard); <b>User</b> = no dashboard access (sees <b>Access Denied</b> on admin-only pages, can still use the rest of FPP). Changing it calls <code>POST api/plugin/fpp-ExternalFPP/set-user-role</code>. You cannot change your own role; the last admin cannot be demoted. Stored as <code>role</code> (<code>admin</code> or <code>user</code>) in <code>config/settings.json</code>; admins are written to <code>config/plugin.fpp-ExternalFPP.groups</code> as <code>efpp-admin</code>.</li>
    <li><b>Status</b> &mdash; <span style="color:#198754">OK</span> or <span style="color:#fd7e14">&#9888; must change password</span> when the flag is set.</li>
    <li><b>Change Password</b> &mdash; Opens the modal (see below). Per-user.</li>
    <li><b>Delete</b> &mdash; Calls <code>POST api/plugin/fpp-ExternalFPP/delete-user</code> after confirmation. Blocked for the last <b>Admin</b> or the last remaining user while external access is enabled &mdash; disable both ports on <b>Config</b> first.</li>
    <li>Polls <code>GET api/plugin/fpp-ExternalFPP/users</code> every 5 s.</li>
</ul>

<h4>Add User Form</h4>
<ul>
    <li><b>Username</b> &mdash; Required. Must be unique (checked server-side).</li>
    <li><b>Password / Confirm password</b> &mdash; Minimum 6 characters, must match. Stored as a bcrypt hash (<code>$2y$</code>) in <code>config/settings.json</code> and in <code>config/plugin.fpp-ExternalFPP.htpasswd</code> (<code>username:hash</code>).</li>
    <li><b>Require password change at next login</b> &mdash; When checked the user is held on the <b>Change Password Page</b> after their next successful login until they set a new password.</li>
    <li><b>Role</b> &mdash; <code>User (no dashboard access)</code> or <code>Admin (full access)</code>. Defaults to <b>Admin</b> until at least one Admin exists (otherwise the plugin could never be enabled), then defaults to <b>User</b>.</li>
    <li><b>Add User</b> &mdash; Calls <code>POST api/plugin/fpp-ExternalFPP/add-user</code>. On success the form is cleared and the role default is recomputed.</li>
</ul>

<h4>Change Password Modal</h4>
<ul>
    <li>Title shows the username being edited. Fields: <b>New password</b>, <b>Confirm new password</b>, and <b>Require password change at next login</b> (pre-checked to match the user's current flag).</li>
    <li><b>Save</b> &mdash; Calls <code>POST api/plugin/fpp-ExternalFPP/set-user-password</code> with <code>username</code>, <code>password</code>, <code>password_confirm</code>, <code>must_change</code>. Same 6-char / match validation as Add.</li>
    <li>On success the modal auto-closes after ~1 s and the table refreshes.</li>
    <li>While the modal is open the table refreshes every 3 s.</li>
</ul>

<h4>Warnings</h4>
<ul>
    <li><b>At least one Admin user must be created</b> &mdash; Shown only while external access is disabled and no users exist.</li>
    <li><b>Last user cannot be deleted while enabled</b> &mdash; Shown when external access is enabled and only one user remains.</li>
</ul>

<h4>After Changing Users</h4>
<ul>
    <li>No Apache reload is needed &mdash; the password file (<code>.htpasswd</code>) and group file are rewritten directly and read per-request by <code>mod_authn_file</code> / <code>mod_authz_groupfile</code>.</li>
    <li>A visitor who changes their <b>own</b> password via the <b>Change Password Page</b> (proxied request) has their session cookie re-issued with the new password so they stay signed in.</li>
    <li>The external login session cookie name is <code>fppefpp</code> (realm <code>FPP External Web Access</code>); <code>/logout</code> on the external vhost clears it.</li>
</ul>
