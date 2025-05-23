#!/bin/bash

# btrfs implicitly disable checksum on files with the C attribute
find . -xdev -type f,d -exec lsattr -d {} + | sed -n 's/^[^ ]*C[^ ]* //p'
