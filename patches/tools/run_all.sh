#!/bin/bash

set -e

if [[ "$#" -ne 1 ]]; then
    echo "Usage: $0 <BZFILE>"
    exit 1
fi
SCRIPT_DIR="$(dirname "$0")"
BZ_SCRIPT_DIR="$SCRIPT_DIR/../$1"
if [[ ! -d "$BZ_SCRIPT_DIR" ]]; then
    echo "Error: No patch found for $1"
    exit 1
fi
sh "$BZ_SCRIPT_DIR/cleanup.sh"
sh "$SCRIPT_DIR/revert.sh" "$1"
sh "$BZ_SCRIPT_DIR/unpack.sh"
sh "$BZ_SCRIPT_DIR/patch.sh"
sh "$BZ_SCRIPT_DIR/pack.sh"
sh "$SCRIPT_DIR/deploy.sh" "$1"
sh "$BZ_SCRIPT_DIR/cleanup.sh"
