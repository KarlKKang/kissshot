#!/bin/bash

set -e
cd "$(dirname "$0")"

cd ./root
find . | cpio -o -H newc | xz --check=crc32 --ia64 --lzma2=preset=9e "--threads=$(nproc --all)" >../bzroot.part
cd ..
cat ./microcode ./bzroot.part >./bzroot
sha256sum ./bzroot | awk '{print $1}' >./bzroot.sha256

echo "All files packed successfully."
