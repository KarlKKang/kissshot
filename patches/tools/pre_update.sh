#!/bin/bash

set -e

rm -rf /boot/backup
if zfs list rpool/root@previous >/dev/null 2>&1; then
    zfs destroy rpool/root@previous
fi
zfs rename rpool/root@production rpool/root@previous

echo "The system is ready to be upgraded."
