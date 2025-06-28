#!/bin/bash

set -e

BZFILE="bzimage"
if [ ! -f "/boot/backup/$BZFILE" ] || [ ! -f "/boot/backup/$BZFILE.sha256" ]; then
    echo "$BZFILE: nothing to revert"
else
    mv -f "/boot/backup/$BZFILE" "/boot/$BZFILE"
    mv -f "/boot/backup/$BZFILE.sha256" "/boot/$BZFILE.sha256"
    rmdir --ignore-fail-on-non-empty /boot/backup
    echo "$BZFILE: revert completed"
fi
