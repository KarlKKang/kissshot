#!/bin/bash

SCRIPT_DIR=$(dirname "$0")
php "$SCRIPT_DIR/zfs-auto-snapshot.php"
exit_code=$?
if [ $exit_code -ne 0 ]; then
    logger -t zfs-auto-snapshot "[error] Script exited with error code $exit_code"
    /usr/local/emhttp/webGui/scripts/notify -e "ZFS Auto Snapshot" -d "Script exited with error code $exit_code" -i "alert"
fi
