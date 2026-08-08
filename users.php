<?php
/**
 * #############################################################
 * ## External FPP Web Access (fpp-ExternalFPP)               ##
 * ## Author: jessica12ryan                                ##
 * ## URL: https://github.com/jessica12ryan/fpp-ExternalFPP##
 * #############################################################
 * ## users.php                                               ##
 * ## Manage the users allowed to log in to the external port.##
 * #############################################################
 */

include __DIR__ . '/tabs.inc';
?>

<div style="margin:0 auto;">
    <fieldset class="border p-3">
        <legend>External FPP Users</legend>
        <div class="p-3">
            <p>
                These users can log in to the external (password-protected) port via the login page.
            </p>
            <div id="efpp_users_warning"></div>
            <div id="efpp_users_table">
                <span class="text-secondary">Loading users...</span>
            </div>
            <div id="efpp_users_result" style="margin-top:8px;"></div>
        </div>
    </fieldset>

    <br />

    <fieldset class="border p-3">
        <legend>Add User</legend>
        <div class="p-3">
            <table>
                <tr>
                    <td style="padding: 4px;"><b>Username:</b></td>
                    <td style="padding: 4px;">
                        <input type="text" id="efpp_new_username" size="30" autocomplete="off">
                    </td>
                </tr>
                <tr>
                    <td style="padding: 4px;"><b>Password:</b></td>
                    <td style="padding: 4px;">
                        <input type="password" id="efpp_new_password" size="30" autocomplete="new-password">
                    </td>
                </tr>
                <tr>
                    <td style="padding: 4px;"><b>Confirm password:</b></td>
                    <td style="padding: 4px;">
                        <input type="password" id="efpp_new_confirm" size="30" autocomplete="new-password">
                    </td>
                </tr>
                <tr>
                    <td style="padding: 4px;"></td>
                    <td style="padding: 4px;">
                        <label><input type="checkbox" id="efpp_new_must_change"> Require password change at next login</label>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 4px;"><b>Role:</b></td>
                    <td style="padding: 4px;">
                        <select id="efpp_new_role">
                            <option value="user" selected>User (no dashboard access)</option>
                            <option value="admin">Admin (full access)</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 4px;"></td>
                    <td style="padding: 4px;">
                        <input type="button" class="buttons" value="Add User" onclick="efppUsers.add();">
                    </td>
                </tr>
            </table>
        </div>
    </fieldset>
</div>

<div id="efpp_change_modal" class="modal fade" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Change Password &mdash; <span id="efpp_cp_title"></span></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <table>
                    <tr>
                        <td style="padding: 4px;"><b>New password:</b></td>
                        <td style="padding: 4px;">
                            <input type="password" id="efpp_cp_password" size="30" autocomplete="new-password">
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 4px;"><b>Confirm new password:</b></td>
                        <td style="padding: 4px;">
                            <input type="password" id="efpp_cp_confirm" size="30" autocomplete="new-password">
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 4px;"></td>
                        <td style="padding: 4px;">
                            <label><input type="checkbox" id="efpp_cp_must_change"> Require password change at next login</label>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 4px;"></td>
                        <td style="padding: 4px;">
                            <div id="efpp_cp_result"></div>
                        </td>
                    </tr>
                </table>
            </div>
            <div class="modal-footer">
                <input type="button" class="buttons" value="Cancel" data-dismiss="modal">
                <input type="button" class="buttons" value="Save" onclick="efppUsers.changePassword();">
            </div>
        </div>
    </div>
</div>

