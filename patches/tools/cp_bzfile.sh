#!/bin/bash

set -e

if [[ "$#" -ne 1 ]]; then
    echo "Usage: $0 <BZFILE>"
    exit 1
fi

if [[ -f "./$1" ]]; then
    SHA256FILE="./$1.sha256"
else
    cp "/boot/$1" "./$1"
    SHA256FILE="/boot/$1.sha256"
fi

HASH1=$(sha256sum "./$1")
HASH2=$(cat "$SHA256FILE")
if [ "${HASH1:0:64}" != "${HASH2:0:64}" ]; then
    echo "$BZFILE hash mismatch"
    exit 1
fi
