#!/bin/bash

set -e

rm -rf /boot/backup
if btrfs subvolume show /boot/root/previous >/dev/null 2>&1; then
    btrfs subvolume delete -c -R /boot/root/previous
    btrfs subvolume sync /boot/root
fi
btrfs subvolume snapshot /boot/root/prod /boot/root/previous

echo "The system is ready to be upgraded."
