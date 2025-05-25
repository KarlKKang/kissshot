#!/bin/bash

export MAJOR_VER="6"
export FULL_VER="6.12.24"
export ZFS_VER="2.3.1"

set -e
cd "$(dirname "$0")"

if zfs list rpool/root@orphaned >/dev/null 2>&1; then
    echo "Orphaned snapshot found. Please destroy it or rename it before proceeding."
    exit 1
fi

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

if zfs list rpool/root@production >/dev/null 2>&1; then
    zfs rename rpool/root@production rpool/root@orphaned
    zfs destroy -d rpool/root@orphaned
fi
zfs snapshot rpool/root@production

run_command cleanup
echo "All tasks completed successfully."
