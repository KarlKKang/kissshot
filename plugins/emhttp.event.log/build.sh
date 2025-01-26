#!/bin/bash

ver=$(date +%Y.%m.%d)
cd "$(dirname "$0")" || exit 1

find source/event -type f -exec chmod +x {} \;
tar -cJf emhttp.event.log-$ver.txz -C source .

echo "Version: $ver"
echo "md5sum: $(md5sum emhttp.event.log-$ver.txz)"