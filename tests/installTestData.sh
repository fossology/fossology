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
#
# install test data into the fosstester account for use in testing
#
# NOTE: assumes being executed from the sources!
#
# must be run as fosstester.
#
thisdir=`pwd`
error_cnt=0
filelist='.bash_aliases .bashrc .subversion .svn'

if [ -r ./TestData/fosstester/ReadMe ]
then
	for file in $filelist
	do
		cp -r ./TestData/fosstester/$file /home/fosstester/
	done
else
	echo "ERROR! fosstester environment could not be found in $thisdir/TestData/fosstester/"
	let $error_cnt += 1
fi

if [ -d ./TestData/archives ]
then
	cp -R ./TestData/archives ~fosstester
else
	echo "ERROR! no $thisdir/TestData/archives directory found, could not install archives for testing"
	let $error_cnt += 1
fi

if [ -d ./TestData/licenses ]
then
	cp -R ./TestData/licenses ~fosstester
else
	echo "ERROR! no $thisdir/TestData/license directory found, could not install licenses for testing"
	let $error_cnt += 1
fi


if [ $error_cnt -ne 0 ]
then
	echo "There were previous errors, will exit with 1 (Fail)"
	exit 1
fi
