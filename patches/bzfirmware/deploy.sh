#!/bin/bash

set -e
cd "$(dirname "$0")"

DEPLOY_DIR=/boot/root/staging
BZDIR="usr"
if [ -x "$DEPLOY_DIR/sbin/init" ]; then
    if [ -d "$DEPLOY_DIR/$BZDIR" ]; then
        rmdir "$DEPLOY_DIR/$BZDIR" || {
            echo "A previous version of $BZDIR already exists."
            exit 1
        }
    fi
    mv "$BZDIR" "$DEPLOY_DIR"
    chown root:root "$DEPLOY_DIR/$BZDIR"
    chmod 755 "$DEPLOY_DIR/$BZDIR"
    echo "bzfirmware: $BZDIR: deployed successfully"
else
    echo "Please deploy bzroot first."
    exit 1
fi
