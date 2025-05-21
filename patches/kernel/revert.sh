#!/bin/bash

set -e

BZFILE="bzimage"
if [ ! -f "/boot/backup/$BZFILE" ] || [ ! -f "/boot/backup/$BZFILE.sha256" ]; then
    echo "kernel: $BZFILE: nothing to revert"
else
    mv -f "/boot/backup/$BZFILE" "/boot/$BZFILE"
    mv -f "/boot/backup/$BZFILE.sha256" "/boot/$BZFILE.sha256"
    rmdir --ignore-fail-on-non-empty /boot/backup
    echo "kernel: $BZFILE: revert completed"
fi

BZDIR="lib"
if [ ! -d "/boot_extra/deploy/$BZDIR" ]; then
    echo "kernel: $BZDIR: nothing to revert"
else
    rm -rf "/boot_extra/deploy/$BZDIR"
    echo "kernel: $BZDIR: revert completed"
fi
