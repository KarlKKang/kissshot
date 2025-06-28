FROM debian:bookworm-slim
ENV DEBIAN_FRONTEND noninteractive
ENV LANG C.UTF-8

RUN apt-get update && \
    apt-get install -y --no-install-recommends curl ca-certificates gnupg2 build-essential flex bison libelf-dev bc libssl-dev cpio kmod rust-all rust-src bindgen libncurses-dev \
    alien autoconf automake debhelper-compat dh-autoreconf dh-dkms dh-python dkms fakeroot gawk git libaio-dev libattr1-dev libblkid-dev libcurl4-openssl-dev libelf-dev libffi-dev libpam0g-dev libssl-dev libtirpc-dev libtool libudev-dev linux-headers-generic parallel po-debconf python3 python3-all-dev python3-cffi python3-dev python3-packaging python3-setuptools python3-sphinx uuid-dev zlib1g-dev && \
    apt-get clean && \
    rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

COPY --chmod=755 entrypoint.sh /entrypoint.sh

ENTRYPOINT ["/entrypoint.sh"]