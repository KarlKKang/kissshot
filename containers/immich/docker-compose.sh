#!/bin/bash

set -e
cd "$(dirname "$0")"

if git -c safe.directory='*' rev-parse &>/dev/null; then
    echo "Please run outside of a git repository."
    exit 1
fi

docker run -it --rm --name docker-compose --network none \
    -v /var/run/docker.sock:/var/run/docker.sock \
    -v /mnt/user/appdata/immich/caddy.env:/mnt/user/appdata/immich/caddy.env:ro \
    -v "$PWD:$PWD:ro" \
    -w="$PWD" \
    docker compose "$@"
