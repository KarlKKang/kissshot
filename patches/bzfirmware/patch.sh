#!/bin/bash

set -e
cd "$(dirname "$0")"

SRC_DIR="../src"

cat $SRC_DIR/rc.S >./usr/local/etc/rc.d/rc.S
cat $SRC_DIR/rc.6 >./usr/local/etc/rc.d/rc.6
cat $SRC_DIR/rc.runlog >./usr/local/etc/rc.d/rc.runlog
cat $SRC_DIR/flash_backup >./usr/local/emhttp/plugins/dynamix/scripts/flash_backup

echo "All files patched successfully."
