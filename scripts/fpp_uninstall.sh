#!/bin/bash

#############################################################
## External FPP Web Access (fpp-ExternalFPP)               ##
## Author: jessica12ryan                                ##
## URL: https://github.com/jessica12ryan/fpp-ExternalFPP##
#############################################################
## Uninstall Script                                        ##
#############################################################

PLUGIN_NAME="fpp-ExternalFPP"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_DIR="$(dirname "${SCRIPT_DIR}")"

# Remove the Apache listener and vhost before the plugin dir is deleted
if command -v a2disconf >/dev/null 2>&1; then
    a2disconf fpp-externalfpp >/dev/null 2>&1 || true
    systemctl reload apache2 >/dev/null 2>&1 || apachectl -k graceful >/dev/null 2>&1 || true
fi
rm -f /etc/apache2/conf-available/fpp-externalfpp.conf 2>/dev/null || true

# Prompt FPP to restart so the UI reflects the change
if [ -f /opt/fpp/scripts/common ]; then
    . /opt/fpp/scripts/common
    setSetting restartFlag 1
fi

echo "${PLUGIN_NAME}: Plugin uninstalled successfully."
