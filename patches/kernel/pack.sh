#!/bin/bash

set -e
cd "$(dirname "$0")"

sha256sum ./bzimage | awk '{print $1}' >./bzimage.sha256

echo "kernel: files packed successfully"
