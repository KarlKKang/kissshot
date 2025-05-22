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

DEPLOY_DIR=/boot/root/deploy
if [ -d "$DEPLOY_DIR" ]; then
    rmdir "$DEPLOY_DIR" || {
        echo "A previous version of root already exists."
        exit 1
    }
fi
mv ./root "$DEPLOY_DIR"
chown root:root "$DEPLOY_DIR"
chmod 755 "$DEPLOY_DIR"
echo "bzroot: root: deployed successfully"
