#!/bin/bash

set -e

if [ "$#" -ne 0 ]; then
    exec "$@"
fi

if [ -z "$MAJOR_VER" ]; then
    echo "Required environment variable MAJOR_VER is not set."
    exit 1
fi
if [ -z "$FULL_VER" ]; then
    echo "Required environment variable FULL_VER is not set."
    exit 1
fi
if [ -z "$PATCH_DIR" ]; then
    echo "Required environment variable PATCH_DIR is not set."
    exit 1
fi
if [ -z "$ZFS_VER" ]; then
    echo "Required environment variable ZFS_VER is not set."
    exit 1
fi
if [ -z "$OUT_DIR" ]; then
    echo "Required environment variable OUT_DIR is not set."
    exit 1
fi

SRC_WD="/usr/src/linux-${FULL_VER}-Unraid"

cd /root

curl -OL "https://cdn.kernel.org/pub/linux/kernel/v$MAJOR_VER.x/linux-$FULL_VER.tar.xz"
curl -OL "https://cdn.kernel.org/pub/linux/kernel/v$MAJOR_VER.x/linux-$FULL_VER.tar.sign"
unxz "linux-$FULL_VER.tar.xz"
gpg2 --trust-model tofu --verify "linux-$FULL_VER.tar.sign"
tar -xf "linux-$FULL_VER.tar"

mv "linux-$FULL_VER" "$SRC_WD"
cd "$SRC_WD"

cp -a "$PATCH_DIR/." ./
find . -type f -iname '*.patch' -print0 | xargs -n1 -0 patch -p1 -i

make olddefconfig
read -rp "Press enter to continue"
make "-j$(nproc)" bzImage
make "-j$(nproc)"
make "-j$(nproc)" modules

MODULE_DIR="/"
make INSTALL_MOD_PATH="$MODULE_DIR" modules_install

KERNEL_RELEASE="$(make -s kernelrelease)"

cd /root

curl -OL "https://github.com/openzfs/zfs/releases/download/zfs-$ZFS_VER/zfs-$ZFS_VER.tar.gz"
curl -OL "https://github.com/openzfs/zfs/releases/download/zfs-$ZFS_VER/zfs-$ZFS_VER.tar.gz.asc"
gpg2 --trust-model tofu --verify "zfs-$ZFS_VER.tar.gz.asc"
tar -xzf "zfs-$ZFS_VER.tar.gz"
cd "zfs-$ZFS_VER"

./autogen.sh
./configure \
    --with-linux="$MODULE_DIR/lib/modules/$KERNEL_RELEASE/build" \
    --with-linux-obj="$MODULE_DIR/lib/modules/$KERNEL_RELEASE/build" \
    --with-config=kernel
make -j"$(nproc)"
make -C module INSTALL_MOD_PATH=$MODULE_DIR modules_install

cp "$SRC_WD/arch/x86_64/boot/bzImage" "$OUT_DIR/bzimage"
cp "$SRC_WD/.config" "$PATCH_DIR/.config"
cp "$SRC_WD/System.map" "$PATCH_DIR/System.map"
rm -rf "$OUT_DIR/lib/modules"
mkdir -p "$OUT_DIR/lib/modules/$KERNEL_RELEASE"
cp -a "$MODULE_DIR/lib/modules/$KERNEL_RELEASE/." "$OUT_DIR/lib/modules/$KERNEL_RELEASE/"

echo "Kernel compiled successfully."
echo "You will be dropped into a shell. Exit with proper exit code when finished checking."
exec /bin/bash
