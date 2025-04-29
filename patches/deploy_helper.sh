#!/bin/bash

set -e

if [ -f /boot/$BZFILE.old ] || [ -f /boot/$BZFILE.sha256.old ]; then
    echo "A previous version of $BZFILE already exists."
    exit 1
fi

mv /boot/$BZFILE /boot/$BZFILE.old
mv /boot/$BZFILE.sha256 /boot/$BZFILE.sha256.old
mv ./$BZFILE /boot/$BZFILE
mv ./$BZFILE.sha256 /boot/$BZFILE.sha256

echo "All files deployed successfully."
