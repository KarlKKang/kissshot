import os

total_ram = 135045971968 # run `cat /proc/meminfo | grep MemTotal`, * 1024
total_reserved_ram = 24576 * 2 * 1024 * 1024
available_cpus = 14
total_cpus = 64

total_available_ram = total_ram - total_reserved_ram
percent_available_ram = total_available_ram / total_ram
percent_available_cpus = available_cpus / total_cpus

script_dir = os.path.dirname(os.path.realpath(__file__))
zfs_conf_path = os.path.join(script_dir, 'zfs.conf')
with open(zfs_conf_path, 'w') as f:
    # default: 10 (10% of system memory)
    f.write(f'options zfs zfs_arc_lotsfree_percent={round(10 * percent_available_ram)}\n')

    # default: max(1/2 of system memory, 67108864)
    zfs_arc_max = round(total_available_ram / 2)
    if zfs_arc_max > 67108864:
        f.write(f'options zfs zfs_arc_max={zfs_arc_max}\n')

    # default: max(1/32 of system memory, 33554432)
    zfs_arc_min = round(total_available_ram / 32)
    if zfs_arc_min > 33554432:
        f.write(f'options zfs zfs_arc_min={zfs_arc_min}\n')

    # default: max(1/64 of system memory, 512 KiB)
    zfs_arc_sys_free = round(total_available_ram / 64)
    if zfs_arc_sys_free > 512 * 1024:
        f.write(f'options zfs zfs_arc_sys_free={zfs_arc_sys_free}\n')

    # default: min(1/4 of system memory, 4 GiB)
    zfs_dirty_data_max_max_cap = 4 * 1024 * 1024 * 1024
    zfs_dirty_data_max_max = min(round(total_available_ram / 4), zfs_dirty_data_max_max_cap)
    if zfs_dirty_data_max_max < zfs_dirty_data_max_max_cap:
        f.write(f'options zfs zfs_dirty_data_max_max={zfs_dirty_data_max_max}\n')

    # default: 10% of system memory, capped at `zfs_dirty_data_max_max`
    zfs_dirty_data_max = round(0.1 * total_available_ram)
    if zfs_dirty_data_max < zfs_dirty_data_max_max:
        f.write(f'options zfs zfs_dirty_data_max={round(0.1 * total_available_ram)}\n')

    # default: 20 (i.e. 1/20 of system memory)
    f.write(f'options zfs zfs_scan_mem_lim_fact={round(20 * 1 / percent_available_ram)}\n')

    # default: max(number of online CPUs, 4)
    if available_cpus > 4:
        f.write(f'options zfs zfs_multilist_num_sublists={available_cpus}\n')

    # default: 75
    f.write(f'options zfs zfs_sync_taskq_batch_pct={round(75 * percent_available_cpus)}\n')

    # default: max(16 * number of CPUs, 64)
    zfs_zevent_len_max = 16 * available_cpus
    if zfs_zevent_len_max > 64:
        f.write(f'options zfs zfs_zevent_len_max={zfs_zevent_len_max}\n')

    # default: 100
    f.write(f'options zfs zfs_zil_clean_taskq_nthr_pct={round(100 * percent_available_cpus)}\n')

    # default: 80
    f.write(f'options zfs zio_taskq_batch_pct={round(80 * percent_available_cpus)}\n')
