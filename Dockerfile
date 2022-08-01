# FOSSology Dockerfile
# SPDX-FileCopyrightText: © 2016 Siemens AG
# SPDX-FileCopyrightText: © fabio.huser@siemens.com
# SPDX-FileCopyrightText: © 2016-2017 TNG Technology Consulting GmbH
# SPDX-FileCopyrightText: © maximilian.huber@tngtech.com
#
# SPDX-License-Identifier: FSFAP
#
# Description: Docker container image recipe

FROM debian:buster-slim as builder
LABEL maintainer="Fossology <fossology@fossology.org>"

WORKDIR /fossology

RUN DEBIAN_FRONTEND=noninteractive apt-get update \
 && DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends \
      git \
      lsb-release \
      php7.3-cli \
      sudo \
 && rm -rf /var/lib/apt/lists/*

COPY ./utils/fo-installdeps ./utils/fo-installdeps
COPY ./install/fo-install-pythondeps ./install/fo-install-pythondeps
COPY ./utils/utils.sh ./utils/utils.sh
COPY ./src/copyright/mod_deps ./src/copyright/
COPY ./src/delagent/mod_deps ./src/delagent/
COPY ./src/mimetype/mod_deps ./src/mimetype/
COPY ./src/nomos/mod_deps ./src/nomos/
COPY ./src/ojo/mod_deps ./src/ojo/
COPY ./src/pkgagent/mod_deps ./src/pkgagent/
COPY ./src/scancode/mod_deps ./src/scancode/
COPY ./src/scheduler/mod_deps ./src/scheduler/
COPY ./src/ununpack/mod_deps ./src/ununpack/
COPY ./src/wget_agent/mod_deps ./src/wget_agent/

RUN mkdir -p /fossology/dependencies-for-runtime \
 && cp -R /fossology/src /fossology/utils /fossology/dependencies-for-runtime/

RUN DEBIAN_FRONTEND=noninteractive apt-get update \
 && DEBIAN_FRONTEND=noninteractive /fossology/utils/fo-installdeps --build -y \
 && DEBIAN_FRONTEND=noninteractive /fossology/install/fo-install-pythondeps --build -y \
 && rm -rf /var/lib/apt/lists/*

COPY . .

RUN make clean install \
 && make clean


FROM debian:buster-slim

LABEL maintainer="Fossology <fossology@fossology.org>"

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
      python \
      python3 \
      python3-yaml \
      python3-psycopg2 \
      python3-requests \
      python3-pip \
 && python3 -m pip install pip==21.2.2 \
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
