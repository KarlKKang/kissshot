#!/bin/bash

set -e

cd "$(dirname "$0")"

mkdir -p /boot/config/plugins/kissshot-plugin
cp ./*.txz /boot/config/plugins/kissshot-plugin

plugin install "$(realpath ./kissshot-plugin.plg)" forced
