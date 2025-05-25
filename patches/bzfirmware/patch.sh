#!/bin/bash

if [ -z "$FULL_VER" ]; then
    echo "Required environment variable FULL_VER is not set."
    exit 1
fi

set -e
cd "$(dirname "$0")"

CONFIG_DIR="./usr/src/linux-${FULL_VER}-Unraid"
if [ ! -d "$CONFIG_DIR" ]; then
    echo "Wrong FULL_VER: $FULL_VER, directory $CONFIG_DIR does not exist."
    exit 1
fi

SRC_DIR="../src"

cat $SRC_DIR/rc.S >./usr/local/etc/rc.d/rc.S
cat $SRC_DIR/rc.6 >./usr/local/etc/rc.d/rc.6
cat $SRC_DIR/rc.runlog >./usr/local/etc/rc.d/rc.runlog
cat $SRC_DIR/.config >"$CONFIG_DIR/.config"

echo "bzfirmware: patches applied successfully"
