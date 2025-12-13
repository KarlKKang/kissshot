#!/bin/bash

set -e

cd /
git clone --recursive -b mod https://github.com/KarlKKang/Xray-core.git
cd /Xray-core
CGO_ENABLED=0 go build -o xray -trimpath -buildvcs=false -gcflags="all=-l=4" -ldflags="-s -w -buildid=" -v ./main
mv /Xray-core/xray /data/source/xray/xray
