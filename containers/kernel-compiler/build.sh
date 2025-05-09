#!/bin/bash

set -e 
cd "$(dirname "$0")"

docker build -t kernel-compiler --pull .
