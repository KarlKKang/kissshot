#!/bin/bash

docker run -it --rm --name featherine-builder --network host -v /mnt/user/appdata/featherine-builder/aws_credentials:/root/.aws/credentials:ro -v featherine-src:/root/src -w /root/src featherine-builder
