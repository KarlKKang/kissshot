#!/bin/bash

set -e

if [[ "$#" -ne 1 ]]; then
    echo "Usage: $0 <BZFILE>"
    exit 1
fi
if [ -f "/boot/$1.old" ] || [ -f "/boot/$1.sha256.old" ]; then
    echo "A previous version of $1 already exists."
    exit 1
fi

BZFILE="$(dirname "$0")/../$1/$1"
mv "/boot/$1" "/boot/$1.old"
mv "/boot/$1.sha256" "/boot/$1.sha256.old"
mv "$BZFILE" "/boot/$1"
mv "$BZFILE.sha256" "/boot/$1.sha256"

echo "All files deployed successfully."
