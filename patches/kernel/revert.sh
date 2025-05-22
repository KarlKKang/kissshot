#!/bin/bash

set -e

BZFILE="bzimage"
if [ ! -f "/boot/backup/$BZFILE" ] || [ ! -f "/boot/backup/$BZFILE.sha256" ]; then
    echo "kernel: $BZFILE: revert completed"
else
    mv -f "/boot/backup/$BZFILE" "/boot/$BZFILE"
    mv -f "/boot/backup/$BZFILE.sha256" "/boot/$BZFILE.sha256"
    rmdir --ignore-fail-on-non-empty /boot/backup
    echo "kernel: $BZFILE: revert completed"
fi

BZDIR="lib"
rm -rf "/boot/root/deploy/$BZDIR"
echo "kernel: $BZDIR: revert completed"
