# External FPP Web Access (fpp-ExternalFPP)

> **An FPP plugin that opens an additional TCP port which serves FPP's web UI behind a username and password (HTTP Basic Auth).**

The normal FPP web UI (port 80) is left completely untouched. This plugin adds a
*second*, password-protected port that reverse-proxies the same FPP web UI. This is
useful when you want to hand a URL to guests, remote users, or a DMZ without exposing
the main UI, or when you simply want an extra layer of credentials in front of the UI.

It is implemented as a second Apache virtual host on FPP's *existing* web server
(FPP already runs Apache) - **no extra packages or daemons are required**.

## Requirements

- FPP 8+ (uses FPP's built-in Apache 2 web server)
- Apache modules `mod_proxy`, `mod_proxy_http`, `mod_auth_basic`, `mod_authn_file`
  (enabled automatically by the plugin install script)
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

A login prompt will appear. Enter the configured username/password to reach the FPP
web UI. Requests without valid credentials receive `401 Unauthorized`.

## How it works

| Piece | Detail |
| --- | --- |
| Apache vhost | `<VirtualHost *:<port>>` added under `/etc/apache2/conf-available/fpp-externalfpp.conf` |
| Authentication | HTTP Basic Auth using `mod_auth_basic` + a `.htpasswd` file stored in the plugin's `config/` directory |
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

## Security notes

- Traffic on the additional port is plain HTTP. Do **not** expose it directly to the
  internet - put it behind a VPN or a TLS-terminating reverse proxy if you need
  remote access.
- If FPP's built-in **UI Password** is also configured, requests proxied through this
  plugin may be challenged by *both* layers depending on FPP's auth configuration.
- This plugin does not modify or remove the normal, unprotected FPP UI on port 80.

## License

MIT. FPP is (c) Falcon Christmas. This plugin is an independent integration and is
not affiliated with or endorsed by the Falcon Christmas project.
