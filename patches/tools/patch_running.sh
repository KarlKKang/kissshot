#!/bin/bash

set -e
cd "$(dirname "$0")"

apply_patch() {
    local name="$1"
    local dest="$2"

    if cmp --silent "../$name" "$dest"; then
        echo "No changes to $name."
    else
        echo "Patching $name..."
        cat "../$name" >"$dest"
    fi
}

apply_patch rc.S /usr/local/etc/rc.d/rc.S
apply_patch rc.6  /usr/local/etc/rc.d/rc.6
apply_patch rc.runlog /usr/local/etc/rc.d/rc.runlog
apply_patch flash_backup /usr/local/emhttp/plugins/dynamix/scripts/flash_backup

echo "All files patched successfully."
