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

if zfs list rpool/root@production >/dev/null 2>&1; then
    zfs rollback rpool/root@production
elif zfs list rpool/root@previous >/dev/null 2>&1; then
    zfs rollback rpool/root@previous
fi
echo "bzroot: root: revert completed"
