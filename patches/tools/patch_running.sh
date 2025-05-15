#!/bin/bash

set -e
cd "$(dirname "$0")"

SRC_DIR="../src"
DRY_RUN=false
if [[ "$1" == "--dry-run" ]]; then
    DRY_RUN=true
fi

apply_patch() {
    local name="$1"
    local dest="$2"

    if cmp --silent "$SRC_DIR/$name" "$dest"; then
        echo "No changes to $name."
    else
        if [ "$DRY_RUN" = true ]; then
            echo "Changes detected in $name. Would apply the following changes:"
            diff -u "$SRC_DIR/$name" "$dest" || true
        else
            echo "Patching $name..."
            cat "$SRC_DIR/$name" >"$dest"
        fi
    fi
}

apply_patch rc.S /usr/local/etc/rc.d/rc.S
apply_patch rc.6 /usr/local/etc/rc.d/rc.6
apply_patch rc.runlog /usr/local/etc/rc.d/rc.runlog
apply_patch flash_backup /usr/local/emhttp/plugins/dynamix/scripts/flash_backup

echo "All files patched successfully."
