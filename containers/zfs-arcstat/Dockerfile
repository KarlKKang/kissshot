FROM python:3-slim

COPY --chmod=755 entrypoint.sh arcstat arc_summary /
COPY modinfo /modinfo

ENTRYPOINT ["/entrypoint.sh"]