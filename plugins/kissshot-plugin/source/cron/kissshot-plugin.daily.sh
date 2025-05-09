#!/bin/bash

PHP_RUN="/usr/local/emhttp/plugins/kissshot-plugin/run_php.sh"

SCRIPT_NAME="recycle-clean" NOTIFICATION_TITLE="Recycle Clean" $PHP_RUN
SCRIPT_NAME="trim" NOTIFICATION_TITLE="TRIM" $PHP_RUN
