#!/bin/bash

set -e

BZDIR="usr"
rm -rf "/boot/root/deploy/$BZDIR"
echo "bzfirmware: $BZDIR: revert completed"
