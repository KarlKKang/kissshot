#!/bin/bash

set -e
cd "$(dirname "$0")"

DEPLOY_DIR=/mnt/rpool/root
BZDIR="usr"
if [ -x "$DEPLOY_DIR/sbin/init" ]; then
    rm -rf "${DEPLOY_DIR:?}/$BZDIR"
    mv "$BZDIR" "$DEPLOY_DIR"
    chown root:root "$DEPLOY_DIR/$BZDIR"
    chmod 755 "$DEPLOY_DIR/$BZDIR"
    echo "bzfirmware: $BZDIR: deployed successfully"
else
    echo "Please deploy bzroot first."
    exit 1
fi
