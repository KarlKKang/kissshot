#!/bin/bash

# btrfs implicitly disable checksum on files with the C attribute
find . ! -type l -exec lsattr -d {} + | sed -n 's/^[^ ]*C[^ ]* //p'
