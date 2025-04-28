#!/bin/bash

set -e
cd "$(dirname "$0")"

cp /boot/bzroot ./bzroot
HASH1=$(sha256sum ./bzroot)
HASH2=$(cat /boot/bzroot.sha256)
if [ "${HASH1:0:64}" != "${HASH2:0:64}" ]; then
    echo "bzroot hash mismatch"
    exit 1
fi

dd if=./bzroot bs=512 "count=$(cpio -ivt -H newc <./bzroot 2>&1 >/dev/null | awk '{print $1}')" of=./microcode
mkdir -p ./root
cd ./root
dd if=../bzroot bs=512 "skip=$(cpio -ivt -H newc <../bzroot 2>&1 >/dev/null | awk '{print $1}')" | xzcat | cpio -i -d -H newc --no-absolute-filenames

echo "All files unpacked successfully."
