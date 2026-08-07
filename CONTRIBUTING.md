# Contributing to fpp-ExternalFPP

> **For developers and advanced users.** This document contains the architecture, internals, and
> command-line details that are intentionally kept out of the user-facing [README.md](README.md).
> If you just want to use the plugin, read the README instead.

---

## Overview

`fpp-ExternalFPP` adds a second, password-protected web address to an FPP (Falcon Player). It works
by installing a second Apache virtual host on FPP's existing web server. That vhost requires a
form login (`mod_auth_form`) before it reverse-proxies requests to FPP's normal web UI on
`127.0.0.1:<backend_port>` (normally port 80).

### Key design decisions

- **No extra packages.** It relies only on Apache modules that are enabled automatically during
  install. The `htpasswd` binary is **not** required: passwords are stored as bcrypt hashes and
  written straight into the Apache password file (Apache accepts `$2y$` natively).
- **The normal FPP UI is untouched.** Only a new listener is added; nothing on port 80 changes.
- **Login is form-based**, not HTTP Basic Auth. Apache sets a session cookie on success
  (`mod_session` / `mod_session_cookie`) and the plugin tracks the username via the `X-Remote-User`
  header (set server-side by Apache) with a session-cookie fallback.
- **No plaintext passwords on disk.** User passwords are bcrypt hashes in
  `config/settings.json`; the Apache password file (`config/plugin.fpp-ExternalFPP.htpasswd`)
  contains the same hashes. See [Security model](#security-model).

---

## Repository layout

```
config/                  Runtime settings + password file (gitignored)
scripts/
  fpp_install.sh         Install/update: preserves config, applies settings
  fpp_uninstall.sh       Uninstall: disables vhost, removes Apache conf
  apply.php              Root helper that writes Apache conf + password file
templates/
  login.html             Default login page (user-editable)
  change-password.html   Default "set a new password" page (user-editable)
www/                     Deployed copies of the pages (gitignored)
api.php                  All HTTP API endpoints (loaded by the plugin framework)
*.php                    Plugin pages (Status/Config/Users/Pages/Logs/Help/About)
tabs.inc                 Tab bar shared by all pages + uiLevel + help-tooltip helpers + role guard
footer.inc               Shared page footer
pluginInfo.json          FPP Plugin Manager metadata
icon.png                 Plugin icon
```

The pages under the plugin root are rendered by FPP's plugin framework (they are loaded inside
FPP's UI with the shared tab bar). The pages you see on the extra port (`login.html`,
`change-password.html`) are plain static HTML served by Apache, with no PHP.

---

## Requirements

- FPP 8+ (ships with Apache 2.4)
- Apache modules: `proxy`, `proxy_http`, `headers`, `authn_file`, `auth_form`, `session`,
  `session_cookie`, `request`, `alias`, `ssl`, `rewrite` — all enabled by the install script.
  `mod_request` is required for `mod_auth_form` to work (without it Apache fails to start with
  AH02618). `mod_ssl` and `mod_rewrite` are needed for the HTTPS listener. Both modules ship
  with FPP's Apache; `ssl.load`/`ssl.conf` are already present in `mods-available` but not
  enabled by default.
- PHP CLI for `scripts/apply.php`.

---

## Manual install (developer)

The README covers the Plugin Manager path. To install/develop from a checkout:

```bash
cd /home/fpp/media/plugins
git clone https://github.com/jessica12ryan/fpp-ExternalFPP.git
cd fpp-ExternalFPP
sudo bash scripts/fpp_install.sh
```

The install script:

1. Backs up `config/settings.json`, `config/plugin.fpp-ExternalFPP.htpasswd`, and `www/login.html`
   to `/tmp/fpp-ExternalFPP-backup`.
2. If the plugin is a git clone, it does a `git fetch` + hard reset to `origin/main`.
3. Restores the backed-up config (so an update never loses your users/settings).
4. Creates a default `settings.json` on fresh installs (`port=8080`,
   `backend_port=80`, `https_port=8443`, `enable_http=0`, `enable_https=0`, no users),
   so the external ports are <b>disabled by default</b> until the user enables one and adds a user.
5. Fixes ownership/permissions for the `fpp` web user.
6. Runs `scripts/apply.php` to write the Apache config.

### Re-applying after an upgrade

