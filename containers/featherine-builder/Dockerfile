FROM ubuntu:rolling
ENV DEBIAN_FRONTEND noninteractive
ENV LANG C.UTF-8

RUN apt-get update && \
    apt-get install -y --no-install-recommends ca-certificates git nodejs npm awscli && \
    apt-get clean && \
    rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*