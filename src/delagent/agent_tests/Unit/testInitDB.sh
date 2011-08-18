#!/bin/sh
#/*********************************************************************
#Copyright (C) 2011 Hewlett-Packard Development Company, L.P.
#
#This program is free software; you can redistribute it and/or
#modify it under the terms of the GNU General Public License
#version 2 as published by the Free Software Foundation.
#
#This program is distributed in the hope that it will be useful,
#but WITHOUT ANY WARRANTY; without even the implied warranty of
#MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
#GNU General Public License for more details.
#
#You should have received a copy of the GNU General Public License along
#with this program; if not, write to the Free Software Foundation, Inc.,
#51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
#*********************************************************************/

VARS=../../../../Makefile.conf

UID=`id -un`
if [ "$UID" != "fossy" ];then
  echo "Must be fossy to run this script."
  exit 1
fi

echo $UID

createdb fossologytest
if [ $? -ne 0 ];
then
  echo "Create Test database Error!"
  exit 1
fi

pg_restore -d fossologytest ../testdata/testdb_all.tar

PREFIX=`cat $VARS|grep -i '^PREFIX'|awk -F = '{print $2}'`
cp $PREFIX/etc/fossology/Db.conf ~/
sed -i 's/fossology/fossologytest/' ~/Db.conf
cp ~/Db.conf $PREFIX/etc/fossology/Db.conf

echo "Create Test database success!"
