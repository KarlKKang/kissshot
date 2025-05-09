#!/bin/bash

set -e
cd "$(dirname "$0")"

cat ../src/rc.S >./root/etc/rc.d/rc.S

echo "bzroot: patches applied successfully"
