#!/bin/bash

set -e
cd "$(dirname "$0")"

if [[ "$#" -ne 1 ]]; then
    echo "Usage: $0 <BZFILE>"
    exit 1
fi

cp "/boot/$1" "./$1"
HASH1=$(sha256sum "./$1")
HASH2=$(cat "/boot/$1.sha256")
if [ "${HASH1:0:64}" != "${HASH2:0:64}" ]; then
    echo "$BZFILE hash mismatch"
    exit 1
fi
