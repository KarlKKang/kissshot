#!/bin/bash

set -e

BZDIR="usr"
rm -rf "/mnt/rpool/root/$BZDIR"
echo "bzfirmware: $BZDIR: revert completed"
