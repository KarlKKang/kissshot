import os
import math

available_cpus = 32
total_cpus = 64

percent_available_cpus = available_cpus / total_cpus

script_dir = os.path.dirname(os.path.realpath(__file__))
zfs_conf_path = os.path.join(script_dir, 'zfs.conf')
with open(zfs_conf_path, 'w') as f:
    # The defaults are accurate as of ZFS 2.3.1
    # https://openzfs.github.io/openzfs-docs/man/v2.3/4/zfs.4.html

    # ZFS calculates the CPU percentage based on the number of online CPUs:
    #  /sys/devices/system/cpu/online
    # This does not account for isolcpus.

    # default: 50
    f.write(f'options zfs metaslab_preload_pct={math.ceil(50 * percent_available_cpus)}\n')

    # default: max(number of online CPUs, 4)
    f.write(f'options zfs zfs_multilist_num_sublists={max(available_cpus, 4)}\n')

    # default: 100
    f.write(f'options zfs zfs_zil_clean_taskq_nthr_pct={math.ceil(100 * percent_available_cpus)}\n')

    # default: 80
    f.write(f'options zfs zio_taskq_batch_pct={math.ceil(80 * percent_available_cpus)}\n')

    # default: max(number of online CPUs, 32)
    f.write(f'options zfs zvol_threads={max(available_cpus, 32)}\n')

    # default: 0
    f.write('options zfs l2arc_exclude_special=1\n')
