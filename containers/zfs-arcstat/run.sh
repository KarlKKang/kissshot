#!/bin/bash

docker run -it --rm --name zfs-arcstat --network none -v /proc:/host/proc:ro -v /sys:/host/sys:ro zfs-arcstat "$@"
