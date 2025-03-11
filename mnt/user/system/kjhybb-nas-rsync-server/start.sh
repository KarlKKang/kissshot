#!/bin/bash

docker run -d --name kjhybb-nas-rsync-server -p 8022:22 --restart always \
-v /mnt/user/kjhybb-nas:/data \
-v /mnt/user/appdata/kjhybb-nas-rsync-server:/config:ro \
-e PASSWORD_FILE=/config/password \
--memory 128m --memory-swap -1 \
rsync-server