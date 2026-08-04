# External FPP Web Access (fpp-ExternalFPP)

> **An FPP plugin that opens an additional TCP port which serves FPP's web UI behind a login page (username and password).**

The normal FPP web UI (port 80) is left completely untouched. This plugin adds a
*second*, password-protected port that reverse-proxies the same FPP web UI. Visitors
to that port are greeted by a sign-in page instead of the FPP dashboard. This is
useful when you want to hand a URL to guests, remote users, or a DMZ without exposing
the main UI, or when you simply want an extra layer of credentials in front of the UI.

It is implemented as a second Apache virtual host on FPP's *existing* web server
(FPP already runs Apache) - **no extra packages or daemons are required**.

## Requirements

- FPP 8+ (uses FPP's built-in Apache 2 web server)
- Apache modules `mod_proxy`, `mod_proxy_http`, `mod_auth_form`, `mod_session`,
  `mod_session_cookie`, `mod_authn_file`, `mod_alias` (enabled automatically by the
  plugin install script)
- The FPP web UI reachable on its normal port (default `80`)

## Installation

### Plugin Manager (Recommended)

1. In FPP UI, go to **Content Setup -> Plugin Manager**
2. Enter this URL and click **Get Plugin Info**:

   ```
   https://raw.githubusercontent.com/jessica12ryan/fpp-ExternalFPP/main/pluginInfo.json
   ```

3. Click **Install** next to the "External FPP Web Access" plugin.

### Manual

```bash
cd /home/fpp/media/plugins
git clone https://github.com/jessica12ryan/fpp-ExternalFPP.git
cd fpp-ExternalFPP
sudo bash scripts/fpp_install.sh
```

## Configuration

1. Go to **Content Setup -> External FPP**
2. Set the **Listen Port** (default `8080`) that will serve the protected UI
3. Enter a **Username** and **Password**
4. Click **Save & Apply**

Once enabled you can browse to:

```
http://<fpp-ip>:8080/
```

You are shown a **login page**. Enter the configured username/password to reach the FPP
web UI. Requests without a valid session are redirected back to the login page.

## Customizing the login page

The login page is just HTML. Open the plugin's **Login Page** tab, edit the page, and
click **Save Login Page** - the change applies immediately (Apache reads the file on
every request). The page is stored at `www/login.html` in the plugin directory.

For login to work the page must contain a `<form method="post">` that posts to the
protected port (`action="/"`) with `<input name="httpd_username">` and
`<input name="httpd_password">` fields. The **Login Page** tab shows this required code
and warns you if it is missing when you save.

To sign out, visit `http://<fpp-ip>:8080/logout`.

## How it works

| Piece | Detail |
| --- | --- |
| Apache vhost | `<VirtualHost *:<port>>` added under `/etc/apache2/conf-available/fpp-externalfpp.conf` |
| Authentication | Form login using `mod_auth_form` + `mod_session`/`mod_session_cookie`, checked against a `.htpasswd` file in the plugin's `config/` directory |
| Login page | Served directly from `www/login.html` (not proxied), reachable without a session |
| Proxy | `ProxyPass / http://127.0.0.1:80/` with `ProxyPreserveHost On` so cookies/sessions work normally |
| Persistence | The Apache `conf-enabled` symlink survives reboots, so the extra port comes up at boot |
| Cleanup | Uninstalling the plugin disables the vhost and removes the Apache config file |

The password hash is written with `htpasswd -B` (bcrypt) when `apache2-utils` is
present, and falls back to a `{SHA}` hash (supported by every Apache 2.4 build)
otherwise.

## Troubleshooting

- Check the plugin log:
  ```bash
  tail -20 /home/fpp/media/logs/plugin-fpp-ExternalFPP.log
  ```
- Check Apache error log for the external port:
  ```bash
  sudo tail -50 /home/fpp/media/logs/apache2-externalfpp-error.log
  ```
- Verify the vhost is loaded:
  ```bash
  sudo apachectl -S | grep -i 8080
  ```
- Port already in use? Pick a different **Listen Port** in the plugin config.

### "I logged in but the browser asks for a password again"

The plugin uses a **login page + session cookie**, not HTTP Basic Auth, so the old
"prompts forever" loop no longer applies. If you are still prompted after signing in,
it is **FPP's built-in UI password** (Status/Control -> FPP Settings -> UI tab). FPP's
own Apache adds that check on port 80, and the prompt shows through the proxied pages.

This plugin proxies through the FPP itself, which FPP normally exempts from its own password, so
on a standard FPP the two passwords do not clash. If you still see the prompt:

1. Open the plugin's **Status** tab and check the "FPP built-in UI password" row.
2. Either turn off FPP's UI password (Status/Control -> FPP Settings -> UI tab -> Enable UI
   password = No) since this plugin provides its own, or
3. When prompted a second time on the external port, enter FPP's own admin credentials
   (username `admin` and the password you set for FPP's UI).

The plugin never forwards your credentials to FPP's web server, so the two layers stay
independent.

## Security notes

- Traffic on the additional port is plain HTTP. The login password is submitted as form
  data and the session cookie stores the login details in a reversible form, so both can
  be read on the wire. Do **not** expose it directly to the internet - put it behind a
  VPN or a TLS-terminating reverse proxy if you need remote access.
- If FPP's built-in **UI Password** is also configured, requests proxied through this
  plugin may be challenged by *both* layers depending on FPP's auth configuration.
- This plugin does not modify or remove the normal, unprotected FPP UI on port 80.

## License

MIT. FPP is (c) Falcon Christmas. This plugin is an independent integration and is
not affiliated with or endorsed by the Falcon Christmas project.
