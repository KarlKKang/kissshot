#!/bin/bash

set -e

mv -f /boot/$BZFILE.old /boot/$BZFILE
mv -f /boot/$BZFILE.sha256.old /boot/$BZFILE.sha256

echo "Revert completed."
