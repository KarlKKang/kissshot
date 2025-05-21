#!/bin/bash

set -e
cd "$(dirname "$0")"

BZFILE="bzimage"
if [ -f "/boot/backup/$BZFILE" ] || [ -f "/boot/backup/$BZFILE.sha256" ]; then
    echo "A previous version of $BZFILE already exists."
    exit 1
else
    mkdir -p /boot/backup
    mv "/boot/$BZFILE" "/boot/backup/$BZFILE"
    mv "/boot/$BZFILE.sha256" "/boot/backup/$BZFILE.sha256"
    mv "$BZFILE" "/boot/$BZFILE"
    mv "$BZFILE.sha256" "/boot/$BZFILE.sha256"
    echo "kernel: $BZFILE: deployed successfully"
fi

BZDIR="lib"
if [ -d "/boot_extra/deploy/$BZDIR" ]; then
    echo "A previous version of $BZDIR already exists."
    exit 1
else
    mkdir -p /boot_extra/deploy
    mv "$BZDIR" "/boot_extra/deploy"
    echo "kernel: $BZDIR: deployed successfully"
fi
