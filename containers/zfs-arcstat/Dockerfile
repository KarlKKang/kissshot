FROM python:3-slim

COPY arcstat /arcstat
COPY arc_summary /arc_summary
COPY modinfo/zfs /modinfo/zfs
COPY modinfo/spl /modinfo/spl
COPY entrypoint.sh /entrypoint.sh

RUN chmod +x /arcstat /arc_summary /entrypoint.sh

ENTRYPOINT ["/entrypoint.sh"]