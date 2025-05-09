#!/bin/bash

PHP_RUN="/usr/local/emhttp/plugins/kissshot-plugin/run_php.sh"

SCRIPT_NAME="zfs-auto-snapshot" NOTIFICATION_TITLE="ZFS Auto Snapshot" $PHP_RUN
SCRIPT_NAME="health-check" NOTIFICATION_TITLE="Health Check" $PHP_RUN
