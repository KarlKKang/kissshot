#!/bin/bash

set -e

if [ -f "/boot/config.img" ]; then
    echo "Config image already exists. Exiting."
    exit 1
fi

dd if=/dev/zero of=/boot/config.img bs=1M count=1024
mkfs.btrfs /boot/config.img
mkdir /boot/config_new
mount -v -t btrfs -o auto,rw,noatime,nodiratime,nodiscard /boot/config.img /boot/config_new
rsync -avP /boot/config/ /boot/config_new/
umount /boot/config_new
rmdir /boot/config_new
mv /boot/config /boot/config.old
mkdir /boot/config
mount -v -t btrfs -o auto,rw,noatime,nodiratime,nodiscard /boot/config.img /boot/config
