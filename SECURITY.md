# Security Policy

## Reporting a Vulnerability

If you discover a security issue in this plugin, please open a private issue or
contact the maintainer via the GitHub repository at
https://github.com/jessica12ryan/fpp-ExternalFPP/security/advisories

Please do **not** report security issues through the public issue tracker if they
could be exploited before a fix is released.

## Scope

- External access is **disabled until an Admin user exists** and a port is explicitly
  enabled. The HTTPS port is served over TLS using FPP's built-in **self-signed**
  certificate, so traffic is encrypted but browsers will show a certificate warning.
  To force HTTPS only, leave only the HTTPS port enabled (uncheck **Enable HTTP port**).
  Any HTTP port that is enabled is **plain HTTP** — the password is submitted as form
  data and the session cookie is visible on the wire. An unencrypted port should not be
  exposed to untrusted networks; if you need a proper certificate or a public deployment,
  terminate TLS in front of it (VPN or reverse proxy) using your own cert.
- The form-login session cookie is **encrypted** with `mod_session_crypto` using a
  per-install key in `config/session-crypto.key` (mode `0600`, never committed).
  Changing a password invalidates the session, so users sign in again afterwards.
- User passwords are stored as **bcrypt hashes** in the plugin's `config/settings.json`, and the
  same hashes are written to `.htpasswd` under the plugin's `config/` directory
  (mode `0640`, owner `fpp`, no world access; the group file likewise). No plaintext
  passwords are kept on disk. The files stay in `config/` — never in `media/config/`,
  which is copied into crash reports and JSON backups. Keep the plugin directory protected.
- Role separation is enforced at the Apache layer: the FPP settings/plugin/network/file-manager/backup
  pages, all of `/api/` (except the login `login-success` / `change-my-password` /
  `session-user` endpoints), and the SSH shell proxy (`/proxy/127.0.0.1:4200`) are
  **Admin-only**. Generic `/proxy/` device passthrough remains available to any
  logged-in user.
- The plugin intentionally does not touch the primary FPP web UI or its port.
