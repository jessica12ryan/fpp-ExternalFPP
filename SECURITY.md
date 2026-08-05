# Security Policy

## Reporting a Vulnerability

If you discover a security issue in this plugin, please open a private issue or
contact the maintainer via the GitHub repository at
https://github.com/jessica12ryan/fpp-ExternalFPP/security/advisories

Please do **not** report security issues through the public issue tracker if they
could be exploited before a fix is released.

## Scope

- By default the plugin serves **both** an **HTTP** and an **HTTPS** port. The HTTPS port is served
  over TLS using FPP's built-in **self-signed** certificate, so traffic is encrypted but browsers
  will show a certificate warning. To force HTTPS only, leave only the HTTPS port enabled (uncheck
  **Enable HTTP port**). Any HTTP port that is enabled is **plain HTTP** — the password is submitted
  as form data and the session cookie stores the login details in a reversible form, so both are
  visible on the wire. An unencrypted port should not be exposed to untrusted networks; if you need
  a proper certificate or a public deployment, terminate TLS in front of it (VPN or reverse proxy)
  using your own cert.
- User passwords are stored as **bcrypt hashes** in the plugin's `config/settings.json`, and the
  same hashes are written to `.htpasswd` under the plugin's `config/` directory (owned by the
  `fpp` user and readable only by local processes). No plaintext passwords are kept on disk. Keep
  the plugin directory protected.
- The plugin intentionally does not touch the primary FPP web UI or its port.
