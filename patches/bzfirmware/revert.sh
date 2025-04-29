#!/bin/bash

set -e
cd "$(dirname "$0")"

BZFILE="bzfirmware" sh ../revert_helper.sh
