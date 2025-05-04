#!/bin/bash

MAJOR_VER="6"
FULL_VER="6.6.78"
ZFS_VER="2.2.7"

set -e
cd "$(dirname "$0")"

PARENT_DIR="$(realpath ..)"

docker run -it --rm --name kernel-compiler -v kernel-compiler-keyring:/root/.gnupg -v "$PARENT_DIR/bzfirmware/usr/src/linux-6.6.78-Unraid:/data/patches:ro" -v "${PWD}:/data/output"  \
    -e MAJOR_VER=$MAJOR_VER -e FULL_VER=$FULL_VER -e PATCH_DIR=/data/patches -e ZFS_VER=$ZFS_VER -e OUT_DIR=/data/output kernel-compiler
