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
        <legend>Users</legend>
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
                        <input type="button" class="buttons" value="Add User" onclick="efppUsers.add();">
                    </td>
                </tr>
            </table>
        </div>
    </fieldset>

    <br />

    <fieldset class="border p-3">
        <legend>Change Password</legend>
        <div class="p-3">
            <table>
                <tr>
                    <td style="padding: 4px;"><b>User:</b></td>
                    <td style="padding: 4px;">
                        <select id="efpp_cp_user"></select>
                    </td>
                </tr>
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
                        <input type="button" class="buttons" value="Change Password" onclick="efppUsers.changePassword();">
                    </td>
                </tr>
            </table>
        </div>
    </fieldset>
</div>

<script>
var efppUsers = {
    apiBase: 'api/plugin/fpp-ExternalFPP',
    enabled: false,
    users: [],

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
    },

    render: function(d) {
        efppUsers.enabled = !!d.enabled;
        efppUsers.users = d.users || [];

        var html = '<table class="fppTable" style="width:auto;">';
        html += '<tr><th style="padding:4px;text-align:left;">Username</th><th style="padding:4px;"></th></tr>';
        if (efppUsers.users.length === 0) {
            html += '<tr><td colspan="2"><span class="text-danger">No users configured.</span></td></tr>';
        }
        for (var i = 0; i < efppUsers.users.length; i++) {
            var u = efppUsers.users[i];
            html += '<tr><td style="padding:4px;"><b>' + escHtml(u) + '</b></td>' +
                '<td style="padding:4px;">' +
                '<input type="button" class="buttons" value="Delete" data-user="' + escAttr(u) + '" onclick="efppUsers.del(this);">' +
                '</td></tr>';
        }
        html += '</table>';
        $('#efpp_users_table').html(html);

        var warn = $('#efpp_users_warning');
        if (!efppUsers.enabled && efppUsers.users.length === 0) {
            warn.html('<div class="alert alert-danger" style="margin-top:8px;">' +
                '<b>At least one user must be created before the plugin can be enabled.</b> ' +
                'Add a user below, then enable the plugin from the Config tab.</div>');
        } else if (efppUsers.enabled && efppUsers.users.length <= 1) {
            warn.html('<p class="text-warning" style="margin-top:8px;">' +
                'The external port is enabled, so the last user cannot be deleted. ' +
                'Disable the plugin (Config tab) before removing this user.</p>');
        } else {
            warn.html('');
        }

        var sel = $('#efpp_cp_user');
        var current = sel.val();
        sel.empty();
        for (var j = 0; j < efppUsers.users.length; j++) {
            sel.append('<option value="' + escAttr(efppUsers.users[j]) + '">' + escHtml(efppUsers.users[j]) + '</option>');
        }
        if (current && efppUsers.users.indexOf(current) !== -1) {
            sel.val(current);
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

    add: function() {
        var username = $('#efpp_new_username').val();
        var password = $('#efpp_new_password').val();
        var confirm = $('#efpp_new_confirm').val();
        $('#efpp_users_result').html('<span class="text-warning">Adding user...</span>');
        $.ajax({
            url: efppUsers.apiBase + '/add-user',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                username: username,
                password: password,
                password_confirm: confirm
            }),
            dataType: 'json',
            success: function(data) {
                efppUsers.handleResponse(data);
                if (data.success) {
                    $('#efpp_new_username').val('');
                    $('#efpp_new_password').val('');
                    $('#efpp_new_confirm').val('');
                }
            },
            error: function(xhr) {
                efppUsers.handleResponse({ success: false, errors: ['Could not reach the plugin API. Check the FPP web server logs.'] });
            }
        });
    },

    changePassword: function() {
        var username = $('#efpp_cp_user').val();
        var password = $('#efpp_cp_password').val();
        var confirm = $('#efpp_cp_confirm').val();
        $('#efpp_users_result').html('<span class="text-warning">Changing password...</span>');
        $.ajax({
            url: efppUsers.apiBase + '/set-user-password',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                username: username,
                password: password,
                password_confirm: confirm
            }),
            dataType: 'json',
            success: function(data) {
                efppUsers.handleResponse(data);
                if (data.success) {
                    $('#efpp_cp_password').val('');
                    $('#efpp_cp_confirm').val('');
                }
            },
            error: function(xhr) {
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