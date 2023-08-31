#!/bin/bash
# This script installs fossology in Gitpod's workspace
# SPDX-FileCopyrightText: © 2021 Siemens AG
# Author: Gaurav Mishra <mishra.gaurav@siemens.com>

# SPDX-License-Identifier: GPL-2.0-only

createuser -s -i -d -r -l -w postgres || echo "user already exists"

sudo a2dismod php8.2 mpm_prefork

# Install apache and PHP packages inside GitPod as gp is not available in Docker
sudo apt-get update && sudo apt-get install -y \
 --option Dpkg::Options::="--force-confold" \
 apache2 libapache2-mod-php php php-cli php-curl php-mbstring php-pear \
 php-pgsql php-sqlite3 php-xdebug php-xml php-zip php-uuid
sudo ./utils/fo-installdeps -ey

sudo a2dismod mpm_event
sudo a2enmod php8.2

# Install FOSSology in Gitpod's workspace
rm -rf build

mkdir -p build

cmake -DCMAKE_INSTALL_PREFIX:PATH='/workspace/fossy/code' \
 -DFO_INITDIR:PATH='/workspace/fossy/etc' \
 -DFO_REPODIR:PATH='/workspace/fossy/srv' \
 -DFO_LOCALSTATEDIR:PATH='/workspace/fossy/var' \
 -DFO_APACHE2SITE_DIR:PATH='/workspace/apache' \
 -DFO_SYSCONFDIR:PATH='/workspace/fossy/etc/fossology' \
 -DFO_PROJECTUSER='gitpod' -DFO_PROJECTGROUP='gitpod' -DTESTING=ON \
 -GNinja -S. -B./build

cmake --build build --parallel

sudo cmake --install build

# Setup DB for gitpod
sudo su postgres -c psql < install/db/gitpod-fossologyinit.sql

# Run postinstall script
sudo -HE /workspace/fossy/code/lib/fossology/fo-postinstall -oe --python-experimental || echo "Done with fo-postinstall"

# Fix the FOSSology path for Apache
echo "Fixing path in Apache"
sed -i "s/\/usr\/local\/share/\/workspace\/fossy\/code\/share/" "/workspace/apache/fossology.conf"
