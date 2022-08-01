#!/bin/bash
# SPDX-FileCopyrightText: © Fossology contributors

# SPDX-License-Identifier: GPL-2.0-only

# One of the files for the project builder setup
#
# this part installs the dependencies needed for running project builder
# Work is done on fobuild using Ubuntu 16.04
#
set -ex

# some variables to have the migration between scripts easier
#INSTALL="yum  install -y"
INSTALL="apt-get install -y"

# download and add additonal repo coordinates
sudo $INSTALL wget git
sudo wget http://ftp.project-builder.org/ubuntu/16.04/pb.sources.list -O /etc/apt/sources.list.d/pb.sources.list
cat > /tmp/docker.list << EOF
deb https://get.docker.io/ubuntu docker main > 
EOF
sudo mv /tmp/docker.list /etc/apt/sources.list.d/docker.list
# caring for the keys
sudo apt-key adv --keyserver hkp://keyserver.ubuntu.com:80 --recv-keys 36A1D7869245C8950F966E92D8576A8BA88D21E9
sudo apt-key adv --keyserver hkp://keyserver.ubuntu.com:80 --recv-keys 0x141B9FF237DB9883
sudo apt-key adv --keyserver hkp://keyserver.ubuntu.com:80 --recv-keys 0x6BA8C2D220EBFB0E

# not using gpg checking is not recommended
# yum update -y --nogpgcheck
# yum install -y project-builder --nogpgcheck
sudo apt-get update -y
# SHA1 keys iis considered keys actually, prevented since march 2016
sudo $INSTALL project-builder lxc-docker --allow-unauthenticated

# IF on CentOS 7 - install EPEL
# wget https://dl.fedoraproject.org/pub/epel/epel-release-latest-7.noarch.rpm
# rpm -Uvh epel-release-latest-7.noarch.rpm
# sudo wget http://ftp.project-builder.org/centos/7/x86_64/pb.repo -O /etc/yum.repos.d/pb.repo
# install build dependencies
# yum install -y redhat-lsb project-builder.org

# download fossy repo to have more specific fossology stuff
rm -rf ../fossology
git clone https://github.com/fossology/fossology.git -b dev/pb ../fossology

# first clean old
sudo bash -x ../fossology/utils/fo-cleanold --delete-everything
sudo bash -x ../fossology/utils/fo-installdeps -b -y 

# adding local user to docker group
sudo usermod -G docker -a $USER

# ... starting docker ?
sudo service docker restart
#sudo systemctl restart docker
