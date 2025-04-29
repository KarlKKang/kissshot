#!/bin/bash

set -e
cd "$(dirname "$0")"

BZFILE="bzroot" sh ../revert_helper.sh
