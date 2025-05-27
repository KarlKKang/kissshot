# Unraid Patches

## `/usr` and `/lib`

Starting from [Unraid 6.12](https://docs.unraid.net/unraid-os/release-notes/6.12.0/#release-bz-file-differences), the `/usr` and `/lib` directories are mounted directly from the flash drive. This would allow `bzfirmware` and `bzmodules` to grow in size in the future without being limited by the size of RAM. 

However, under memory pressure, `/usr` and `/lib` will be evicted from RAM, and subsequent access will be re-read from the flash drive. This raises several concerns:

- Performance: Accessing files from flash is slow, resulting in long loading times for the WebGUI and other applications that rely on user-space binaries and libraries that live in those directories.
- Reliability: Unraid historically justifies flash drives as the only booting option with the argument that the OS is completely loaded into RAM, so the flash drive is not accessed (except for the config folder) after boot. The 6.12 update makes the flash drive a critical component of the system even after a successful boot, as `/usr` and `/lib` contain critical files for the OS to function. If the flash drive fails and the RAM copy is evicted, reading from the flash drive will fail, potentially causing crashes or data corruption.
- Integrity: This is typically not a problem, because `bzfirmware` and `bzmodules` use squashfs with XZ compression, which contains checksums. So any corruption in the data will manifest as a read error.

[Unraid 7.0](https://docs.unraid.net/unraid-os/release-notes/7.0.0/#excessive-flash-drive-activity-slows-the-system-down) introduces the `fastusr` functionality, so that `/usr` is always run in RAM to avoid the performance issues. However, `/lib` is still mounted from the flash. 

This patch unpacks the entire root onto a ZFS pool. NVMe SSDs are preferred for performance reasons, so the kernel is recompiled with the NVMe drivers built in. This provides much higher reliability and resiliency while also leaving plenty of headroom for future growth of the OS size. The XZ decompression overhead is also avoided. The mounted root is a ZFS clone that starts fresh on every boot, so no state will be preserved across reboots, just like the stock behavior.

More RAM should also be made available with this patch. Previously, the upper and work directories of the overlayfs were in RAM, which could be filled up with unpacked plugin files, especially as of right now the unraid-api comes with quite bloated npm packages. The initramfs is also removed when switching to the actual root.

> [!IMPORTANT]
> The snapshot is destroyed and recreated **on boot**. So if there are any sensitive files that rely on the root being stored in RAM and deleted immediately on power loss, extra care needs to be taken. One such example is `/root/keyfile` used to unlock the LUKS encrypted drives. To resolve this issue, this patch mounts `/root` as a tmpfs. This way the keyfile only lives in RAM.

## Boot Drive Silent Corruptions

The entire boot partition is formatted as FAT, which may be prone to silent corruptions. The `bz*` files are checksummed when booting, and the files required by plugins are checksummed when installed. The rest of the files, most notably the plain configuration files in the `config` folder, are not checksummed. This patch resolves the issue by mounting the entire `config` folder from a ZFS dataset.

### Non-Repeatable Silent Corruptions

To avoid non-repeatable silent corruptions, ideally the checksums should be verified against the data that is already read into the RAM, just before the data is used (like any sensible checksummed filesystems are doing). However, during boot only the **on-disk** `bz*` files are verified. Before this verification, the `bzimage` and `bzroot` files are already loaded into RAM, and after the verification, the `bzfirmware` and `bzmodules` files are **re-read** into RAM. Therefore if a non-repeatable silent corruption occurs during the actual loading of those files, the verification step will not be able to catch it.

Checksumming `bzfirmware` and `bzmodules` is not required after this patch since their content is loaded from the ZFS filesystem. As for the `bzimage` and `bzroot` files, they must be loaded before the init script is executed, so there is no simple way to properly verify them. However, it may not be necessary due to the following reasons:

- The `bzimage` and `bzroot` files are only loaded once during boot, unlike `bzfirmware` and `bzmodules` which are read again if they are evicted from RAM.
- The `bzroot` file contains two sections:
  - The first section is the microcode patch, in an uncompressed CPIO archive. Both the integrity and authenticity of the microcode patch are verified by the CPU itself, so it is not possible to apply a corrupted patch. It's easy to verify that the patch is applied by running `dmesg | grep microcode`. It should output something like `microcode: Current revision: ...`. It can be cross-checked against the list of current [AMD microcode patches](https://git.kernel.org/pub/scm/linux/kernel/git/firmware/linux-firmware.git/tree/amd-ucode/README) and [Intel microcode patches](https://www.intel.com/content/www/us/en/developer/topic-technology/software-security-guidance/processors-affected-consolidated-product-cpu-model.html).
  - The second section is the initramfs, compressed with XZ, with CRC32 checksums. The kernel should raise an error if the checksums do not match as it decompresses the file.
- The `bzimage` file also contains a compressed and an uncompressed section:
  - The first uncompressed section is a small stub that does some basic hardware initialization and decompresses the second section. There's basically no way to verify the integrity of this section, but any failure in this stage should be catastrophic, like a firmware failure. This is the same reason why we don't bother checking the bootloader itself.
  - The second section is the compressed kernel image. By default, Unraid ships it with LZMA compression. LZMA is not checksummed. Therefore, this patch recompiles the kernel with XZ compression, which is checksummed with CRC32. The kernel should raise an error if there's a checksum mismatch.

### Alternative Solutions

The [Unraid WebGUI repository](https://github.com/unraid/webgui) recently added support for the GRUB bootloader and boot partition formatted as XFS and Btrfs. This will probably take a while to become an official feature. But even if it does, the above problem is not entirely solved. Btrfs is a checksummed filesystem, but GRUB's implementation is minimal and does not actually verify the checksums during boot.

It seems that the only way to get a fully checksummed boot partition is with GRUB and ZFS. However, I quote from the [official Arch Linux wiki](https://wiki.archlinux.org/title/Install_Arch_Linux_on_ZFS): 

> Grub2's ZFS implementation, which is entirely independent of the OpenZFS implementation, is not known for reliability.

Therefore, I think the above solution has already achieved a decent level of integrity check for the Unraid system files, ~~while keeping the changes minimal~~. The next-level integrity check should not be about using a different filesystem for the boot partition, but to use secure boot. This way, both the bootloader and the kernel will be verified. But this is a much more complex solution requiring significant changes. I do not have a plan to look into this yet.

## Applying Patches

To apply the patches, on the Unraid system to be patched, first create a zpool and the root dataset:
```bash
zpool create -f \
-o ashift=12 \
-o autotrim=on \
-o autoexpand=on \
-O acltype=posix \
-O xattr=sa \
-O compression=on \
-O dnodesize=auto \
-O atime=off \
-O utf8only=on \
-O mountpoint=/mnt/root \
rpool vdev…

zfs create rpool/root
```
Then run the `tools/run_all.sh` script. The `kernel-compiler` and `squashfs-tools` docker images as well as the `kernel-compiler-keyring` docker volume are required. They can be found in `../containers`. The patch is version-specific. Currently it's for Unraid 7.1.2. Old `bzroot` and `bzimage` are kept in the `backup` folder on the flash drive. To move the config folder to the zpool, run `tools/move_config.sh`. The original config folder will be renamed to `config.old` as a backup. When finished, the zpool should be unmounted.

On a patched system, before updating Unraid to a newer version, run `tools/pre_update.sh`. This will remove the `backup` folder to avoid reverting back to the old version. It will also rename the production root snapshot to `rpool/root@previous`. Then it's safe to run Unraid's update tool as usual. Before rebooting, run `tools/run_all.sh` to apply the new patches.