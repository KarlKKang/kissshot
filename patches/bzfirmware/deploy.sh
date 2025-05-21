#!/bin/bash

set -e
cd "$(dirname "$0")"

BZDIR="usr"
if [ -d "/boot_extra/deploy/$BZDIR" ]; then
    echo "A previous version of $BZDIR already exists."
    exit 1
else
    mkdir -p /boot_extra/deploy
    mv "$BZDIR" "/boot_extra/deploy"
    echo "bzfirmware: $BZDIR: deployed successfully"
fi
