#!/bin/bash

set -e

if [ ! -f /boot/$BZFILE.old ] || [ ! -f /boot/$BZFILE.sha256.old ]; then
    echo "No old files found. Nothing to revert."
    exit 0
fi

mv -f /boot/$BZFILE.old /boot/$BZFILE
mv -f /boot/$BZFILE.sha256.old /boot/$BZFILE.sha256

echo "Revert completed."
