# Unraid Patches

## `/usr` and `/lib`

Starting from [Unraid 6.12](https://docs.unraid.net/unraid-os/release-notes/6.12.0/#release-bz-file-differences), the `/usr` and `/lib` directories are mounted directly from the flash drive. This would allow `bzfirmware` and `bzmodules` to grow in size in the future without being limited by the size of RAM. 

However, under memory pressure, `/usr` and `/lib` will be evicted from RAM, and subsequent access will be re-read from the flash drive. This raises several concerns:

- Performance: Accessing files from flash is slow, resulting in long loading times for the WebGUI and other applications that rely on user-space binaries and libraries that live in those directories.
- Reliability: Unraid historically justifies flash drives as the only booting option with the argument that the OS is completely loaded into RAM, so the flash drive is not accessed (except for the config folder) after boot. The 6.12 update makes the flash drive a critical component of the system even after a successful boot, as `/usr` and `/lib` contain critical files for the OS to function. If the flash drive fails and the RAM copy is evicted, reading from the flash drive will fail, potentially causing crashes or data corruption.
- Integrity: This is typically not a problem, because `bzfirmware` and `bzmodules` use squashfs with XZ compression, which contains checksums. So any corruption in the data will manifest as a read error.

[Unraid 7.0](https://docs.unraid.net/unraid-os/release-notes/7.0.0/#excessive-flash-drive-activity-slows-the-system-down) introduces the `fastusr` functionality, so that `/usr` is always run in RAM to avoid the performance issues. However, `/lib` is still mounted from the flash. 

This patch puts the `/usr` and `/lib` directories uncompressed onto a Btrfs device (or multi-device RAID). NVMe SSDs are preferred for performance reasons, so the kernel is recompiled with the NVMe drivers built in. This provides much higher reliability and resiliency while also leaving plenty of headroom for future growth of the directories. The XZ decompression overhead is also avoided. The Btrfs filesystem should be labelled as `UNRAID_EXTRA`, and will be mounted at `/boot_extra`. The `/usr` and `/lib` directories are still mounted as overlayfs, and will start fresh on every boot, just like the native behavior. The upper layer and workdir of the overlayfs are also moved to the Btrfs device, which should further reduce RAM usage, especially as of right now the unraid-api comes with quite bloated npm packages.

## Boot Drive Silent Corruptions

The entire boot partition is formatted as FAT, which may be prone to silent corruptions. The `bz*` files are checksummed when booting, and the files required by plugins are checksummed when installed. The rest of the files, most notably the plain configuration files in the `config` folder, are not checksummed. This patch resolves the issue by mounting the entire `config` folder from the Btrfs device.

### Non-Repeatable Silent Corruptions

To avoid non-repeatable silent corruptions, ideally the checksums should be verified against the data that is already read into the RAM, just before the data is used (like any sensible checksummed filesystems are doing). However, during boot only the **on-disk** `bz*` files are verified. Before this verification, the `bzimage` and `bzroot` files are already loaded into RAM, and after the verification, the `bzfirmware` and `bzmodules` files are **re-read** into RAM. Therefore if a non-repeatable silent corruption occurs during the actual loading of those files, the verification step will not be able to catch it.

Checksumming `bzfirmware` and `bzmodules` is not required after this patch since their content is loaded onto the Btrfs device. As for the `bzimage` and `bzroot` files, they must be loaded before the init script is executed, so there is no simple way to properly verify them. However, it may not be necessary due to the following reasons:

- The `bzimage` and `bzroot` files are only loaded once during boot, unlike `bzfirmware` and `bzmodules` which are read again if they are evicted from RAM.
- The `bzroot` file contains two sections:
  - The first section is the microcode patch, in an uncompressed CPIO archive. Both the integrity and authenticity of the microcode patch are verified by the CPU itself, so it is not possible to apply a corrupted patch. It's easy to verify that the patch is applied by running `dmesg | grep microcode`. It should output something like `microcode: Current revision: ...`. It can be cross-checked against the list of current [AMD microcode patches](https://git.kernel.org/pub/scm/linux/kernel/git/firmware/linux-firmware.git/tree/amd-ucode/README) and [Intel microcode patches](https://www.intel.com/content/www/us/en/developer/topic-technology/software-security-guidance/processors-affected-consolidated-product-cpu-model.html).
  - The second section is the root filesystem, compressed with XZ, with CRC32 checksums. The kernel should raise an error if the checksums do not match as it decompresses the file.
- The `bzimage` file also contains a compressed and an uncompressed section:
  - The first uncompressed section is a small stub that does some basic hardware initialization and decompresses the second section. There's basically no way to verify the integrity of this section, but any failure in this stage should be catastrophic, like a hardware failure. This is the same reason why we don't bother with the bootloader itself.
  - The second section is the compressed kernel image. By default, Unraid ships it with LZMA compression. LZMA is not checksummed. Therefore, this patch recompiles the kernel with XZ compression, which is checksummed with CRC32. The kernel should raise an error if there's a checksum mismatch.

### Alternative Solutions

The [Unraid WebGUI repository](https://github.com/unraid/webgui) recently added support for the GRUB bootloader and boot partition formatted as XFS and Btrfs. This will probably take a while to become an official feature. But even if it does, the above problem is not entirely solved. Btrfs is a checksummed filesystem, but GRUB's implementation is minimal and does not actually verify the checksums during boot. So the integrity check in this solution is no better than this patch could offer.

It seems that the only way to get a fully checksummed boot partition is with GRUB and ZFS. However, I quote from the [official Arch Linux wiki](https://wiki.archlinux.org/title/Install_Arch_Linux_on_ZFS): 

> Grub2's ZFS implementation, which is entirely independent of the OpenZFS implementation, is not known for reliability.

Therefore, I think the above solution has already achieved a decent level of integrity check for the Unraid system files, while keeping the changes minimal. The next-level integrity check should not be about using a different filesystem for the boot partition, but to use secure boot. This way, both the bootloader and the kernel will be verified. But this is a much more complex solution requiring significant changes. I do not have a plan to look into this yet.

## Applying Patches

To apply the patches, just run the `tools/run_all.sh` script on the Unraid system to be patched. The `kernel-compiler` and `squashfs-tools` docker images as well as the `kernel-compiler-keyring` docker volume are required. They can be found in `../containers`. The patch is version-specific. Currently it's for Unraid 7.1.2. Old `bzroot` and `bzimage` are kept in the `backup` folder on the flash drive.

To move the config folder to the Btrfs device, run `tools/move_config.sh` on the Unraid system. The original config folder will be renamed to `config.old` as a backup.

Before updating Unraid to a newer version, run `tools/pre_update.sh`. This will remove the `backup` folder to avoid reverting back to the old version. It will also copy the `usr` and `lib` to the `previous` folder on the extra device.