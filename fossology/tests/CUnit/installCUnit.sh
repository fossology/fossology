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

sudo apt-get install libcunit1-dev libcunit1-doc libcunit1
OUT=$?

if [ $OUT -eq 0 ]; then
  exit 1
elif [ ! -f /usr/local/lib/libcunit.a ]; then
  wget http://sourceforge.net/projects/cunit/files/CUnit/2.1-2/CUnit-2.1-2-src.tar.bz2
  sudo tar -xvjf CUnit-2.1-2-src.tar.bz2 
  chmod 755 configure
  ./configure
  make
  imake install
else
  exit 1
fi

OUT=$?
if [ $OUT -eq 0 ]; then
  exit 1
else
  exit 0
fi

