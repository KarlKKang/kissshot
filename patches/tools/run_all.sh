#!/bin/bash

export MAJOR_VER="6"
export FULL_VER="6.12.24"
export ZFS_VER="2.3.1"

set -e

SCRIPT_DIR="$(dirname "$0")"

cleanup() {
    sh "$SCRIPT_DIR/../bzroot/cleanup.sh" &&
        sh "$SCRIPT_DIR/../bzfirmware/cleanup.sh" &&
        sh "$SCRIPT_DIR/../kernel/cleanup.sh"
}

revert() {
    sh "$SCRIPT_DIR/revert.sh" "bzroot" &&
        sh "$SCRIPT_DIR/revert.sh" "bzfirmware" &&
        sh "$SCRIPT_DIR/revert.sh" "bzimage" &&
        sh "$SCRIPT_DIR/revert.sh" "bzmodules"
}

unpack() {
    sh "$SCRIPT_DIR/../bzroot/unpack.sh" &&
        sh "$SCRIPT_DIR/../bzfirmware/unpack.sh" &&
        sh "$SCRIPT_DIR/../kernel/unpack.sh"
}

patch() {
    sh "$SCRIPT_DIR/../bzroot/patch.sh" &&
        sh "$SCRIPT_DIR/../bzfirmware/patch.sh" &&
        sh "$SCRIPT_DIR/../kernel/patch.sh"
}

pack() {
    sh "$SCRIPT_DIR/../bzroot/pack.sh" &&
        sh "$SCRIPT_DIR/../bzfirmware/pack.sh" &&
        sh "$SCRIPT_DIR/../kernel/pack.sh"
}

deploy() {
    sh "$SCRIPT_DIR/deploy.sh" "bzroot" "$SCRIPT_DIR/../bzroot/bzroot" &&
        sh "$SCRIPT_DIR/deploy.sh" "bzfirmware" "$SCRIPT_DIR/../bzfirmware/bzfirmware" &&
        sh "$SCRIPT_DIR/deploy.sh" "bzimage" "$SCRIPT_DIR/../kernel/bzimage" &&
        sh "$SCRIPT_DIR/deploy.sh" "bzmodules" "$SCRIPT_DIR/../kernel/bzmodules"
}

cleanup
revert
unpack
patch
pack
deploy
cleanup
echo "All tasks completed successfully."
