# FOSSology Dockerfile
# Copyright Siemens AG 2016, fabio.huser@siemens.com
# Copyright TNG Technology Consulting GmbH 2016, maximilian.huber@tngtech.com
#
# Copying and distribution of this file, with or without modification,
# are permitted in any medium without royalty provided the copyright
# notice and this notice are preserved.  This file is offered as-is,
# without any warranty.
#
# Description: Docker container image recipe

FROM debian:stable
MAINTAINER Fossology <fossology@fossology.org>
WORKDIR /fossology

ENV _update="apt-get update"
ENV _install="apt-get install -y --no-install-recommends"
ENV _cleanup="eval apt-get clean && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*"

RUN set -x \
 && $_update && $_install \
       lsb-release curl php5 libpq-dev libdbd-sqlite3-perl libspreadsheet-writeexcel-perl postgresql-client \
       sudo \
       # for standalone mode:
       postgresql \
 && $_cleanup

ADD utils/fo-installdeps utils/fo-installdeps
ADD install/scripts/php-conf-fix.sh install/scripts/php-conf-fix.sh
RUN set -x \
 && $_update \
 && /fossology/install/scripts/php-conf-fix.sh --overwrite \
 && /fossology/utils/fo-installdeps -e -y \
 && $_cleanup
RUN curl -sS https://getcomposer.org/installer | php && \
    mv composer.phar /usr/local/bin/composer

ADD . .
RUN chmod +x /fossology/docker-entrypoint.sh
RUN set -x \
 && make install \
 && make clean

RUN set -x \
 && /usr/local/lib/fossology/fo-postinstall --common \
 && mkdir -p /srv/fossology/repository/

################################################################################
# scheduler related
RUN /usr/local/lib/fossology/fo-postinstall \
        --agent \
        --scheduler-only

RUN set -x \
 && mkdir -p /var/log/fossology \
 && chown -R fossy:fossy /var/log/fossology \
 && chgrp -R fossy /usr/local/etc/fossology/ \
 && chmod -R g+wr /usr/local/etc/fossology/ \
 && chown fossy:fossy /usr/local/etc/fossology/Db.conf

################################################################################
# web related
RUN /usr/local/lib/fossology/fo-postinstall \
        --web-only \
 && systemctl disable apache2

RUN set -x \
 && cp /fossology/install/src-install-apache-example.conf \
        /etc/apache2/conf-available/fossology.conf \
 && ln -s /etc/apache2/conf-available/fossology.conf \
        /etc/apache2/conf-enabled/fossology.conf \
 && echo Listen 8081 >/etc/apache2/ports.conf

RUN set -x \
 && chmod -R o+r /etc/apache2 \
 && mkdir -p /var/log/apache2/ \
 && chown -R fossy:fossy /var/log/apache2/ \
 && chown -R fossy:fossy /var/run/apache2/ \
 && chown -R fossy:fossy /var/lock/apache2/

EXPOSE 8081

################################################################################
VOLUME /srv/fossology/repository/
USER fossy

ENTRYPOINT ["/fossology/docker-entrypoint.sh"]
