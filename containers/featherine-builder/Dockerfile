FROM node:22-bookworm-slim

RUN apt-get update && \
    apt-get install -y --no-install-recommends awscli python3 python3-fonttools && \
    apt-get clean && \
    rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

CMD ["/bin/bash"]