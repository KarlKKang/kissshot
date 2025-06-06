#!/bin/bash

set -e
cd "$(dirname "$0")"

docker run -it --rm --name docker-compose --network none \
    -v /var/run/docker.sock:/var/run/docker.sock \
    -v "$PWD:$PWD:ro" \
    -w="$PWD" \
    docker compose "$@"
