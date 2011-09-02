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


FOSSYUID=`id -un`
if [ "$FOSSYUID" != "fossy" ];then
  echo "Must be fossy to run this script."
  exit 1
fi

#echo $UID

#rm -rf /srv/fossology/repository/localhost/gold/*
#rm -rf /srv/fossology/repository/localhost/files/*

if [ $? -ne 0 ];
then
  echo "Delete Test database Error!"
  exit 1
fi

echo "Clean Test repository success!"
