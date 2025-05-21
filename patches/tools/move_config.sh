#!/bin/bash

set -e

if [ -d "/boot_extra/config" ]; then
    if ! rmdir /boot_extra/config; then
        echo "New config already exists. Exiting."
        exit 1
    fi
fi

mkdir /boot_extra/config
rsync -a /boot/config/ /boot_extra/config/
if mountpoint -q /boot/config; then
    umount /boot/config
else
    mv /boot/config /boot/config.old
    mkdir /boot/config
fi
mount --bind /boot_extra/config /boot/config
