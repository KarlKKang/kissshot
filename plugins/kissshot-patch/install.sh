#!/bin/bash

set -e

cd "$(dirname "$0")"

mkdir -p /boot/config/plugins/kissshot-patch
cp ./*.txz /boot/config/plugins/kissshot-patch

plugin install "$(realpath ./kissshot-patch.plg)" forced
