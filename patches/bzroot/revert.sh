#!/bin/bash

set -e

mv -f /boot/bzroot.old /boot/bzroot
mv -f /boot/bzroot.sha256.old /boot/bzroot.sha256

echo "Revert completed."
