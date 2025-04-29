#!/bin/bash

set -e
cd "$(dirname "$0")"

BZFILE="bzfirmware" sh ../deploy_helper.sh
