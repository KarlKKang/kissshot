#!/bin/bash

set -e
cd "$(dirname "$0")"

docker run --rm -it \
    -v /var/run/docker.sock:/var/run/docker.sock \
    -v /mnt/user/appdata/immich:/mnt/user/appdata/immich:ro \
    -v "$PWD:$PWD:ro" \
    -w="$PWD" \
    docker compose down
