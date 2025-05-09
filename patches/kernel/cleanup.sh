#!/bin/bash

set -e
cd "$(dirname "$0")"

rm -f ./bzmodules
rm -f ./bzmodules.sha256
rm -f ./bzimage
rm -f ./bzimage.sha256
rm -rf ./lib

echo "kernel: cleanup completed"
