#!/bin/bash

set -e
cd "$(dirname "$0")"

BZFILE="bzroot"
if [ -f "/boot/backup/$BZFILE" ] || [ -f "/boot/backup/$BZFILE.sha256" ]; then
    echo "A previous version of $BZFILE already exists."
    exit 1
else
    mkdir -p /boot/backup
    mv "/boot/$BZFILE" "/boot/backup/$BZFILE"
    mv "/boot/$BZFILE.sha256" "/boot/backup/$BZFILE.sha256"
    mv "$BZFILE" "/boot/$BZFILE"
    mv "$BZFILE.sha256" "/boot/$BZFILE.sha256"
    echo "bzroot: $BZFILE: deployed successfully"
fi

rm -rf ./root/lib
DEPLOY_DIR=/mnt/rpool/root
rsync -a --delete ./root/ "$DEPLOY_DIR"
chown root:root "$DEPLOY_DIR"
chmod 755 "$DEPLOY_DIR"
echo "bzroot: root: deployed successfully"
