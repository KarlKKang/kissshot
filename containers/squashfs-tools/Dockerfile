FROM debian:bookworm-slim
ENV DEBIAN_FRONTEND noninteractive
ENV LANG C.UTF-8

RUN apt-get update && \
    apt-get install -y --no-install-recommends squashfs-tools && \
    apt-get clean && \
    rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

CMD ["mksquashfs", "--help"]