<script>
var efppUsers = {
    apiBase: 'api/plugin/fpp-ExternalFPP',
    enabled: false,
    users: [],
    editing: '',
    cpTimer: null,

    showCpResult: function(data) {
        var html = '';
        if (data.errors && data.errors.length > 0) {
            html += '<span class="text-danger"><b>Errors:</b><br>' + data.errors.map(escHtml).join('<br>') + '</span>';
        }
        if (data.messages && data.messages.length > 0) {
            html += '<span class="text-success"><b>' + (data.success ? 'OK' : 'Notes') + ':</b><br>' + data.messages.map(escHtml).join('<br>') + '</span>';
        }
        $('#efpp_cp_result').html(html);
        if (data.success) {
            setTimeout(function() { $('#efpp_change_modal').modal('hide'); }, 1000);
        }
    },

    handleResponse: function(data) {
        var html = '';
        if (data.errors && data.errors.length > 0) {
            html += '<span class="text-danger"><b>Errors:</b><br>' + data.errors.map(escHtml).join('<br>') + '</span>';
        }
        if (data.messages && data.messages.length > 0) {
            html += '<span class="text-success"><b>' + (data.success ? 'OK' : 'Notes') + ':</b><br>' + data.messages.map(escHtml).join('<br>') + '</span>';
        }
        if (data.success === false && !data.errors) {
            html += '<span class="text-danger">Unknown error occurred.</span>';
        }
        $('#efpp_users_result').html(html);
        efppUsers.refresh();
        if (data.success) {
            setTimeout(function() { $('#efpp_users_result').html(''); }, 4000);
        }
    },

    render: function(d) {
        efppUsers.enabled = !!d.enabled;
        efppUsers.users = d.users || [];

        var html = '<table class="fppTable" style="width:auto;">';
        html += '<tr><th style="padding:4px;text-align:left;">Username</th><th style="padding:4px;text-align:left;">Role</th><th style="padding:4px;text-align:left;">Status</th><th style="padding:4px;"></th></tr>';
        if (efppUsers.users.length === 0) {
            html += '<tr><td colspan="4"><span class="text-danger">No users configured.</span></td></tr>';
        }
        for (var i = 0; i < efppUsers.users.length; i++) {
            var u = efppUsers.users[i];
            var name = u.username || '';
            var role = u.role || 'admin';
            var roleSelect = '<select data-user="' + escAttr(name) + '" data-role="' + (role === 'admin' ? 'admin' : 'user') + '" onchange="efppUsers.setRole(this);">' +
                '<option value="admin"' + (role === 'admin' ? ' selected' : '') + '>Admin</option>' +
                '<option value="user"' + (role === 'user' ? ' selected' : '') + '>User</option>' +
                '</select>';
            var badge = u.must_change ? '<span class="text-warning"><b>&#9888;</b> must change password</span>' : '<span class="text-success">OK</span>';
            html += '<tr><td style="padding:4px;"><b>' + escHtml(name) + '</b></td>' +
                '<td style="padding:4px;">' + roleSelect + '</td>' +
                '<td style="padding:4px;">' + badge + '</td>' +
                '<td style="padding:4px;">' +
                '<input type="button" class="buttons" value="Change Password" data-user="' + escAttr(name) + '" onclick="efppUsers.openModal(this);"> ' +
                '<input type="button" class="buttons" value="Delete" data-user="' + escAttr(name) + '" onclick="efppUsers.del(this);">' +
                '</td></tr>';
        }
        html += '</table>';
        $('#efpp_users_table').html(html);

        efppUsers.setDefaultRole();

        var warn = $('#efpp_users_warning');
        if (!efppUsers.enabled && efppUsers.users.length === 0) {
            warn.html('<div class="alert alert-danger" style="margin-top:8px;">' +
                '<b>At least one Admin user must be created before the plugin can be enabled.</b> ' +
                'Add an Admin user below, then enable the plugin from the Config tab.</div>');
        } else if (efppUsers.enabled && efppUsers.users.length <= 1) {
            warn.html('<p class="text-warning" style="margin-top:8px;">' +
                'The external port is enabled, so the last user cannot be deleted. ' +
                'Disable the plugin (Config tab) before removing this user.</p>');
        } else {
            warn.html('');
        }
    },

    refresh: function() {
        $.ajax({
            url: efppUsers.apiBase + '/users',
            type: 'GET',
            dataType: 'json',
            success: efppUsers.render,
            error: function() {}
        });
    },

    // The Add User form defaults to the Admin role until at least one Admin
    // exists (otherwise the plugin could never be enabled).
    setDefaultRole: function() {
        var hasAdmin = false;
        for (var i = 0; i < efppUsers.users.length; i++) {
            if ((efppUsers.users[i].role || 'admin') === 'admin') {
                hasAdmin = true;
                break;
            }
        }
        $('#efpp_new_role').val(hasAdmin ? 'user' : 'admin');
    },

    add: function() {
        var username = $('#efpp_new_username').val();
        var password = $('#efpp_new_password').val();
        var confirm = $('#efpp_new_confirm').val();
        var mustChange = $('#efpp_new_must_change').is(':checked');
        var role = $('#efpp_new_role').val();
        $('#efpp_users_result').html('<span class="text-warning">Adding user...</span>');
        $.ajax({
            url: efppUsers.apiBase + '/add-user',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                username: username,
                password: password,
                password_confirm: confirm,
                must_change: mustChange ? 1 : 0,
                role: role
            }),
            dataType: 'json',
            success: function(data) {
                efppUsers.handleResponse(data);
                if (data.success) {
                    $('#efpp_new_username').val('');
                    $('#efpp_new_password').val('');
                    $('#efpp_new_confirm').val('');
                    $('#efpp_new_must_change').prop('checked', false);
                    efppUsers.setDefaultRole();
                }
            },
            error: function(xhr) {
                efppUsers.handleResponse({ success: false, errors: ['Could not reach the plugin API. Check the FPP web server logs.'] });
            }
        });
    },

    openModal: function(btn) {
        var username = btn.getAttribute('data-user');
        efppUsers.editing = username;
        $('#efpp_cp_title').text(username);
        $('#efpp_cp_password').val('');
        $('#efpp_cp_confirm').val('');
        $('#efpp_cp_result').html('');
        for (var i = 0; i < efppUsers.users.length; i++) {
            if (efppUsers.users[i].username === username) {
                $('#efpp_cp_must_change').prop('checked', !!efppUsers.users[i].must_change);
                break;
            }
        }
        clearInterval(efppUsers.cpTimer);
        efppUsers.cpTimer = setInterval(function() { efppUsers.refresh(); }, 3000);
        $('#efpp_change_modal').modal('show');
        $('#efpp_cp_password').focus();
    },

    changePassword: function() {
        var username = efppUsers.editing;
        var password = $('#efpp_cp_password').val();
        var confirm = $('#efpp_cp_confirm').val();
        var mustChange = $('#efpp_cp_must_change').is(':checked');
        $('#efpp_cp_result').html('<span class="text-warning">Changing password...</span>');
        $.ajax({
            url: efppUsers.apiBase + '/set-user-password',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                username: username,
                password: password,
                password_confirm: confirm,
                must_change: mustChange ? 1 : 0
            }),
            dataType: 'json',
            success: function(data) {
                efppUsers.showCpResult(data);
                if (data.success) {
                    clearInterval(efppUsers.cpTimer);
                }
            },
            error: function(xhr) {
                efppUsers.showCpResult({ success: false, errors: ['Could not reach the plugin API. Check the FPP web server logs.'] });
            }
        });
    },

    setRole: function(sel) {
        var username = sel.getAttribute('data-user');
        var previousRole = sel.getAttribute('data-role');
        var role = sel.value;
        if (role === previousRole) {
            return;
        }
        $('#efpp_users_result').html('<span class="text-warning">Updating role for ' + escHtml(username) + '...</span>');
        $.ajax({
            url: efppUsers.apiBase + '/set-user-role',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ username: username, role: role }),
            dataType: 'json',
            success: function(data) {
                if (data.success) {
                    efppUsers.handleResponse(data);
                } else {
                    sel.value = previousRole;
                    efppUsers.handleResponse({ success: false, errors: data.errors || ['Could not update the role.'] });
                }
            },
            error: function(xhr) {
                sel.value = previousRole;
                efppUsers.handleResponse({ success: false, errors: ['Could not reach the plugin API. Check the FPP web server logs.'] });
            }
        });
    },

    del: function(btn) {
        var username = btn.getAttribute('data-user');
        if (!confirm('Delete user "' + username + '"?')) {
            return;
        }
        $('#efpp_users_result').html('<span class="text-warning">Deleting user...</span>');
        $.ajax({
            url: efppUsers.apiBase + '/delete-user',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ username: username }),
            dataType: 'json',
            success: function(data) {
                efppUsers.handleResponse(data);
                if (data.success) {
                    $('#efpp_change_modal').modal('hide');
                }
            },
            error: function(xhr) {
                efppUsers.handleResponse({ success: false, errors: ['Could not reach the plugin API. Check the FPP web server logs.'] });
            }
        });
    }
};

function escHtml(s) {
    if (s == null) return '';
    return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function escAttr(s) {
    return escHtml(String(s == null ? '' : s)).replace(/'/g, '&#39;');
}

$(document).ready(function() {
    efppUsers.refresh();
    setInterval(efppUsers.refresh, 5000);
});
</script>

<?php include __DIR__ . '/footer.inc'; ?>