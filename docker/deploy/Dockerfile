# syntax=docker/dockerfile:1

# Throwaway image used only to publish a release over FTP(S) and run
# post-deploy commands over SSH. Nothing about it ships to production.
FROM debian:bookworm-slim

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        lftp \
        openssh-client \
        sshpass \
        jq \
        zip \
        ca-certificates \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /work

ENTRYPOINT ["/bin/bash"]
