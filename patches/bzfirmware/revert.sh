#!/bin/bash

set -e

BZDIR="usr"
rm -rf "/boot/root/staging/$BZDIR"
echo "bzfirmware: $BZDIR: revert completed"
