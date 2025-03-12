# FOSSology Dockerfile
# SPDX-FileCopyrightText: © 2016,2022 Siemens AG
# SPDX-FileCopyrightText: © fabio.huser@siemens.com
# SPDX-FileCopyrightText: © mishra.gaurav@siemens.com
# SPDX-FileCopyrightText: © 2016-2017 TNG Technology Consulting GmbH
# SPDX-FileCopyrightText: © maximilian.huber@tngtech.com
#
# SPDX-License-Identifier: FSFAP
#
# Description: Docker container image recipe

FROM debian:bookworm-slim AS builder
LABEL org.opencontainers.image.authors="Fossology <fossology@fossology.org>"
LABEL org.opencontainers.image.source="https://github.com/fossology/fossology"
LABEL org.opencontainers.image.vendor="FOSSology"
LABEL org.opencontainers.image.licenses="GPL-2.0-only AND LGPL-2.1-only"

WORKDIR /fossology

RUN DEBIAN_FRONTEND=noninteractive apt-get update \
 && DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends \
      git \
      lsb-release \
      php8.2-cli \
      sudo \
      cmake \
      ninja-build \
 && rm -rf /var/lib/apt/lists/*

COPY ./utils/fo-installdeps ./utils/fo-installdeps
COPY ./install/fo-install-pythondeps ./install/fo-install-pythondeps
COPY ./utils/utils.sh ./utils/utils.sh
COPY ./src/copyright/mod_deps ./src/copyright/
COPY ./src/compatibility/mod_deps ./src/compatibility/
COPY ./src/delagent/mod_deps ./src/delagent/
COPY ./src/mimetype/mod_deps ./src/mimetype/
COPY ./src/nomos/mod_deps ./src/nomos/
COPY ./src/ojo/mod_deps ./src/ojo/
COPY ./src/pkgagent/mod_deps ./src/pkgagent/
COPY ./src/scancode/mod_deps ./src/scancode/
COPY ./src/scheduler/mod_deps ./src/scheduler/
COPY ./src/ununpack/mod_deps ./src/ununpack/
COPY ./src/wget_agent/mod_deps ./src/wget_agent/
COPY ./src/scanoss/mod_deps ./src/scanoss/

RUN mkdir -p /fossology/dependencies-for-runtime \
 && cp -R /fossology/src /fossology/utils /fossology/dependencies-for-runtime/

RUN DEBIAN_FRONTEND=noninteractive apt-get update \
 && DEBIAN_FRONTEND=noninteractive /fossology/utils/fo-installdeps --build -y \
 && DEBIAN_FRONTEND=noninteractive /fossology/install/fo-install-pythondeps --build -y \
 && rm -rf /var/lib/apt/lists/*

COPY . .

RUN cmake -DCMAKE_BUILD_TYPE=MinSizeRel -S. -B./build -G Ninja \
 && cmake --build ./build --parallel \
 && cmake --install build

FROM debian:bookworm-slim

LABEL org.opencontainers.image.authors="Fossology <fossology@fossology.org>"
LABEL org.opencontainers.image.url="https://fossology.org"
LABEL org.opencontainers.image.source="https://github.com/fossology/fossology"
LABEL org.opencontainers.image.vendor="FOSSology"
LABEL org.opencontainers.image.licenses="GPL-2.0-only AND LGPL-2.1-only"
LABEL org.opencontainers.image.title="FOSSology"
LABEL org.opencontainers.image.description="FOSSology is an open source license compliance software system and toolkit.  As a toolkit you can run license, copyright and export control scans from the command line.  As a system, a database and web ui are provided to give you a compliance workflow. License, copyright and export scanners are tools used in the workflow."

### install dependencies
COPY --from=builder /fossology/dependencies-for-runtime /fossology

WORKDIR /fossology

# Fix for Postgres and other packages in slim variant
# Note: cron, python, python-psycopg2 are installed
#       specifically for metrics reporting
RUN mkdir -p /usr/share/man/man1 /usr/share/man/man7 \
 && DEBIAN_FRONTEND=noninteractive apt-get update \
 && DEBIAN_FRONTEND=noninteractive apt-get upgrade -y \
 && DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends \
      curl \
      lsb-release \
      sudo \
      cron \
      python3 \
      python3-yaml \
      python3-psycopg2 \
      python3-requests \
      python3-pip \
      libyaml-cpp0.7 \
 && DEBIAN_FRONTEND=noninteractive /fossology/utils/fo-installdeps --offline --runtime -y \
 && DEBIAN_FRONTEND=noninteractive apt-get autoremove -y

# configure php
COPY ./install/scripts/php-conf-fix.sh ./install/scripts/php-conf-fix.sh
RUN /fossology/install/scripts/php-conf-fix.sh --overwrite

# configure apache
RUN mkdir -p /var/log/apache2/ \
 && ln -sf /proc/self/fd/1 /var/log/apache2/access.log \
 && ln -sf /proc/self/fd/1 /var/log/apache2/error.log

COPY ./docker-entrypoint.sh /fossology/docker-entrypoint.sh
RUN chmod +x /fossology/docker-entrypoint.sh
ENTRYPOINT ["/fossology/docker-entrypoint.sh"]

COPY --from=builder /etc/cron.d/fossology /etc/cron.d/fossology
COPY --from=builder /etc/init.d/fossology /etc/init.d/fossology
COPY --from=builder /usr/local/ /usr/local/

# the database is filled in the entrypoint
RUN /usr/local/lib/fossology/fo-postinstall --agent --common --scheduler-only \
     --web-only --no-running-database --python-experimental \
 && rm -rf /var/lib/apt/lists/*
