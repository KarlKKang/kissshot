#!/bin/bash

set -e
cd "$(dirname "$0")"

curl -L -o caddy 'https://caddyserver.com/api/download?os=linux&arch=amd64&p=github.com%2Fcaddy-dns%2Fcloudflare'
chmod +x caddy
