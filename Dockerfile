# FOSSology Dockerfile
# Copyright Siemens AG 2016, fabio.huser@siemens.com
# Copyright TNG Technology Consulting GmbH 2016-2017, maximilian.huber@tngtech.com
#
# Copying and distribution of this file, with or without modification,
# are permitted in any medium without royalty provided the copyright
# notice and this notice are preserved.  This file is offered as-is,
# without any warranty.
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
COPY ./utils/utils.sh ./utils/utils.sh
COPY ./src/copyright/mod_deps ./src/copyright/
COPY ./src/delagent/mod_deps ./src/delagent/
COPY ./src/mimetype/mod_deps ./src/mimetype/
COPY ./src/nomos/mod_deps ./src/nomos/
COPY ./src/ojo/mod_deps ./src/ojo/
COPY ./src/pkgagent/mod_deps ./src/pkgagent/
COPY ./src/scheduler/mod_deps ./src/scheduler/
COPY ./src/ununpack/mod_deps ./src/ununpack/
COPY ./src/wget_agent/mod_deps ./src/wget_agent/

RUN mkdir -p /fossology/dependencies-for-runtime \
 && cp -R /fossology/src /fossology/utils /fossology/dependencies-for-runtime/

RUN DEBIAN_FRONTEND=noninteractive apt-get update \
 && DEBIAN_FRONTEND=noninteractive /fossology/utils/fo-installdeps --build -y \
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
 && DEBIAN_FRONTEND=noninteractive /fossology/utils/fo-installdeps --offline --runtime -y \
 && DEBIAN_FRONTEND=noninteractive apt-get purge -y lsb-release \
 && DEBIAN_FRONTEND=noninteractive apt-get autoremove -y \
 && rm -rf /var/lib/apt/lists/*

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
RUN /usr/local/lib/fossology/fo-postinstall --agent --common --scheduler-only --web-only --no-running-database
