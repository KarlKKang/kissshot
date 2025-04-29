#!/bin/bash

set -e
cd "$(dirname "$0")"

rm -f ./bzfirmware
docker run -it --rm --name squashfs-tools -v "$PWD":/data squashfs-tools \
    mksquashfs /data/usr /data/bzfirmware -comp xz -Xbcj x86
sha256sum ./bzfirmware | awk '{print $1}' >./bzfirmware.sha256

echo "All files packed successfully."
