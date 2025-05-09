#!/bin/bash

php "$(dirname "$0")/${SCRIPT_NAME}.php" "$@"
exit_code=$?
if [ $exit_code -ne 0 ]; then
    logger -t "${SCRIPT_NAME:-kissshot-plugin}" "[error] Script exited with error code $exit_code"
    /usr/local/emhttp/webGui/scripts/notify -e "${NOTIFICATION_TITLE:-kissshot-plugin}" -d "Script exited with error code $exit_code" -i "alert"
fi
