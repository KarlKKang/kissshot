#!/bin/bash

if [ -z "$MAJOR_VER" ]; then
    echo "Required environment variable MAJOR_VER is not set."
    exit 1
fi
if [ -z "$FULL_VER" ]; then
    echo "Required environment variable FULL_VER is not set."
    exit 1
fi
if [ -z "$ZFS_VER" ]; then
    echo "Required environment variable ZFS_VER is not set."
    exit 1
fi

set -e
cd "$(dirname "$0")"

PARENT_DIR="$(realpath ..)"

docker run -it --rm --name kernel-compiler -v kernel-compiler-keyring:/root/.gnupg -v "$PARENT_DIR/bzfirmware/usr/src/linux-6.6.78-Unraid:/data/patches" -v "${PWD}:/data/output"  \
    -e MAJOR_VER="$MAJOR_VER" -e FULL_VER="$FULL_VER" -e PATCH_DIR=/data/patches -e ZFS_VER="$ZFS_VER" -e OUT_DIR=/data/output kernel-compiler
