#!/bin/bash

set -e
cd "$(dirname "$0")"

rm -f ./bzfirmware
rm -f ./bzfirmware.sha256
rm -rf ./usr

echo "bzfirmware: cleanup completed"
