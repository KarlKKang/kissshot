#!/bin/bash

set -e

ver=$(date +%Y.%m.%d)
cd "$(dirname "$0")"

if [ ! -f source/xray/xray ]; then
    echo "Please build xray binary first."
    exit 1
fi

rm -f kissshot-plugin-*.txz

find source/event -type f -exec chmod +x {} \;
find source/rc.d -type f -exec chmod +x {} \;
find source -type f -name "*.sh" -exec chmod +x {} \;
chmod +x source/system/ddns
chmod +x source/system/xrayd
chmod +x source/xray/xray
tar -cJf kissshot-plugin-$ver.txz -C source .

echo "Version: $ver"
echo "md5sum: $(md5sum kissshot-plugin-$ver.txz)"