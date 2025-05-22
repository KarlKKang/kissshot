#!/bin/bash

set -e
cd "$(dirname "$0")"

if [ ! -d ../bzfirmware/usr/lib64 ]; then
    echo "Please unpack bzfirmware first."
    exit 1
fi

cat ../src/rc.S >./root/etc/rc.d/rc.S
mv ../bzfirmware/usr/lib64/liblzo2.so* ./root/lib64
rm ./root/init
cat ../src/init >./root/init
chown root:root ./root/init
chmod 755 ./root/init

echo "bzroot: patches applied successfully"
