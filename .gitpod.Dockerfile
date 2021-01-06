FROM gitpod/workspace-full

RUN sudo apt-get update \
 && sudo apt-get install -y \
    lsb-release git build-essential php-xdebug postgresql \
    postgresql-server-dev-all apache2 libmxml-dev curl libxml2-dev \
    libcunit1-dev libicu-dev libtext-template-perl subversion rpm librpm-dev \
    libmagic-dev libglib2.0 libboost-regex-dev libboost-program-options-dev \
    libpq-dev composer devscripts libdistro-info-perl binutils cabextract cpio \
    sleuthkit genisoimage poppler-utils upx-ucl unrar-free unzip p7zip-full \
    p7zip wget dpkg-dev s-nail \
    php php-mbstring php-cli php-xml php-zip php-pear php-pgsql php-curl \
    libicu66 libjsoncpp-dev libboost-system-dev libboost-filesystem-dev \
    libjson-c-dev libgcrypt20-dev bzip2 tar gzip libglib2.0-dev \
 && echo "\nIncludeOptional /workspace/apache/*.conf\n" | sudo tee -a /etc/apache2/apache2.conf

# Fix PHP for FOSSology
RUN PHP_PATH=$(php --ini | awk '/\/etc\/php.*\/cli$/{print $5}') \
 && phpIni="${PHP_PATH}/../apache2/php.ini" \
 && TIMEZONE=$(cat /etc/timezone) \
 && sudo sed -i 's/^\(max_execution_time\s*=\s*\).*$/\1300/' $phpIni \
 && sudo sed -i 's/^\(memory_limit\s*=\s*\).*$/\1702M/' $phpIni \
 && sudo sed -i 's/^\(post_max_size\s*=\s*\).*$/\1701M/' $phpIni \
 && sudo sed -i 's/^\(upload_max_filesize\s*=\s*\).*$/\1700M/' $phpIni \
 && sudo sed -i "s%.*date.timezone =.*%date.timezone = $TIMEZONE%" $phpIni

# Copy PostgreSQL setup from https://github.com/gitpod-io/workspace-images/blob/master/postgres/Dockerfile
# Setup PostgreSQL server for user gitpod
ENV PATH="$PATH:/usr/lib/postgresql/12/bin"
ENV PGDATA="/workspace/.pgsql/data"
RUN mkdir -p ~/.pg_ctl/bin ~/.pg_ctl/sockets \
 && printf '#!/bin/bash\n[ ! -d $PGDATA ] && mkdir -p $PGDATA && initdb -D $PGDATA\npg_ctl -D $PGDATA -l ~/.pg_ctl/log -o "-k ~/.pg_ctl/sockets" start\n' > ~/.pg_ctl/bin/pg_start \
 && printf '#!/bin/bash\npg_ctl -D $PGDATA -l ~/.pg_ctl/log -o "-k ~/.pg_ctl/sockets" stop\n' > ~/.pg_ctl/bin/pg_stop \
 && chmod +x ~/.pg_ctl/bin/*
ENV PATH="$PATH:$HOME/.pg_ctl/bin"
ENV DATABASE_URL="postgresql://gitpod@localhost"
ENV PGHOSTADDR="127.0.0.1"
ENV PGDATABASE="postgres"

# This is a bit of a hack. At the moment we have no means of starting background
# tasks from a Dockerfile. This workaround checks, on each bashrc eval, if the
# PostgreSQL server is running, and if not starts it.
RUN printf "\n# Auto-start PostgreSQL server.\n[[ \$(pg_ctl status | grep PID) ]] || pg_start > /dev/null\n" >> ~/.bashrc

# Setup fossy user
RUN sudo groupadd --system fossy \
 && sudo useradd --comment "fossy" --gid fossy --groups gitpod --create-home --shell /bin/bash --system fossy \
 && sudo usermod --append --groups fossy gitpod
