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

#################################################
FROM bitnami/postgresql:9.6 as fossology-db
COPY ./install/db/initdb-extensions.sh /docker-entrypoint-initdb.d/install-extensions.sh
COPY ./install/db/fossologyinit.sql /docker-entrypoint-initdb.d/
COPY ./install/db/psql-connect.sh /
USER 0
RUN touch /.psql_history && chmod 666 /.psql_history
USER 1001

FROM debian:buster-slim as builder

LABEL maintainer="opensource-audit-solutions@list.orange.com"
LABEL Name="Fossology_Orange-OpenSource"
LABEL Description="Fossology Docker Image"
LABEL Url="https://gitlab.forge.orange-labs.fr/opensource/fossology/wikis/"

EXPOSE 8080

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
COPY ./utils/install_composer.sh  ./utils/
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

#################################################
FROM bitnami/apache:latest as fossology-web

### install dependencies
COPY --from=builder /fossology/dependencies-for-runtime /fossology

### copy deb pkg not in repo
COPY packages/libapache2-mod-auth-openidc_2.4.8.4-1.bionic+1_amd64.deb /tmp
COPY packages/libhiredis0.13_0.13.3-2_amd64.deb /tmp

WORKDIR /fossology

# Fix for Postgres and other packages in slim variant
# Note: cron, python, python-psycopg2 are installed
#       specifically for metrics reporting
# Required to perform privileged actions
USER 0
RUN install_packages \
      libapache2-mod-shib2 \
      curl \
      lsb-release \
      sudo \
      cron \
      python \
      python3 \
      python3-yaml \
      python3-psycopg2 \
      python3-requests
RUN /fossology/utils/fo-installdeps --distro minideb --offline --runtime -y
# configure php
COPY ./install/scripts/php-conf-fix.sh ./install/scripts/php-conf-fix.sh
RUN /fossology/install/scripts/php-conf-fix.sh --overwrite

RUN mkdir /vhosts
RUN chmod a+rw /vhosts
COPY --from=builder /usr/local/ /usr/local/

# the database is filled in the entrypoint
RUN /usr/local/lib/fossology/fo-postinstall --container-mode --agent --common --web-only --no-running-database

# TEMPORARY :
RUN install_packages vim net-tools

### install oauth apache mod
RUN dpkg -i /tmp/libhiredis0.13_0.13.3-2_amd64.deb
RUN install_packages libcjose0 libhiredis0.14 libjansson4 apache2-api-20120211 apache2-bin
RUN dpkg -i /tmp/libapache2-mod-auth-openidc_2.4.8.4-1.bionic+1_amd64.deb
RUN cp /usr/lib/apache2/modules/mod_auth_openidc.so /opt/bitnami/apache/modules/mod_auth_openidc.so

# Revert to the original non-root user
RUN echo "=> Revert to the original non-root user (1001)"
USER 1001

COPY ./docker-entrypoint-k8s.sh /fossology/docker-entrypoint-k8s.sh
ENTRYPOINT ["/fossology/docker-entrypoint-k8s.sh", "web"]

#################################################
FROM debian:buster-slim as fossology-scheduler

### install dependencies
COPY --from=builder /fossology/dependencies-for-runtime /fossology

WORKDIR /fossology

# Fix for Postgres and other packages in slim variant
# Note: cron, python, python-psycopg2 are installed
#       specifically for metrics reporting
RUN mkdir /usr/share/man/man1 /usr/share/man/man7 \
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
      rsync tar \
 && DEBIAN_FRONTEND=noninteractive /fossology/utils/fo-installdeps --offline --runtime -y \
 && DEBIAN_FRONTEND=noninteractive apt-get purge -y lsb-release \
 && DEBIAN_FRONTEND=noninteractive apt-get autoremove -y \
 && rm -rf /var/lib/apt/lists/*

COPY --from=builder /etc/cron.d/fossology /etc/cron.d/fossology
COPY --from=builder /etc/init.d/fossology /etc/init.d/fossology
COPY --from=builder /usr/local/ /usr/local/

# the database is filled in the entrypoint
RUN /usr/local/lib/fossology/fo-postinstall --container-mode --agent --common --scheduler-only --no-running-database

COPY ./docker-entrypoint-k8s.sh /fossology/docker-entrypoint-k8s.sh
ENTRYPOINT ["/fossology/docker-entrypoint-k8s.sh"]
# Scheduler is the default command, but the image can also be used for 'maintenance'
CMD ["scheduler"]

