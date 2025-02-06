#!/bin/bash

set -e

ver=$(date +%Y.%m.%d)
cd "$(dirname "$0")"

find source/event -type f -exec chmod +x {} \;
tar -cJf zfs.encryption.automount-$ver.txz -C source .

echo "Version: $ver"
echo "md5sum: $(md5sum zfs.encryption.automount-$ver.txz)"