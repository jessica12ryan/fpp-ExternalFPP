# Security Policy

## Reporting a Vulnerability

If you discover a security issue in this plugin, please open a private issue or
contact the maintainer via the GitHub repository at
https://github.com/jessica12ryan/fpp-ExternalFPP/security/advisories

Please do **not** report security issues through the public issue tracker if they
could be exploited before a fix is released.

## Scope

- The additional password-protected port provided by this plugin uses a **login form
  over plain HTTP**. The password is submitted as form data and the session cookie
  stores the login details in a reversible form, so both are visible on the wire.
  This port should not be exposed to untrusted networks. If you need encryption,
  terminate TLS in front of it (VPN or reverse proxy).
- User passwords are stored in the plugin's `config/settings.json` as plain text (so
  the `Test` button can exercise a real login) and the password *hashes* are written
  to `.htpasswd` under the plugin's `config/` directory (owned by the `fpp` user and
  readable only by local processes). Keep the plugin directory protected.
- The plugin intentionally does not touch the primary FPP web UI or its port.
