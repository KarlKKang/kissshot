#!/bin/bash

set -e 
cd "$(dirname "$0")"

mkdir -p ./modinfo
modinfo zfs -0 > ./modinfo/zfs
modinfo spl -0 > ./modinfo/spl

docker build -t zfs-arcstat --pull .
