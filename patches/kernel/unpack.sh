#!/bin/bash

set -e
cd "$(dirname "$0")"

if [ ! -d ../bzfirmware/usr ]; then
    echo "Please run the bzfirmware unpack script first."
    exit 1
fi

BZFILE="bzmodules"

sh ../tools/cp_bzfile.sh $BZFILE

mkdir -p ./lib
docker run -it --rm --name squashfs-tools --network none -v "$PWD":/data squashfs-tools \
    unsquashfs -d /data/lib /data/$BZFILE

echo "kernel: unpacked successfully"
