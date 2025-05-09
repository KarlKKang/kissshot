#!/bin/bash

set -e

if [[ "$#" -ne 2 ]]; then
    echo "Usage: $0 <BZFILE> <SRC_PATH>"
    exit 1
fi
if [ -f "/boot/backup/$1" ] || [ -f "/boot/backup/$1.sha256" ]; then
    echo "A previous version of $1 already exists."
    exit 1
fi

mkdir -p /boot/backup
mv "/boot/$1" "/boot/backup/$1"
mv "/boot/$1.sha256" "/boot/backup/$1.sha256"
mv "$2" "/boot/$1"
mv "$2.sha256" "/boot/$1.sha256"

echo "$1: deployed successfully"
