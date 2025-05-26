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

if [ ! -d ../bzfirmware/usr ]; then
    echo "Please unpack bzfirmware first."
    exit 1
fi
if [ ! -d ../bzroot/root ]; then
    echo "Please unpack bzroot first."
    exit 1
fi

PARENT_DIR="$(realpath ..)"

show_diff() {
    local file1="$1"
    local file2="$2"
    if ! cmp --silent "$file1" "$file2"; then
        echo "Differences found in $file1 and $file2:"
        diff -u "$file1" "$file2" || true
        read -rp "Continue? (y/n) " answer
        if [[ "$answer" != "y" && "$answer" != "Y" ]]; then
            exit 1
        fi
    fi
}

docker run -it --rm --name kernel-compiler --network host -v kernel-compiler-keyring:/root/.gnupg \
    -v "$PARENT_DIR/bzfirmware/usr/src/linux-${FULL_VER}-Unraid:/data/patches" -v "${PWD}:/data/output" -v "$PARENT_DIR/bzroot/root:/data/initramfs" \
    -e MAJOR_VER="$MAJOR_VER" -e FULL_VER="$FULL_VER" -e PATCH_DIR=/data/patches -e ZFS_VER="$ZFS_VER" -e OUT_DIR=/data/output kernel-compiler

show_diff ../src/.config "../bzfirmware/usr/src/linux-${FULL_VER}-Unraid/.config"

echo "kernel: patches applied successfully"
