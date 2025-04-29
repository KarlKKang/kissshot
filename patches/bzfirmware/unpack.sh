#!/bin/bash

set -e
cd "$(dirname "$0")"

BZFILE="bzfirmware"

cp /boot/$BZFILE ./$BZFILE
HASH1=$(sha256sum ./$BZFILE)
HASH2=$(cat /boot/$BZFILE.sha256)
if [ "${HASH1:0:64}" != "${HASH2:0:64}" ]; then
    echo "$BZFILE hash mismatch"
    exit 1
fi

mkdir -p ./usr
docker run -it --rm --name squashfs-tools -v "$PWD":/data squashfs-tools \
    unsquashfs -d /data/usr /data/$BZFILE

echo "All files unpacked successfully."
