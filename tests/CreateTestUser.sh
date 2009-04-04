#!/bin/bash
#***********************************************************
# Copyright (C) 2008 Hewlett-Packard Development Company, L.P.
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
#
# simple script to create the test user needed for testing certain
# test cases.n Also creates a file to indicate that the user exists and
# tests can proceed.
#
# Must be run as root or sudo
#
# todo: add -f option so tests will run with data install errors?

# add groups
/usr/sbin/groupadd -g 666777 fosstester
if [ $? -ne 0 ]
then
  echo "ERROR! could not create fosstester group number 666777"
  exit 1
fi
/usr/sbin/groupadd -g 555777 noemail
if [ $? -ne 0 ]
then
  echo "ERROR! could not create noemail group number 555777"
  exit 1
fi
# useradd:
# Name, home dir path, uid, initial group, other groups, create home, shell,
# password (none) user-account

# fosstester
/usr/sbin/useradd -c 'Fossolgy Test User' -d /home/fosstester -u 666777 -g fosstester \
-G fossy,sudo,users -m -s /bin/bash -p 'Brksumth1n' fosstester
if [ $? -ne 0 ]
then
  echo "ERROR! could not create fosstester user UID:666777"
  exit 1
fi

# noemail
/usr/sbin/useradd -c 'Fossolgy Test User' -d /home/noemail -u 555777 -g noemail \
-G fossy,sudo,users -m -s /bin/bash -p 'n0eeemale' noemail
if [ $? -ne 0 ]
then
  echo "ERROR! could not create noemail user UID:555777"
  exit 1
fi

if [ -x ./installTestData.sh ]
then
	./installTestData.sh
	if [ $? -ne 0 ]
	then
		echo "ERROR! durnig run of installTestData.sh"
		exit 1
	fi
else
	echo "ERROR! Either ./installTestData.sh doesn't exist or is not executable"
	exit 1
fi

#
# set up the all clear file
# Some tests look for this file to make sure there is data for use.

touch /home/fosstester/allClear
chmod a+rx /home/fosstester/allClear
chown fosstester:fosstester /home/fosstester/allClear

exit 0