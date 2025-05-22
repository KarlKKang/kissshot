#!/bin/bash

set -e

MNT_DIR="/mnt/root"

if ! mountpoint -q "$MNT_DIR"; then
    echo "$MNT_DIR is not a mountpoint. Please mount it first."
    exit 1
fi

if [ -d "$MNT_DIR/config" ]; then
    echo "New config already exists. Exiting."
    exit 1
fi

btrfs subvolume create "$MNT_DIR/config"
rsync -a /boot/config/ "$MNT_DIR/config/"
if mountpoint -q /boot/config; then
    umount /boot/config
else
    mv /boot/config /boot/config.old
    mkdir /boot/config
fi
mount --bind "$MNT_DIR/config" /boot/config
