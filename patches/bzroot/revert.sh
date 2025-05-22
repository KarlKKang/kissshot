#!/bin/bash

set -e

BZFILE="bzroot"
if [ ! -f "/boot/backup/$BZFILE" ] || [ ! -f "/boot/backup/$BZFILE.sha256" ]; then
    echo "bzroot: $BZFILE: revert completed"
else
    mv -f "/boot/backup/$BZFILE" "/boot/$BZFILE"
    mv -f "/boot/backup/$BZFILE.sha256" "/boot/$BZFILE.sha256"
    rmdir --ignore-fail-on-non-empty /boot/backup
    echo "bzroot: $BZFILE: revert completed"
fi

rm -rf /boot/root/deploy
echo "bzroot: root: revert completed"
