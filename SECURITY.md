# Security Policy

## Reporting a Vulnerability

If you discover a security issue in this plugin, please open a private issue or
contact the maintainer via the GitHub repository at
https://github.com/jessica12ryan/fpp-ExternalFPP/security/advisories

Please do **not** report security issues through the public issue tracker if they
could be exploited before a fix is released.

## Scope

- The additional password-protected port provided by this plugin uses HTTP **Basic
  Auth over plain HTTP**. Basic Auth credentials are trivially visible on the wire,
  so this port should not be exposed to untrusted networks. If you need encryption,
  terminate TLS in front of it (VPN or reverse proxy).
- The generated `.htpasswd` file is stored under the plugin's `config/` directory
  (owned by the `fpp` user) and is only readable by local processes.
- The plugin intentionally does not touch the primary FPP web UI or its port.
