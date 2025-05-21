#!/bin/bash

set -e

BZDIR="usr"
if [ ! -d "/boot_extra/deploy/$BZDIR" ]; then
    echo "bzfirmware: $BZDIR: nothing to revert"
else
    rm -rf "/boot_extra/deploy/$BZDIR"
    echo "bzfirmware: $BZDIR: revert completed"
fi
