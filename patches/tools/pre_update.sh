#!/bin/bash

set -e

rm -rf /boot/backup
rm -rf /boot_extra/previous
rsync -a /boot_extra/usr /boot_extra/previous/
rsync -a /boot_extra/lib /boot_extra/previous/

echo "The system is ready to be upgraded."
