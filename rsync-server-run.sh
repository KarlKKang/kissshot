#!/bin/bash

set -e

cd /mnt/user/system/rsync-server-source

docker build -t rsync-server .

docker run -d --name kjhybb-nas-rsync-server -p 8022:22 --restart always \
-v /mnt/user/kjhybb-nas:/data \
-v /mnt/user/appdata/kjhybb-nas-rsync-server:/config:ro \
-e PASSWORD_FILE=/config/password \
rsync-server