#!/bin/bash

set -e
cd "$(dirname "$0")"

cd ./root
find . | cpio -o -H newc | xz --check=crc32 --x86 --lzma2=preset=9e >../bzroot.part
cd ..
cat ./microcode ./bzroot.part >./bzroot
sha256sum ./bzroot | awk '{print $1}' >./bzroot.sha256

echo "bzroot: files packed successfully"
