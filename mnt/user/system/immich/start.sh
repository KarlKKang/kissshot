#!/bin/bash

set -e
cd "$(dirname "$0")"

docker run --rm -it \
    -v /var/run/docker.sock:/var/run/docker.sock \
    -v "$PWD:$PWD" \
    -w="$PWD" \
    docker compose up -d
