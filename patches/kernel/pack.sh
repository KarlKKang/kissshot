#!/bin/bash

set -e
cd "$(dirname "$0")"

rm -f ./bzmodules
docker run -it --rm --name squashfs-tools -v "$PWD":/data squashfs-tools \
    mksquashfs /data/lib /data/bzmodules -comp xz -Xbcj x86
sha256sum ./bzmodules | awk '{print $1}' >./bzmodules.sha256
sha256sum ./bzimage | awk '{print $1}' >./bzimage.sha256

echo "All files packed successfully."
