#!/bin/bash
# This script installs fossology in Gitpod's workspace
# SPDX-FileCopyrightText: © 2021 Siemens AG
# Author: Gaurav Mishra <mishra.gaurav@siemens.com>

# SPDX-License-Identifier: GPL-2.0-only

sudo a2dismod php7.4 mpm_prefork

# Install apache and PHP packages inside GitPod as gp is not available in Docker
sudo apt-get update && sudo apt-get install -y \
 --option Dpkg::Options::="--force-confold" \
 apache2 libapache2-mod-php php php-cli php-curl php-mbstring php-pear \
 php-pgsql php-sqlite3 php-xdebug php-xml php-zip
sudo ./utils/fo-installdeps -ey

sudo a2dismod mpm_event
sudo a2enmod php7.4

# Install FOSSology in Gitpod's workspace
make install \
 PREFIX='/workspace/fossy/code' \
 INITDIR='/workspace/fossy/etc' \
 REPODIR='/workspace/fossy/srv' \
 LOCALSTATEDIR='/workspace/fossy/var' \
 APACHE2_SITE_DIR='/workspace/apache' \
 SYSCONFDIR='/workspace/fossy/etc/fossology' \
 PROJECTUSER='gitpod' PROJECTGROUP='gitpod'

# Setup DB for gitpod
sudo su postgres -c psql < install/db/gitpod-fossologyinit.sql

# Run postinstall script
sudo -HE /workspace/fossy/code/lib/fossology/fo-postinstall || echo "Done with fo-postinstall"

# Fix the FOSSology path for Apache
echo "Fixing path in Apache"
sed -i "s/\/usr\/local\/share/\/workspace\/fossy\/code\/share/" "/workspace/apache/fossology.conf"
