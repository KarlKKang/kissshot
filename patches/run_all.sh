#!/bin/bash

set -e

if [[ "$#" -ne 1 ]]; then
    echo "Usage: $0 <BZFILE>"
    exit 1
fi
SCRIPT_DIR="$(dirname "$0")/$1"
if [[ ! -d "$SCRIPT_DIR" ]]; then
    echo "Error: Directory $SCRIPT_DIR does not exist."
    exit 1
fi
sh "$SCRIPT_DIR/revert.sh"
sh "$SCRIPT_DIR/unpack.sh"
sh "$SCRIPT_DIR/patch.sh"
sh "$SCRIPT_DIR/pack.sh"
sh "$SCRIPT_DIR/deploy.sh"
sh "$SCRIPT_DIR/cleanup.sh"
