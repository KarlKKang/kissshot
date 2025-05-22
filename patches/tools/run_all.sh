#!/bin/bash

export MAJOR_VER="6"
export FULL_VER="6.12.24"
export ZFS_VER="2.3.1"

set -e
cd "$(dirname "$0")"

run_command() {
    local command="$1"
    sh "../bzroot/$command.sh" &&
        sh "../bzfirmware/$command.sh" &&
        sh "../kernel/$command.sh"
}

run_command cleanup
run_command revert
run_command unpack
run_command patch
run_command pack
run_command deploy

if ! btrfs subvolume show /boot/root/staging >/dev/null 2>&1; then
    echo "No staging subvolume found."
    exit 1
fi
if btrfs subvolume show /boot/root/prod >/dev/null 2>&1; then
    btrfs subvolume delete -c -R /boot/root/prod
    btrfs subvolume sync /boot/root
fi
if [ -d /boot/root/prod ]; then
    echo "/boot/root/prod already exists."
    exit 1
fi
mv /boot/root/staging /boot/root/prod

run_command cleanup
echo "All tasks completed successfully."
