#!/bin/bash

set -e
cd "$(dirname "$0")"

BZFILE="bzfirmware"

sh ../tools/cp_bzfile.sh $BZFILE

mkdir -p ./usr
docker run -it --rm --name squashfs-tools -v "$PWD":/data squashfs-tools \
    unsquashfs -d /data/usr /data/$BZFILE

echo "bzfirmware: unpacked successfully"
