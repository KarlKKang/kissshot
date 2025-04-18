#!/bin/bash

set -e

if [ "$1" == "summary" ]; then
    exec /arc_summary "${@:2}"
else
    exec /arcstat "$@"
fi
