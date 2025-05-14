#!/bin/bash

PHP_RUN="/usr/local/emhttp/plugins/kissshot-plugin/run_php.sh"

SCRIPT_NAME="zfs-backup" NOTIFICATION_TITLE="ZFS Backup" $PHP_RUN
SCRIPT_NAME="health-check" NOTIFICATION_TITLE="Health Check" $PHP_RUN
