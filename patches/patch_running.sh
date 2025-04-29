#!/bin/bash

set -e
cd "$(dirname "$0")"

cat ./rc.S >/usr/local/etc/rc.d/rc.S
cat ./rc.6 >/usr/local/etc/rc.d/rc.6
cat ./flash_backup >/usr/local/emhttp/plugins/dynamix/scripts/flash_backup

echo "All files patched successfully."
