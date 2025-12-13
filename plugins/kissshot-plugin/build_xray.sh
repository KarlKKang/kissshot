#!/bin/bash

set -e
cd "$(dirname "$0")"

rm -rf source/xray
mkdir -p source/xray

docker pull golang:latest
docker run -it --rm --name xray-compiler --network host -v "${PWD}:/data" golang:latest /bin/bash /data/build_xray_helper.sh
curl -fL https://raw.githubusercontent.com/Loyalsoldier/v2ray-rules-dat/release/geoip.dat -o source/xray/geoip.dat
curl -fL https://raw.githubusercontent.com/Loyalsoldier/v2ray-rules-dat/release/geosite.dat -o source/xray/geosite.dat
