#!/bin/bash
set -e

#############################################################
## External FPP Web Access (fpp-ExternalFPP)               ##
## Author: jessica12ryan                                ##
## URL: https://github.com/jessica12ryan/fpp-ExternalFPP##
#############################################################
## Install/Update Script                                   ##
#############################################################

PLUGIN_NAME="fpp-ExternalFPP"

# Plugin directory is always the parent of the scripts/ directory
# (works regardless of how the plugin manager invokes this script)
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_DIR="$(dirname "${SCRIPT_DIR}")"

# --- Write default settings on a fresh install ---
if [ ! -f "${PLUGIN_DIR}/config/settings.json" ]; then
    mkdir -p "${PLUGIN_DIR}/config"
    cat > "${PLUGIN_DIR}/config/settings.json" <<'EOF'
{
  "port": 8080,
  "backend_port": 80,
  "enable_http": 0,
  "enable_https": 0,
  "users": []
}
EOF
fi

# --- Fix permissions so the web server (fpp user) can read/write everything ---
# Credential files keep no world access (apply.php enforces 640/600); a blanket
# 664 here would undo that on every upgrade, so handle them explicitly.
if chown -R fpp:fpp "${PLUGIN_DIR}/config" 2>/dev/null || chown -R :fpp "${PLUGIN_DIR}/config" 2>/dev/null; then
    chmod 775 "${PLUGIN_DIR}/config" 2>/dev/null || true
    find "${PLUGIN_DIR}/config" -type f -exec chmod 664 {} + 2>/dev/null || true
    for f in "${PLUGIN_DIR}/config/plugin.fpp-ExternalFPP.htpasswd" "${PLUGIN_DIR}/config/plugin.fpp-ExternalFPP.groups" "${PLUGIN_DIR}/config/settings.json"; do
        [ -f "$f" ] && chmod 640 "$f" 2>/dev/null || true
    done
    [ -f "${PLUGIN_DIR}/config/session-crypto.key" ] && chmod 600 "${PLUGIN_DIR}/config/session-crypto.key" 2>/dev/null || true
fi
if [ -d "${PLUGIN_DIR}/www" ]; then
    chown -R fpp:fpp "${PLUGIN_DIR}/www" 2>/dev/null || chown -R :fpp "${PLUGIN_DIR}/www" 2>/dev/null || true
    chmod 775 "${PLUGIN_DIR}/www" 2>/dev/null || true
    find "${PLUGIN_DIR}/www" -type f -exec chmod 664 {} + 2>/dev/null || true
fi

# Make sure helper scripts are executable
chmod +x "${PLUGIN_DIR}/scripts/apply.php" 2>/dev/null || true
chmod +x "${PLUGIN_DIR}/scripts/fpp_install.sh" 2>/dev/null || true
chmod +x "${PLUGIN_DIR}/scripts/fpp_uninstall.sh" 2>/dev/null || true

# --- Apply settings to Apache (idempotent; disabled by default on fresh install) ---
# EFPP_DEFER_RESTART: never do a full Apache restart here. A restart drops the
# HTTP connection running the plugin upgrade (freeze), and new modules work via
# <IfModule> guards until the next Config save or reboot activates them.
if command -v php >/dev/null 2>&1; then
    PHPRUN="php"
else
    PHPRUN="/usr/bin/php"
fi
EFPP_DEFER_RESTART=1 "${PHPRUN}" "${PLUGIN_DIR}/scripts/apply.php" >/dev/null 2>&1 || true

echo "${PLUGIN_NAME}: Plugin installed successfully."
echo "${PLUGIN_NAME}: Go to Content Setup -> External FPP to configure the port, then add a user in the Users tab."
