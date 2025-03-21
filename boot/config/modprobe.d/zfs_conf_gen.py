import os
import math

available_cpus = 28
total_cpus = 64
l2arc_tbw = 2400

percent_available_cpus = available_cpus / total_cpus

script_dir = os.path.dirname(os.path.realpath(__file__))
zfs_conf_path = os.path.join(script_dir, 'zfs.conf')
with open(zfs_conf_path, 'w') as f:
    # The defaults are accurate as of ZFS 2.3.1
    # https://openzfs.github.io/openzfs-docs/man/v2.3/4/zfs.4.html

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

    # default: 33554432
    #  customized so that the expected lifetime of the L2ARC is 5 years, rounded to the nearest multiple of 4 MiB
    l2arc_write_max = round(l2arc_tbw * 1000 * 1000 / 5 / 365 / 24 / 60 / 60 / 4) * 4 * 1024 * 1024
    if l2arc_write_max > 33554432:
        f.write(f'options zfs l2arc_write_max={l2arc_write_max}\n')
        f.write(f'options zfs l2arc_write_boost={l2arc_write_max}\n')