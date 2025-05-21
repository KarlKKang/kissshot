#!/bin/bash

set -e
cd "$(dirname "$0")"

if [ ! -d ../bzfirmware/usr/lib64 ]; then
    echo "Please unpack bzfirmware first."
    exit 1
fi

cat ../src/rc.S >./root/etc/rc.d/rc.S
mv ../bzfirmware/usr/lib64/liblzo2.so* ./root/lib64

echo "bzroot: patches applied successfully"
