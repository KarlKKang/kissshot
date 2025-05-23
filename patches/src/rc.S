#!/bin/bash
#
# script: rc.S
#
# System initialization script.
# Mostly written by: Patrick J. Volkerding, <volkerdi@slackware.com>
#
# LimeTech - modified for Unraid OS
# Bergware - modified for Unraid OS, October 2023

# Set the path.
PATH=/usr/local/sbin:/usr/sbin:/sbin:/usr/local/bin:/usr/bin:/bin

# Mount /proc if it is not already mounted:
if [[ ! -d /proc/sys ]]; then
  /sbin/mount -v proc /proc -n -t proc 2>/dev/null
fi

# Mount /sys if it is not already mounted:
if [[ ! -d /sys/kernel ]]; then
  /sbin/mount -v sysfs /sys -n -t sysfs 2>/dev/null
fi

# The efivarfs filesystem is used for reading and writing EFI variables, such
# as the boot menu entries. By default efivarfs will be mounted read-write on
# the /sys/firmware/efi/efivars directory. To modify this behavior, edit the
# file: /etc/default/efivarfs
# Only try to mount if this directory exists (so the kernel supports efivarfs):
if [[ -d /sys/firmware/efi/efivars ]]; then
  # Only try to mount if efivarfs is not already mounted:
  if ! /sbin/mount | /bin/grep -wq efivarfs; then
    # Mount according to /etc/default/efivarfs:
    if [[ -r /etc/default/efivarfs ]]; then
      . /etc/default/efivarfs
    else # default
      EFIVARFS="rw"
    fi
    case "$EFIVARFS" in
    'rw')
      /sbin/mount -o rw -t efivarfs none /sys/firmware/efi/efivars
      ;;
    'ro')
      /sbin/mount -o ro -t efivarfs none /sys/firmware/efi/efivars
      ;;
    esac
  fi
fi

# If /run exists, mount a tmpfs on it (unless the
# initrd has already done so):
if [[ -d /run ]]; then
  if ! /bin/grep -wq "tmpfs /run tmpfs" /proc/mounts; then
    /sbin/mount -v -n -t tmpfs tmpfs /run -o mode=0755,size=128M,nodev,nosuid,noexec
  fi
fi

if ! /bin/grep -wq "devtmpfs /dev devtmpfs" /proc/mounts; then
  /sbin/mount -v -n -t devtmpfs -o size=8M devtmpfs /dev
fi

# LimeTech - lets mount debugfs
/sbin/mount -v -t debugfs none /sys/kernel/debug

# LimeTech - determine if the 'unraidlabel' kernel append parameter was
# provided to override which device is mounted for /boot (default: UNRAID)
UNRAIDROOT=
set -- $(/bin/cat /proc/cmdline)
for x in "$@"; do
  case "$x" in
  root=*)
    UNRAIDROOT="${x#root=}"
    ;;
  esac
done

abort() {
  /bin/echo "$1"
  /bin/echo "You will be dropped to a shell. Exit the shell to reboot."
  /bin/bash
  /bin/umount -a
  read -p "Press ENTER key to reboot..."
  /bin/echo
  /sbin/reboot -fd
}

# check initial files used to boot
bzcheck(){
  local BZFILE=$1
  local BZFILE_PATH="/boot/$1"
  if [[ -f /boot/config/skipbzcheck ]]; then
    /bin/echo "Skipping $BZFILE checksum verification"
    return
  fi
  /bin/echo "Verifying $BZFILE checksum ..."
  [[ -f "$BZFILE_PATH" ]] || abort "$BZFILE not present"
  local BZFILECHK="$BZFILE.sha256"
  [[ -f "/boot/$BZFILECHK" ]] || abort "$BZFILECHK not present"
  local HASH1
  HASH1=$(/bin/sha256sum "$BZFILE_PATH") || abort "Cannot checksum $BZFILE"
  local HASH2
  HASH2=$(/bin/cat "/boot/$BZFILECHK") || abort "Cannot read $BZFILECHK"
  [[ ${HASH1:0:64} != "${HASH2:0:64}" ]] && abort "$BZFILE checksum error"
}

if [[ $UNRAIDROOT == "" ]]; then
  bzcheck "bzimage"
  bzcheck "bzroot"
  bzcheck "bzroot-gui"

  # now that /usr is mounted make /etc/rc.d a symlink
  /bin/rm -rf /etc/rc.d
  /bin/ln -sf /usr/local/etc/rc.d /etc

  # move /var/log to a tmpfs
  /bin/mv -f /var/log/* /var/empty
  /sbin/mount -t tmpfs -o size=128m,mode=0755 tmpfs /var/log
  /bin/mv -f /var/empty/* /var/log
else
  /bin/echo "Checking root filesystem"
  /sbin/fsck -C -a $UNRAIDROOT
  RETVAL=$?
  [[ $RETVAL -ge 2 ]] && abort "fsck failed with return value $RETVAL"
  # Remount the root filesystem in read-write mode
  /bin/echo "Remounting $UNRAIDROOT with read-write enabled."
  /sbin/mount -w -v -n -o remount /
  RETVAL=$?
  [[ $RETVAL -gt 0 ]] && abort "failed to remount $UNRAIDROOT r/w with return value $RETVAL"
  [[ ! -f /etc/rc.d/rc.S.cont ]] && abort "unable to continue - you must remove 'root=$UNRAIDROOT' from syslinux.cfg"
fi

# mount /root as an overlayfs
ROOT_OVERLAY_DIR=/var/local/root_overlay
/bin/mkdir -p "$ROOT_OVERLAY_DIR"
/sbin/mount -v -t tmpfs -o mode=0755 tmpfs "$ROOT_OVERLAY_DIR"
/bin/mkdir -p "$ROOT_OVERLAY_DIR/upperdir" "$ROOT_OVERLAY_DIR/workdir"
/usr/bin/chmod 710 "$ROOT_OVERLAY_DIR"/*
/sbin/mount -v -t overlay overlay -o "lowerdir=/root,upperdir=$ROOT_OVERLAY_DIR/upperdir,workdir=$ROOT_OVERLAY_DIR/workdir" "/root" || abort "cannot mount overlayfs for /root"
/usr/bin/chmod 710 /root

# set permissions for non vfat boot on /boot
/usr/bin/chown -R root:root /boot/config
/usr/bin/find /boot/config -type d -exec /usr/bin/chmod 700 {} \;
/usr/bin/find /boot/config -type f -exec /usr/bin/chmod 600 {} \;

. /etc/unraid-version
# log all PHP warnings (for beta|rc releases)
if [[ -f /etc/php.d/errors-php.ini && $version =~ -(beta|rc) ]]; then
  sed -i 's/^error_reporting=.*/error_reporting=E_ALL/' /etc/php.d/errors-php.ini
fi
# invoke testing hook (for -(beta|rc).x.y releases)
if [[ -f /boot/config/rc.S.extra && $version =~ -(beta|rc)\.[0-9]+\.[0-9]+$ ]]; then
  . /boot/config/rc.S.extra
fi
# and continue in separate script
. /etc/rc.d/rc.S.cont
