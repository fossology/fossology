# FOSSology gitpod.io Dockerfile
# SPDX-FileCopyrightText: © 2021 Siemens AG
# Author: Gaurav Mishra <mishra.gaurav@siemens.com>

# SPDX-License-Identifier: GPL-2.0-only
#
# Description: Docker container image recipe for gitpod.io workspace

FROM gitpod/workspace-full:latest

USER root

RUN install-packages --option Dpkg::Options::="--force-confold" \
    binutils build-essential bzip2 cabextract cmake composer cpio cppcheck \
    curl debhelper devscripts dpkg-dev genisoimage git git-core gzip \
    libboost-filesystem-dev libboost-program-options-dev \
    libboost-regex-dev libboost-system-dev libcppunit-dev libcunit1-dev \
    libdbd-sqlite3-perl libdistro-info-perl libgcrypt20-dev \
    libicu70 libicu-dev libjson-c-dev libjsoncpp-dev \
    liblocal-lib-perl libmagic-dev libmxml-dev libpcre3-dev libpq-dev \
    librpm-dev libzstd-dev libspreadsheet-writeexcel-perl libsqlite3-0 libsqlite3-dev \
    libssl-dev libtext-template-perl libxml2-dev lsb-release ninja-build p7zip \
    p7zip-full poppler-utils postgresql-14 postgresql-contrib-14 \
    postgresql-server-dev-all rpm sleuthkit s-nail sqlite3 subversion tar \
    unrar-free unzip wget zip

# Fix PHP for FOSSology
RUN PHP_PATH=$(php --ini | awk '/\/etc\/php.*\/cli$/{print $5}') \
 && phpIni="${PHP_PATH}/../apache2/php.ini" \
 && TIMEZONE=$(cat /etc/timezone) \
 && sed -i 's/^\(max_execution_time\s*=\s*\).*$/\1300/' $phpIni \
 && sed -i 's/^\(memory_limit\s*=\s*\).*$/\1702M/' $phpIni \
 && sed -i 's/^\(post_max_size\s*=\s*\).*$/\1701M/' $phpIni \
 && sed -i 's/^\(upload_max_filesize\s*=\s*\).*$/\1700M/' $phpIni \
 && sed -i "s%.*date.timezone =.*%date.timezone = $TIMEZONE%" $phpIni

USER gitpod

# Copy PostgreSQL setup from https://github.com/gitpod-io/workspace-images/blob/66e95d4114e2dbbace9d17a39291e447b9b3f061/chunks/tool-postgresql/Dockerfile
# Changes:
# 1. Call initdb with `postgres' as the user
# 2. Change the unix_socket_directories in postgresql.conf
# Setup PostgreSQL server for user gitpod
ENV PATH="/usr/lib/postgresql/14/bin:$PATH"
ENV PGWORKSPACE="/workspace/.pgsql"
ENV PGDATA="$PGWORKSPACE/data"
RUN PGDATA="$HOME/.pgsql/data" \
 && SOCLOC="$HOME/.pg_ctl/sockets" \
 && mkdir -p ~/.pg_ctl/bin ~/.pg_ctl/sockets $PGDATA \
 && initdb -D $PGDATA \
 && printf "#!/bin/bash\npg_ctl -D ${PGDATA} -l ~/.pg_ctl/log -o \"-k ~/.pg_ctl/sockets\" start\n" > ~/.pg_ctl/bin/pg_start \
 && printf "#!/bin/bash\npg_ctl -D ${PGDATA} -l ~/.pg_ctl/log -o \"-k ~/.pg_ctl/sockets\" stop\n" > ~/.pg_ctl/bin/pg_stop \
 && chmod +x ~/.pg_ctl/bin/* \
 && sudo sed -i "s:^\(unix_socket_directories\s*=\s*\).*\$:\1'${SOCLOC}':" /etc/postgresql/14/main/postgresql.conf \
 && echo "localhost:*:*:gitpod:gitpod" >> ~/.pgpass \
 && chmod 600 ~/.pgpass
ENV PATH="$PATH:$HOME/.pg_ctl/bin"
ENV DATABASE_URL="postgresql://postgres@localhost"
ENV PGHOSTADDR="127.0.0.1"
ENV PGDATABASE="postgres"

# This is a bit of a hack. At the moment we have no means of starting background
# tasks from a Dockerfile. This workaround checks, on each bashrc eval, if the
# PostgreSQL server is running, and if not starts it.
RUN printf "\n# Auto-start PostgreSQL server.\n[[ \$(pg_ctl status | grep PID) ]] || pg_start > /dev/null\n" >> ~/.bashrc \
 && echo "export PGHOST=${PGHOSTADDR}\n" >> ~/.bashrc

# Git branch prepend
RUN printf "parse_git_branch() {\n  git branch 2> /dev/null | sed -e '/^[^*]/d' -e 's/* \(.*\)/(\\\1)/'\n}\n" >> ~/.bashrc \
 && echo 'PS1="\[\e]0;\u: \w\a\]${debian_chroot:+($debian_chroot)}\[\033[01;32m\]\u\[\033[00m\]:\[\033[01;34m\]\w\[\033[00m\] \[\033[0;33m\]\$(parse_git_branch)\[\033[00m\]$ "' >> ~/.bashrc

# Add custom aliases
RUN printf "fossinstallparams=\"-DCMAKE_INSTALL_PREFIX:PATH='/workspace/fossy/code' -DFO_INITDIR:PATH='/workspace/fossy/etc' -DFO_REPODIR:PATH='/workspace/fossy/srv' -DFO_LOCALSTATEDIR:PATH='/workspace/fossy/var' -DFO_APACHE2SITE_DIR:PATH='/workspace/apache' -DFO_SYSCONFDIR:PATH='/workspace/fossy/etc/fossology' -DFO_PROJECTUSER='gitpod' -DFO_PROJECTGROUP='gitpod' -DTESTING=ON\"\n" >> ~/.bashrc \
 && printf '# Fossology alias\nalias fossrun="sudo /workspace/fossy/code/share/fossology/scheduler/agent/fo_scheduler --verbose=3 --reset --config /workspace/fossy/etc/fossology/"\n' >> ~/.bash_aliases \
 && printf 'alias conffoss="cmake ${fossinstallparams} -DTESTING=ON -DCMAKE_BUILD_TYPE=Debug -GNinja -S/workspace/fossology/ -B/workspace/fossology/build"\n' \
 && printf 'alias buildfoss="conffoss && cmake --build /workspace/fossology/build --parallel"\n' \
 && printf 'alias installfoss="cmake --install /workspace/fossology/build && sudo -E /workspace/fossy/code/lib/fossology/fo-postinstall -oe --python-experimental"\n' >> ~/.bash_aliases
