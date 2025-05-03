#!/bin/bash

set -e
cd "$(dirname "$0")"

apply_patch() {
    local name="$1"
    local src="$2"
    local dest="$3"

    if cmp --silent "$src" "$dest"; then
        echo "No changes to $name."
    else
        echo "Patching $name..."
        cat "$src" >"$dest"
    fi
}

apply_patch rc.S ./rc.S /usr/local/etc/rc.d/rc.S
apply_patch rc.6 ./rc.6 /usr/local/etc/rc.d/rc.6
apply_patch rc.runlog ./rc.runlog /usr/local/etc/rc.d/rc.runlog
apply_patch flash_backup ./flash_backup /usr/local/emhttp/plugins/dynamix/scripts/flash_backup

echo "All files patched successfully."
