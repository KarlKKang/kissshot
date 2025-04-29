#!/bin/bash

set -e

if [ -f /boot/$BZFILE.old ] && [ -f /boot/$BZFILE.sha256.old ]; then
    echo "A previous version of $BZFILE already exists."
    echo "Do you want to discard the current version (y) or discard the previous version (n)?"
    read -r choice
    if [ "$choice" = "y" ] || [ "$choice" = "Y" ]; then
        rm -f /boot/$BZFILE
        rm -f /boot/$BZFILE.sha256
    elif [ "$choice" = "n" ] || [ "$choice" = "N" ]; then
        mv -f /boot/$BZFILE /boot/$BZFILE.old
        mv -f /boot/$BZFILE.sha256 /boot/$BZFILE.sha256.old
    else
        echo "Invalid choice. Exiting."
        exit 1
    fi
else
    mv /boot/$BZFILE /boot/$BZFILE.old
    mv /boot/$BZFILE.sha256 /boot/$BZFILE.sha256.old
fi

mv ./$BZFILE /boot/$BZFILE
mv ./$BZFILE.sha256 /boot/$BZFILE.sha256

echo "All files deployed successfully."
