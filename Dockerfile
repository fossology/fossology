# FOSSology Dockerfile
# Copyright Siemens AG 2016, fabio.huser@siemens.com
#
# Copying and distribution of this file, with or without modification,
# are permitted in any medium without royalty provided the copyright
# notice and this notice are preserved.  This file is offered as-is,
# without any warranty.
#
# Description: Docker container image recipe

FROM debian:8.8

MAINTAINER Fossology <fossology@fossology.org>

WORKDIR /fossology
COPY . .

RUN apt-get update && \
    apt-get install -y lsb-release sudo postgresql php5-curl libpq-dev libdbd-sqlite3-perl libspreadsheet-writeexcel-perl && \
    /fossology/utils/fo-installdeps -e -y && \
    rm -rf /var/lib/apt/lists/*

RUN curl -sS https://getcomposer.org/installer | php && \
    mv composer.phar /usr/local/bin/composer

RUN /fossology/install/scripts/install-spdx-tools.sh

RUN /fossology/install/scripts/install-ninka.sh

RUN make install

RUN cp /fossology/install/src-install-apache-example.conf /etc/apache2/conf-available/fossology.conf && \
    ln -s /etc/apache2/conf-available/fossology.conf /etc/apache2/conf-enabled/fossology.conf

RUN /fossology/install/scripts/php-conf-fix.sh --overwrite

EXPOSE 8081

RUN chmod +x /fossology/docker-entrypoint.sh
ENTRYPOINT ["/fossology/docker-entrypoint.sh"]
