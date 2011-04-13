#!/bin/bash
#***********************************************************
# Copyright (C) 2010 Hewlett-Packard Development Company, L.P.
#
# This program is free software; you can redistribute it and/or
# modify it under the terms of the GNU General Public License
# version 2 as published by the Free Software Foundation.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License along
# with this program; if not, write to the Free Software Foundation, Inc.,
# 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
#***********************************************************/
# judge os type and install cunit on debian
cat /proc/version |grep Debian
OUT=$?
if [ $OUT -eq 0 ]; then
  sudo apt-get -y install lcov
  sudo apt-get -y install autoconf
  sudo apt-get -y install libcunit1-dev libcunit1-doc libcunit1
else
  echo "please find the relevant package for cunit to install\n"
fi

##########################################################################################
# install cunit through source, please refer to the process below
# wget http://sourceforge.net/projects/cunit/files/CUnit/2.1-2/CUnit-2.1-2-src.tar.bz2
#  sudo tar -xvjf CUnit-2.1-2-src.tar.bz2 
#  chmod 755 configure
#  ./configure
#  make
#  imake install