After pulling a new version, re-run the installer (or `php scripts/apply.php`) so Apache picks up
any config changes. `apply.php` is idempotent.

---

## How authentication works

1. A visitor hits the external port without a session. The vhost's
   `<LocationMatch "^/(?!login.html)">` block requires a valid user
   (`Require valid-user`, provider `file`, `AuthUserFile` = the plugin's `.htpasswd`).
2. `AuthFormLoginRequiredLocation` redirects them to `/login.html`, which is served by an `Alias`
   from the plugin's `www/` directory and is deliberately *not* protected
   (`AuthType None` / `Require all granted`).
3. The login form POSTs `httpd_username` / `httpd_password` to the protected URL.
4. `mod_auth_form` validates against the password file. On success Apache sets the session cookie
   (`SessionCookieName fppefpp path=/; httponly`) and redirects to
   `AuthFormLoginSuccessLocation` (`/change-password.html`).
5. Every proxied request to the backend also gets `X-Remote-User` set server-side from
   `%{REMOTE_USER}s`. The API reads that to know who is logged in (with a fallback that parses the
   username out of the session cookie, because `mod_headers` can emit the literal string `(null)`
   before `REMOTE_USER` is populated).
6. Users flagged **must change password at next login** are held on `change-password.html` until
   they POST a new password to the `change-my-password` API endpoint, which clears the flag.

Logout is handled by Apache's `form-logout-handler` at `/logout`, which clears the session cookie
and redirects back to the login page.

### Session / identity constants

In `api.php`:

- `EFPP_SESSION_COOKIE` — the cookie name (`fppefpp`), must match the Apache vhost
  `SessionCookieName`.
- `EFPP_SESSION_REALM` — the `AuthName` used in the Apache vhost. The session cookie stores
  `<realm>-user=<username>&<realm>-pw=<password>`.

If you change the realm or cookie name you must change it in **both** `api.php` and the vhost
config generated by `scripts/apply.php`.

---

## Config file format

`config/settings.json`:

```json
{
  "port": 8080,
  "backend_port": 80,
  "https_port": 8443,
  "enable_http": 1,
  "enable_https": 1,
  "users": [
    { "username": "Ryan", "password": "<bcrypt hash>", "must_change": 0, "role": "admin" }
  ]
}
```

- `enable_http` / `enable_https` — whether each port is served; external access is derived from
  these (it is on when at least one is `1`), so there is no separate `enabled` key.
- `port` — the HTTP port.
- `enable_http` — `1` serves plain HTTP on `port`; `0` disables it.
- `enable_https` — `1` serves TLS on `https_port`; `0` disables it.
- `backend_port` — the port FPP's real UI listens on (usually `80`).
- `users[].password` — a **bcrypt hash** (prefix `$2y$`), never plaintext.
- `users[].must_change` — `1` forces the user to set a new password at next login.
- `users[].role` — `admin` (full access, the default for existing accounts) or `user` (limited: no
  plugin dashboard, no config/account changes). Only the literal string `user` is treated as limited.

Passwords are hashed with `password_hash($pw, PASSWORD_BCRYPT)`. The same hash is written directly
to the Apache password file. On upgrade, `apply.php` auto-migrates any legacy plaintext values to
bcrypt.

---

## Apache configuration

`scripts/apply.php` generates `/etc/apache2/conf-available/fpp-externalfpp.conf`. Whether each
listener/vhost is written is controlled by the `enable_http` and `enable_https` settings:

- when `enable_http` is on, a vhost is written on `<port>` that is a **password-protected reverse
  proxy** (plain HTTP);
- when `enable_https` is on, a vhost is written on `<https_port>` that is a password-protected
  reverse proxy with TLS (`SSLEngine on` + `SSLCertificateFile`/`SSLCertificateKeyFile` pointing at
  FPP's self-signed snakeoil cert `/etc/ssl/certs/ssl-cert-snakeoil.pem`).
- enabling only `enable_https` (leaving `enable_http` off) is what gives HTTPS-only /
  "force https" behaviour — there is no separate redirect.

The protected vhost body is shared via `efppBuildVhostBody()` (in `scripts/apply.php`) and it:

- proxies everything (`ProxyPass / http://127.0.0.1:<backend_port>/`) with `ProxyPreserveHost On`;
- keeps `login.html`, `change-password.html`, and `/logout` local (marked `!` so they are not
  proxied, with `Alias` to the plugin `www/` files);
- unsets `Authorization` before proxying so the browser's Basic credentials are never forwarded to
  FPP (which would re-trigger a browser prompt from FPP's own password file);
- sets `X-Remote-User` from `REMOTE_USER` server-side;
- forwards the real client IP as `X-Forwarded-For` (server-side) so the plugin can log who logged
  in, since `REMOTE_ADDR` behind the proxy is always `127.0.0.1`;
- requires a form login on everything except `login.html`.

`apply.php` also enables the required Apache modules (including `ssl` and `rewrite`), enables the
conf with `a2enconf`, and reloads Apache. The `conf-enabled` symlink survives reboots.

The uninstall script disables the conf (`a2disconf`) and deletes the Apache conf file.

---

## API endpoints

All endpoints live in `api.php` under `/api/plugin/fpp-ExternalFPP/`:

| Method | Endpoint | Purpose |
| --- | --- | --- |
| GET | `status` | Status data for the Status tab |
| POST | `save` | Save Config tab settings (validates, calls `apply.php`); enabling a port is done by saving `enable_http`/`enable_https` |
| POST | `restart` | Re-apply the Apache config |
| POST | `test` | Config-level checks (ports open, user in password file) |
| GET | `users` | List users |
| POST | `add-user` | Add a user |
| POST | `set-user-password` | Change a user's password (admin) |
| POST | `set-user-role` | Change a user's role (admin) |
| POST | `delete-user` | Remove a user (admin) |
| GET | `login-page` / `change-password-page` | Fetch page HTML |
| POST | `save-page` / `reset-page` | Save / reset a page to its template |
| GET | `session-user` | Who is logged in + `must_change` flag (also logs a successful login) |
| POST | `change-my-password` | Logged-in user changes their own password (self-service, not admin-gated) |
| GET | `logs` | Recent log entries |
| GET | `icon` | Plugin icon |

The `test` endpoint no longer performs a live login (passwords are hashes, so a plaintext login
isn't possible); it verifies the backend is reachable, the port is listening, and the first
configured user exists in the password file with a valid bcrypt hash.

Accounts have a `role` of `admin` (full) or `user` (limited). Role enforcement happens in two
places:

- **Every plugin page** blocks a session that belongs to a `user`-role account (`tabs.inc`) and
  shows an "Access denied" message instead of the dashboard.
- **API mutations that must be admin-only** (`save`, `restart`, `add-user`, `set-user-password`,
  `set-user-role`, `delete-user`, and the page save/reset endpoints) reject the request when the
  current session is a `user` account. `change-my-password` is intentionally *not* gated so limited
  users can still set their own password. The guard is `efppSessionIsUser()`: it only blocks when a
  logged-in session maps to a `user`-role account, so a visitor without a session (the normal FPP
  port / built-in admin) keeps full access.

---

## Logging

All API activity goes to `/home/fpp/media/logs/plugin-fpp-ExternalFPP.log` via `efppLog()`.
Every successful login is recorded (`SUCCESS Login: <username> from <client-ip>`) by the
`session-user` endpoint, and every password change is recorded with the username (never the
password).

---

## Security model

- **Both ports are off by default.** A fresh install writes `enable_http=0` and `enable_https=0`,
  so no external port is exposed until the user enables one (and adds at least one user). With
  `enable_https` on, the HTTPS port is served with FPP's self-signed snakeoil certificate
  (`/etc/ssl/certs/ssl-cert-snakeoil.pem`). For HTTPS-only, leave `enable_http` off (uncheck
  "Enable HTTP port"). An HTTP port that is enabled has no TLS: the login password travels as form
  data and the session cookie stores credentials in a reversible form (`mod_session_cookie`), so
  both are readable on the wire. Do not expose an unencrypted port to untrusted networks directly;
  terminate TLS in front of it (VPN or reverse proxy).
- **Hashed passwords.** No plaintext is stored in `settings.json` or the password file. Passwords
  are bcrypt via PHP's `password_hash`.
- **Header trust.** `X-Remote-User` is *replaced server-side* by Apache and never trusted from the
  client, which is what makes the "change my own password" API safe.
- **FPP's own UI password.** When FPP's built-in UI password is enabled, it applies on port 80.
  The plugin proxies to `127.0.0.1:80` from localhost, and FPP's `Require local` branch lets those
  proxied requests through, so normally the plugin user is not asked for FPP's password a second
  time. If you point `backend_port` at FPP's secure port (`8080`, configured by
  `secure-port-config.conf`), that vhost has **no** local exception and users *will* be prompted —
  don't point `backend_port` at it.
- **Self-signed cert caveat.** The snakeoil certificate is not signed by a public CA, so browsers
  show a certificate warning. Given the plugin proxies to FPP over the loopback interface only,
  this is acceptable; for a trust chain you would instead point `SSLCertificateFile`/`KeyFile`
  at your own cert in `scripts/apply.php` and put the traffic behind your own reverse proxy.

---

## Troubleshooting (command line)

The plugin's user-facing pages cover the common fixes. For deeper diagnostics:

```bash
# Plugin log
tail -20 /home/fpp/media/logs/plugin-fpp-ExternalFPP.log

# Apache error log for the external port
sudo tail -50 /home/fpp/media/logs/apache2-externalfpp-error.log

# Apache access log for the external port (login attempts etc.)
sudo tail -50 /home/fpp/media/logs/apache2-externalfpp-access.log

# Verify the vhost is loaded
sudo apachectl -S | grep -i 8080

# Check the generated vhost config
cat /etc/apache2/conf-enabled/fpp-externalfpp.conf

# Test the Apache config before reloading
sudo apachectl configtest
```

### Common failure: module missing

If Apache fails to start with `AH02618` after an install, `mod_request` is missing:

```bash
sudo a2enmod request
sudo systemctl reload apache2
```

If Apache fails to start when HTTPS is enabled (`enable_https=1`), `mod_ssl` may be missing or the
snakeoil certificate absent:

```bash
sudo a2enmod ssl
ls -l /etc/ssl/certs/ssl-cert-snakeoil.pem /etc/ssl/private/ssl-cert-snakeoil.key
sudo systemctl reload apache2
```

### Common failure: HTTPS not reachable after upgrade

With `enable_http=1` (and/or `enable_https=1`) and you re-apply, the corresponding vhost is
written, so the HTTPS port should answer. If the HTTPS port isn't listening, confirm the conf was
regenerated (re-run `php scripts/apply.php`) and that nothing else binds the HTTPS port.

### Common failure: "sent back to login page with the right password"

The password file may be out of date. Set the password again in the **Users** tab (which rewrites
the password file), or re-run `scripts/apply.php`. Also make sure the saved login page still
contains the `httpd_username` / `httpd_password` form fields (the Pages tab validates this on save).

---

## Developing / testing

1. Clone into `~/Desktop/fpp-ExternalFPP` (this repo) for edits, and mirror changes to the FPP box
   under `/home/fpp/media/plugins/fpp-ExternalFPP`.
2. Lint PHP files before committing:

   ```bash
   php -l api.php
   php -l scripts/apply.php
   php -l config.php
   php -l users.php
   php -l pages.php
   php -l logs.php
   php -l help.php
   php -l about.php
   php -l tabs.inc
   ```

3. The `www/login.html` and `www/change-password.html` files are **gitignored** (user-editable);
   the defaults live in `templates/`. Only edit the templates in the repo.
4. `config/settings.json` and `config/plugin.fpp-ExternalFPP.htpasswd` are gitignored too — never
   commit them (they contain credentials).

### Code style

- Follow the existing style: PHP 5-compatible arrays (`array(...)`), tabs for indentation in PHP
  files, 4-space in HTML/JS inline blocks, no PHP closing tag at EOF where avoidable, and no
  comments unless they explain non-obvious behavior.

---

## Making a contribution

1. Open an issue first for anything non-trivial (bug, feature, design change).
2. Fork the repo, create a branch, and make your changes.
3. Run `php -l` on every PHP file you touched.
4. Update the README/CONTRIBUTING docs if behavior changed.
5. Open a pull request back to `main`. Include a short description of what and why.

For security issues, do **not** open a public issue — see [SECURITY.md](SECURITY.md).

---

## License

MIT. Copyright © 2026 jessica12ryan. FPP is © Falcon Christmas; this plugin is an independent
integration and is not affiliated with or endorsed by the Falcon Christmas project.
