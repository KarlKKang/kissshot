#!/bin/bash

set -e
cd "$(dirname "$0")"

mv /boot/bzroot /boot/bzroot.old
mv /boot/bzroot.sha256 /boot/bzroot.sha256.old
mv ./bzroot /boot/bzroot
mv ./bzroot.sha256 /boot/bzroot.sha256

echo "All files deployed successfully."
