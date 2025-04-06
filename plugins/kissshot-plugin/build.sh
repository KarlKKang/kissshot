#!/bin/bash

set -e

ver=$(date +%Y.%m.%d)
cd "$(dirname "$0")"

rm -f kissshot-plugin-*.txz

find source/event -type f -exec chmod +x {} \;
find source -type f -name "*.sh" -exec chmod +x {} \;
tar -cJf kissshot-plugin-$ver.txz -C source .

echo "Version: $ver"
echo "md5sum: $(md5sum kissshot-plugin-$ver.txz)"