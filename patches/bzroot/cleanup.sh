#!/bin/bash

set -e
cd "$(dirname "$0")"

rm -f ./microcode
rm -f ./bzroot
rm -f ./bzroot.sha256
rm -f ./bzroot.part
rm -rf ./root

echo "bzroot: cleanup completed"
