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

abort() {
  /bin/echo "$1"
  if read -r -t 5 -p "Press ENTER to enter a shell..."; then
    /bin/echo "You will be dropped to a shell. Exit the shell to reboot."
    /bin/bash
  fi
  /bin/umount -a
  read -rp "Press ENTER key to reboot..."
  /bin/echo
  /sbin/reboot -fd
}

# Mount /proc if it is not already mounted:
if [[ ! -d /proc/sys ]]; then
  /sbin/mount -v proc /proc -t proc || abort "Cannot mount proc on /proc"
fi

# Mount /sys if it is not already mounted:
if [[ ! -d /sys/kernel ]]; then
  /sbin/mount -v sysfs /sys -t sysfs || abort "Cannot mount sysfs on /sys"
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
    'rw'|'ro')
      /sbin/mount -v -o "$EFIVARFS" -t efivarfs none /sys/firmware/efi/efivars || abort "Cannot mount efivarfs on /sys/firmware/efi/efivars"
      ;;
    esac
  fi
fi

# If /run exists, mount a tmpfs on it (unless the
# initrd has already done so):
if [[ -d /run ]]; then
  if ! /bin/grep -wq "tmpfs /run tmpfs" /proc/mounts; then
    /sbin/mount -v -t tmpfs tmpfs /run -o mode=0755,size=128M,nodev,nosuid,noexec || abort "Cannot mount tmpfs on /run"
  fi
fi

if ! /bin/grep -wq "devtmpfs /dev devtmpfs" /proc/mounts; then
  /sbin/mount -v -t devtmpfs -o size=8M devtmpfs /dev || abort "Cannot mount devtmpfs on /dev"
fi

# LimeTech - lets mount debugfs
/sbin/mount -v -t debugfs none /sys/kernel/debug || abort "Cannot mount debugfs on /sys/kernel/debug"

move_to_tmpfs() {
  local dir="$1"
  local mount_opt="$2"
  local tmp_dir
  if [[ -d "$dir" ]]; then
    if ! /bin/rmdir "$dir" 2>/dev/null; then
      tmp_dir="${dir}_tmp"
      while [[ -e "$tmp_dir" || -h "$tmp_dir" ]]; do
        tmp_dir="${tmp_dir}_tmp"
      done
      /bin/mv "$dir" "$tmp_dir" || abort "Cannot move $dir to $tmp_dir"
    fi
  fi
  /bin/mkdir "$dir" || abort "Cannot create $dir"
  /sbin/mount -v -t tmpfs -o "$mount_opt" tmpfs "$dir" || abort "Cannot mount tmpfs on $dir"
  if [[ -n "$tmp_dir" ]]; then
    shopt -s dotglob
    /bin/mv "$tmp_dir"/* "$dir" || abort "Cannot restore contents of $dir"
    shopt -u dotglob
    /bin/rmdir "$tmp_dir" || abort "Cannot remove $tmp_dir"
  fi
}

move_to_tmpfs /tmp "mode=1777,nosuid,nodev"
move_to_tmpfs /root "mode=0710"
move_to_tmpfs /var "mode=0755"
move_to_tmpfs /var/log "size=128m,mode=0755"

# make /var/lock a symlink to /run/lock
if [[ ! -d /run/lock ]]; then
  /bin/mkdir /run/lock || abort "Cannot create /run/lock"
  /bin/chmod 755 /run/lock || abort "Cannot set permissions on /run/lock"
fi
shopt -s dotglob
/bin/mv -f /var/lock/* /run/lock
shopt -u dotglob
/bin/rmdir /var/lock || abort "Cannot remove /var/lock"
/bin/ln -s /run/lock /var || abort "Cannot create symlink /var/lock -> /run/lock"

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

bzcheck "bzimage"
bzcheck "bzroot"
bzcheck "bzroot-gui"

# now that /usr is mounted make /etc/rc.d a symlink
/bin/rm -rf /etc/rc.d
/bin/ln -s /usr/local/etc/rc.d /etc || abort "Cannot create symlink /etc/rc.d -> /usr/local/etc/rc.d"

# set permissions for non vfat boot on /boot
/bin/chown -R root:root /boot/config
/usr/bin/find /boot/config -type d -exec /bin/chmod 700 {} \;
/usr/bin/find /boot/config -type f -exec /bin/chmod 600 {} \;

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
