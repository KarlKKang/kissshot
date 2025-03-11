#!/bin/bash

set -e
cd "$(dirname "$0")"

if [ ! -d ./source ]; then
    git clone https://github.com/KarlKKang/rsync-server.git source
    cd source
else
    cd source
    git pull
fi

docker build -t rsync-server .