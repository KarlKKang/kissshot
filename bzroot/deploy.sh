#!/bin/bash

set -e
cd "$(dirname "$0")"

if [ -f /boot/bzroot.old ] && [ -f /boot/bzroot.sha256.old ]; then
    echo "A previous version of bzroot already exists."
    echo "Do you want to discard the current version (y) or discard the previous version (n)?"
    read -r choice
    if [ "$choice" = "y" ] || [ "$choice" = "Y" ]; then
        rm -f /boot/bzroot
        rm -f /boot/bzroot.sha256
    elif [ "$choice" = "n" ] || [ "$choice" = "N" ]; then
        mv -f /boot/bzroot /boot/bzroot.old
        mv -f /boot/bzroot.sha256 /boot/bzroot.sha256.old
    else
        echo "Invalid choice. Exiting."
        exit 1
    fi
else
    mv /boot/bzroot /boot/bzroot.old
    mv /boot/bzroot.sha256 /boot/bzroot.sha256.old
fi

mv ./bzroot /boot/bzroot
mv ./bzroot.sha256 /boot/bzroot.sha256

echo "All files deployed successfully."
