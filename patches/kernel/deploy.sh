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

DEPLOY_DIR=/mnt/rpool/root
BZDIR="lib"
if [ -x "$DEPLOY_DIR/sbin/init" ]; then
    rm -rf "${DEPLOY_DIR:?}/$BZDIR"
    mv "$BZDIR" "$DEPLOY_DIR"
    chown root:root "$DEPLOY_DIR/$BZDIR"
    chmod 755 "$DEPLOY_DIR/$BZDIR"
    echo "kernel: $BZDIR: deployed successfully"
else
    echo "Please deploy bzroot first."
    exit 1
fi
