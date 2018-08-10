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

FROM debian:jessie

LABEL maintainer="Fossology <fossology@fossology.org>"

WORKDIR /fossology

RUN DEBIAN_FRONTEND=noninteractive apt-get update \
 && DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends lsb-release sudo \
 && rm -rf /var/lib/apt/lists/*

COPY ./utils/fo-installdeps ./utils/fo-installdeps
COPY ./utils/utils.sh ./utils/utils.sh
COPY ./src/delagent/mod_deps ./src/delagent/
COPY ./src/mimetype/mod_deps ./src/mimetype/
COPY ./src/pkgagent/mod_deps ./src/pkgagent/
COPY ./src/scheduler/mod_deps ./src/scheduler/
COPY ./src/ununpack/mod_deps ./src/ununpack/
COPY ./src/wget_agent/mod_deps ./src/wget_agent/
COPY ./install/scripts/php-conf-fix.sh ./install/scripts/php-conf-fix.sh
COPY ./utils/install_composer.sh ./utils/install_composer.sh

RUN DEBIAN_FRONTEND=noninteractive apt-get update \
 && DEBIAN_FRONTEND=noninteractive /fossology/utils/fo-installdeps --everything -y \
 && rm -rf /var/lib/apt/lists/* \
 && /fossology/install/scripts/php-conf-fix.sh --overwrite \
 && /fossology/utils/install_composer.sh

COPY . .

RUN make install && make clean

# the database is filled in the entrypoint
RUN /usr/local/lib/fossology/fo-postinstall --agent --common --scheduler-only --web-only --no-running-database

# configure apache
RUN cp /fossology/install/src-install-apache-example.conf /etc/apache2/conf-available/fossology.conf \
 && ln -s /etc/apache2/conf-available/fossology.conf /etc/apache2/conf-enabled/fossology.conf \
 && mkdir -p /var/log/apache2/ \
 && ln -sf /proc/self/fd/1 /var/log/apache2/access.log \
 && ln -sf /proc/self/fd/1 /var/log/apache2/error.log

EXPOSE 80

RUN chmod +x /fossology/install/docker-compose.docker-entrypoint.sh
ENTRYPOINT ["/fossology/install/docker-compose.docker-entrypoint.sh"]
