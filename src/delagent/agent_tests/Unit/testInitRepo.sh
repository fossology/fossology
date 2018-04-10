#!/bin/sh
#/*********************************************************************
#Copyright (C) 2011 Hewlett-Packard Development Company, L.P.
#
#Copyright (C) 2018 Siemens AG
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

REPODIR=$1

if [ ! -d $REPODIR/localhost/ ]; then
  mkdir -p $REPODIR/localhost/
fi

tar -xf ../testdata/testrepo_gold.tar.gz -C $REPODIR/localhost/
tar -xf ../testdata/testrepo_files.tar.gz -C $REPODIR/localhost/

echo "Create Test Repository success!"
