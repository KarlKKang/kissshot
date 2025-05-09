#!/bin/bash

set -e
cd "$(dirname "$0")"

BZFILE="bzroot"

sh ../tools/cp_bzfile.sh $BZFILE

dd if=./$BZFILE bs=512 "count=$(cpio -ivt -H newc <./$BZFILE 2>&1 >/dev/null | awk '{print $1}')" of=./microcode
mkdir -p ./root
cd ./root
dd if=../$BZFILE bs=512 "skip=$(cpio -ivt -H newc <../$BZFILE 2>&1 >/dev/null | awk '{print $1}')" | xzcat | cpio -i -d -H newc --no-absolute-filenames

echo "bzroot: unpacked successfully"
