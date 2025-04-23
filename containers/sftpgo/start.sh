#!/bin/bash

docker run -d --name sftpgo -p 8080:8080 -p 2022:2022 --restart always \
    -v /mnt/user/appdata/sftpgo/data:/srv/sftpgo \
    -v /mnt/user/appdata/sftpgo/home:/var/lib/sftpgo \
    -v /mnt/user/media:/data/media:ro \
    --memory 128m --memory-swap -1 \
    drakkan/sftpgo:latest
