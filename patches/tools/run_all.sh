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
run_command cleanup
echo "All tasks completed successfully."
