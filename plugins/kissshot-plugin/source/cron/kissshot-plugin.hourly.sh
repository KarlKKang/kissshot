#!/bin/bash

php "/usr/local/emhttp/plugins/kissshot-plugin/zfs-auto-snapshot.php"
exit_code=$?
if [ $exit_code -ne 0 ]; then
    logger -t zfs-auto-snapshot "[error] Script exited with error code $exit_code"
    /usr/local/emhttp/webGui/scripts/notify -e "ZFS Auto Snapshot" -d "Script exited with error code $exit_code" -i "alert"
fi

php "/usr/local/emhttp/plugins/kissshot-plugin/health-check.php"
exit_code=$?
if [ $exit_code -ne 0 ]; then
    logger -t health-check "[error] Script exited with error code $exit_code"
    /usr/local/emhttp/webGui/scripts/notify -e "Health Check" -d "Script exited with error code $exit_code" -i "alert"
fi
