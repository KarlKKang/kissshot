#!/bin/bash

set -e
cd "$(dirname "$0")"

PARENT_DIR="$(realpath ..)"

docker run -it --rm --name kernel-compiler -v kernel-compiler-keyring:/root/.gnupg -v "$PARENT_DIR/bzfirmware/usr/src/linux-6.6.78-Unraid:/data/patches:ro"  \
    -e MAJOR_VER=6 -e FULL_VER=6.6.78 -e PATCH_DIR=/data/patches -e ZFS_VER=2.2.7 kernel-compiler
