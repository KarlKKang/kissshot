#!/bin/bash

set -e

if [[ "$#" -ne 1 ]]; then
    echo "Usage: $0 <BZFILE>"
    exit 1
fi
if [ ! -f "/boot/$1.old" ] || [ ! -f "/boot/$1.sha256.old" ]; then
    echo "No old files found. Nothing to revert."
    exit 0
fi

mv -f "/boot/$1.old" "/boot/$1"
mv -f "/boot/$1.sha256.old" "/boot/$1.sha256"

echo "Revert completed."
