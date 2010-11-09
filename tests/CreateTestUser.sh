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
# test cases. Also creates a file to indicate that the user exists and
# tests can proceed.
#
# Must be run as root or sudo
#
# todo: add -f option so tests will run with data install errors?
## create user and group
 # Because we are doing these by name, in the multi-machine install case
 # we may end up with uid/gid being different across machines. This will
 # either need to be fixed by hand or with NFSv4 you can use rpc.idmapd
 # to do uid/gid mapping. More details will be provided in the multi-machine
 # documentation.

function add_group() {
#
# expects name of project group
if [ -z $1 ]
   then return 1
else
   groupname=$1
fi
   # check for group
   if grep -q "^$groupname:" /etc/group; then
      echo "NOTE: group '$groupname' already exists, good."
      return 0
   else
      # use addgroup if it exists since it supports --system
      if [ -f /usr/sbin/addgroup -a ! -L /usr/sbin/addgroup ]; then
         addgroup --system $groupname
      else
         groupadd $groupname
      fi
      if [ "$?" != "0" ] ; then
         echo "ERROR: Unable to create group '$groupname'"
         return 1
      else
         echo "NOTE: group '$groupname' created"
         return 0
      fi
   fi
}  # add_group

function add_user() {
#
# expects name of the user and groupname
if [ -z $1 ]
    then return 1
else
   username=$1   
fi
if [ -z $2 ]
    then return 1
else
   groupname=$2  
fi
   # check for user
   if grep -q "^$username:" /etc/passwd; then
      echo "NOTE: user '$username' already exists, good."
      USERSHELL=`grep "^$username:" /etc/passwd |cut -d: -f 7`
      if [ "$USERSHELL" = "/bin/false" ]; then
         echo "ERROR: $username shell must be a real shell"
         return 1
      fi
      return 0
   else
      # use adduser if it exists since it supports --system, but
      # not if it's a symlink (probably to /usr/sbin/useradd)
      if [ -f /usr/sbin/adduser -a ! -L /usr/sbin/adduser ]; then
         adduser --gecos "Fossolgy Test User" --ingroup $groupname --system \
           --shell /bin/bash --home "/home/$username" $username
      else
         useradd -c "UI_Test" -g $groupname -m \
           -s /bin/bash -d "/home/$username" $username
      fi
      if [ "$?" != "0" ] ; then
         echo "ERROR: Unable to create user '$username'"
         return 1
      else
         echo "NOTE: user '$username' created"
         /usr/sbin/usermod -G fossy,sudo,users $username
         /usr/sbin/chpasswd <<< "$username:$username" 
         return 0
      fi
   fi
}

##############################################################################
# Main
##############################################################################

# This must run as root.
if [ `id -u` != "0" ] ; then
   echo "ERROR: $0 must run as root."
   echo "Aborting."
   exit 1
fi

# add /usr/sbin to path
PATH=$PATH:/usr/sbin
#echo "path is:$PATH"
export PATH

# add groups

add_group fosstester
if [ $? -ne 0 ]
then
  echo "ERROR! could not create fosstester"
  exit 1
fi
add_group noemail
if [ $? -ne 0 ]
then
  echo "ERROR! could not create noemail group"
  exit 1
fi

# fosstester
add_user fosstester fosstester
if [ $? -ne 0 ]
then
  echo "ERROR! could not create fosstester user"
  exit 1
fi

# noemail
add_user noemail noemail
if [ $? -ne 0 ]
then
  echo "ERROR! could not create noemail user"
  exit 1
fi

# remove this for now, don't want this script doing it.
#if [ -x ./installTestData.sh ]
##then
#	./installTestData.sh
#	if [ $? -ne 0 ]
#	then
#		echo "ERROR! durnig run of installTestData.sh"
#		exit 1
#	fi
#else
#	echo "ERROR! Either ./installTestData.sh doesn't exist or is not executable"
#	exit 1
#fi

#
# set up the all clear file
# Some tests look for this file to make sure there is data for use.

touch /home/fosstester/allClear
chmod a+rx /home/fosstester/allClear
chown fosstester:fosstester /home/fosstester/allClear

exit 0