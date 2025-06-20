#!/bin/bash

set -e

if [ "$#" -ne 1 ]; then
    echo "Usage: $0 <SRC_DIR>"
    exit 1
fi
SRC_DIR=$(realpath "$1")

cd "$(dirname "$0")"

docker build -t featherine-builder --pull .
docker run -it --rm --name featherine-builder --network host -v /mnt/user/appdata/featherine-builder/aws_credentials:/root/.aws/credentials:ro -v "$SRC_DIR":/root/src -w /root/src featherine-builder
