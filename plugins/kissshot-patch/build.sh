#!/bin/bash

set -e

ver=$(date +%Y.%m.%d)
cd "$(dirname "$0")"

rm -f kissshot-patch-*.txz

tar -cJf kissshot-patch-$ver.txz -C source .

echo "Version: $ver"
echo "md5sum: $(md5sum kissshot-patch-$ver.txz)"
