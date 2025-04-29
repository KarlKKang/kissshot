#!/bin/bash

set -e
cd "$(dirname "$0")"

BZFILE="bzroot"

cp /boot/$BZFILE ./$BZFILE
HASH1=$(sha256sum ./$BZFILE)
HASH2=$(cat /boot/$BZFILE.sha256)
if [ "${HASH1:0:64}" != "${HASH2:0:64}" ]; then
    echo "$BZFILE hash mismatch"
    exit 1
fi

dd if=./$BZFILE bs=512 "count=$(cpio -ivt -H newc <./$BZFILE 2>&1 >/dev/null | awk '{print $1}')" of=./microcode
mkdir -p ./root
cd ./root
dd if=../$BZFILE bs=512 "skip=$(cpio -ivt -H newc <../$BZFILE 2>&1 >/dev/null | awk '{print $1}')" | xzcat | cpio -i -d -H newc --no-absolute-filenames

echo "All files unpacked successfully."
