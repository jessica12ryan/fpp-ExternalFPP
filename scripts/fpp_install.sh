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

# --- Preserve user configuration across any git operations ---
BACKUP_DIR="/tmp/fpp-ExternalFPP-backup"
PRESERVED_PAGES="login.html change-password.html access-denied.html"
mkdir -p "${BACKUP_DIR}/www" "${BACKUP_DIR}/templates"
if [ -f "${PLUGIN_DIR}/config/settings.json" ]; then
    cp "${PLUGIN_DIR}/config/settings.json" "${BACKUP_DIR}/settings.json" 2>/dev/null || true
fi
if [ -f "${PLUGIN_DIR}/config/plugin.fpp-ExternalFPP.htpasswd" ]; then
    cp "${PLUGIN_DIR}/config/plugin.fpp-ExternalFPP.htpasswd" "${BACKUP_DIR}/htpasswd" 2>/dev/null || true
fi
if [ -f "${PLUGIN_DIR}/config/plugin.fpp-ExternalFPP.groups" ]; then
    cp "${PLUGIN_DIR}/config/plugin.fpp-ExternalFPP.groups" "${BACKUP_DIR}/groups" 2>/dev/null || true
fi
# Back up each user-editable page along with its current template, so after the
# update we can tell whether the page was customized (restore it verbatim) or
# left at the default (refresh it to the freshly-updated template).
for page in ${PRESERVED_PAGES}; do
    if [ -f "${PLUGIN_DIR}/www/${page}" ]; then
        cp "${PLUGIN_DIR}/www/${page}" "${BACKUP_DIR}/www/${page}" 2>/dev/null || true
    fi
    if [ -f "${PLUGIN_DIR}/templates/${page}" ]; then
        cp "${PLUGIN_DIR}/templates/${page}" "${BACKUP_DIR}/templates/${page}" 2>/dev/null || true
    fi
done

# If the plugin is a git clone, update it (best effort)
if [ -d "${PLUGIN_DIR}/.git" ]; then
    git -C "${PLUGIN_DIR}" fetch origin 2>/dev/null || true
    git -C "${PLUGIN_DIR}" checkout -- . 2>/dev/null || true
    git -C "${PLUGIN_DIR}" clean -fd 2>/dev/null || true
    git -C "${PLUGIN_DIR}" reset --hard origin/main 2>/dev/null || true
fi

# Restore user configuration saved before the git operations
mkdir -p "${PLUGIN_DIR}/config" "${PLUGIN_DIR}/www"
if [ -f "${BACKUP_DIR}/settings.json" ]; then
    cp "${BACKUP_DIR}/settings.json" "${PLUGIN_DIR}/config/settings.json" 2>/dev/null || true
fi
if [ -f "${BACKUP_DIR}/htpasswd" ]; then
    cp "${BACKUP_DIR}/htpasswd" "${PLUGIN_DIR}/config/plugin.fpp-ExternalFPP.htpasswd" 2>/dev/null || true
fi
if [ -f "${BACKUP_DIR}/groups" ]; then
    cp "${BACKUP_DIR}/groups" "${PLUGIN_DIR}/config/plugin.fpp-ExternalFPP.groups" 2>/dev/null || true
fi
# Customized pages are kept as the user left them; pages still at the default
# are rolled forward to the updated template so bundled page fixes apply.
for page in ${PRESERVED_PAGES}; do
    old_page="${BACKUP_DIR}/www/${page}"
    old_template="${BACKUP_DIR}/templates/${page}"
    new_template="${PLUGIN_DIR}/templates/${page}"
    if [ -f "${old_page}" ]; then
        if [ -f "${old_template}" ] && cmp -s "${old_page}" "${old_template}"; then
            # Page was unmodified, so take the freshly-updated default template.
            if [ -f "${new_template}" ]; then
                cp "${new_template}" "${PLUGIN_DIR}/www/${page}" 2>/dev/null || true
            else
                rm -f "${PLUGIN_DIR}/www/${page}"
            fi
        else
            # Page was customized (or had no template to compare): keep it.
            cp "${old_page}" "${PLUGIN_DIR}/www/${page}" 2>/dev/null || true
        fi
    fi
done
rm -rf "${BACKUP_DIR}"

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
if chown -R fpp:fpp "${PLUGIN_DIR}/config" 2>/dev/null || chown -R :fpp "${PLUGIN_DIR}/config" 2>/dev/null; then
    chmod 775 "${PLUGIN_DIR}/config" 2>/dev/null || true
    find "${PLUGIN_DIR}/config" -type f -exec chmod 664 {} + 2>/dev/null || true
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
if command -v php >/dev/null 2>&1; then
    PHPRUN="php"
else
    PHPRUN="/usr/bin/php"
fi
"${PHPRUN}" "${PLUGIN_DIR}/scripts/apply.php" >/dev/null 2>&1 || true

echo "${PLUGIN_NAME}: Plugin installed successfully."
echo "${PLUGIN_NAME}: Go to Content Setup -> External FPP to configure the port, then add a user in the Users tab."
