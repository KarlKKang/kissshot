#!/bin/bash

set -e

if [[ "$#" -ne 1 ]]; then
    echo "Usage: $0 <BZFILE>"
    exit 1
fi
if [ ! -f "/boot/backup/$1" ] || [ ! -f "/boot/backup/$1.sha256" ]; then
    echo "$1: nothing to revert"
    exit 0
fi

mv -f "/boot/backup/$1" "/boot/$1"
mv -f "/boot/backup/$1.sha256" "/boot/$1.sha256"
rmdir --ignore-fail-on-non-empty /boot/backup

echo "$1: revert completed"
