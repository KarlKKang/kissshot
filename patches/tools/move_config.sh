#!/bin/bash

set -e

MNT_DIR="/mnt/rpool"

if ! mountpoint -q "$MNT_DIR"; then
    echo "$MNT_DIR is not a mountpoint. Please mount it first."
    exit 1
fi

zfs create -o mountpoint=$MNT_DIR/config -o canmount=on rpool/config
rsync -a /boot/config/ "$MNT_DIR/config/"
if mountpoint -q /boot/config; then
    umount /boot/config
else
    mv /boot/config /boot/config.old
    mkdir /boot/config
fi
zfs set mountpoint=/boot/config rpool/config
zfs set canmount=noauto rpool/config
echo "Configuration files moved successfully"
