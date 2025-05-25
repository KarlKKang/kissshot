#!/bin/bash

set -e
cd "$(dirname "$0")"

if [ ! -d ../bzfirmware/usr/lib64 ]; then
    echo "Please unpack bzfirmware first."
    exit 1
fi

cat ../src/rc.S >./root/etc/rc.d/rc.S

mv -i ../bzfirmware/usr/lib64/liblzo2.so* ./root/lib64
mv -i ../bzfirmware/usr/lib64/libunwind.so* ./root/lib64
mv -i ../bzfirmware/usr/lib64/libgcc_s.so* ./root/lib64
mv -i ../bzfirmware/usr/sbin/zfs ./root/sbin
mv -i ../bzfirmware/usr/sbin/zpool ./root/sbin
ln -si ../../sbin/zfs ../bzfirmware/usr/sbin
ln -si ../../sbin/zpool ../bzfirmware/usr/sbin

mkdir -p ./root/etc/modprobe.d
cat ../../boot/config/modprobe.d/zfs.conf >./root/etc/modprobe.d/zfs.conf

rm ./root/init
cat ../src/init >./root/init
chown root:root ./root/init
chmod 755 ./root/init

echo "bzroot: patches applied successfully"
