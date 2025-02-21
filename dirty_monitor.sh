#!/bin/bash

watch -n 1 grep -E '"^(Dirty:|Writeback:|MemFree:|Cached:)"' /proc/meminfo '|' tr '"\n"' '" "'
