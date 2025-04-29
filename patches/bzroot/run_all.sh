#!/bin/bash

set -e

SCRIPT_DIR="$(dirname "$0")"
sh "$SCRIPT_DIR/unpack.sh"
sh "$SCRIPT_DIR/patch.sh"
sh "$SCRIPT_DIR/pack.sh"
sh "$SCRIPT_DIR/deploy.sh"
sh "$SCRIPT_DIR/cleanup.sh"
