#!/bin/bash

set -e
cd "$(dirname "$0")"

cmp --silent ./rc.S /usr/local/etc/rc.d/rc.S || {
    echo "Patching rc.S..."
    cat ./rc.S >/usr/local/etc/rc.d/rc.S
}
cmp --silent ./rc.6 /usr/local/etc/rc.d/rc.6 || {
    echo "Patching rc.6..."
    cat ./rc.6 >/usr/local/etc/rc.d/rc.6
}
cmp --silent ./flash_backup /usr/local/emhttp/plugins/dynamix/scripts/flash_backup || {
    echo "Patching flash_backup..."
    cat ./flash_backup >/usr/local/emhttp/plugins/dynamix/scripts/flash_backup
}

echo "All files patched successfully."
