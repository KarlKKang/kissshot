#!/bin/bash

php "/usr/local/emhttp/plugins/kissshot-plugin/recycle-clean.php"
exit_code=$?
if [ $exit_code -ne 0 ]; then
    logger -t recycle-clean "[error] Script exited with error code $exit_code"
    /usr/local/emhttp/webGui/scripts/notify -e "Recycle Clean" -d "Script exited with error code $exit_code" -i "alert"
fi
