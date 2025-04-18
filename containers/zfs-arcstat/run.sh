#!/bin/bash

docker run -it --rm --name zfs-arcstat -v /proc:/host/proc:ro -v /sys:/host/sys:ro zfs-arcstat "$@"
