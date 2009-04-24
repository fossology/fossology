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
# best if run as fosstester, root will work ok too.
#
echo "This script should be run as either the fosstester or root user"
thisdir=`pwd`
error_cnt=0
filelist='.bash_aliases .bashrc .subversion .svn'

if [ -r ./TestData/fosstester/ReadMe ]
then
	for file in $filelist
	do
		cp -r ./TestData/fosstester/$file /home/fosstester/ > /dev/null 2>&1
	done
else
	echo "ERROR! fosstester environment could not be found in $thisdir/TestData/fosstester/"
	let $error_cnt += 1
fi

if [ -d ./TestData/archives ]
then
  # need to suppress .svn and .subversion errors as we are copying from source
	cp -R ./TestData/archives ~fosstester > /dev/null 2>&1
else
	echo "ERROR! no $thisdir/TestData/archives directory found, could not install archives for testing"
	let $error_cnt += 1
fi

if [ -d ./TestData/licenses ]
then
	cp -R ./TestData/licenses ~fosstester > /dev/null 2>&1
else
	echo "ERROR! no $thisdir/TestData/license directory found, could not install licenses for testing"
	let $error_cnt += 1
fi

mkdir -p ~fosstester/public_html
if [ "$?" -ne 0 ]
then
	echo "ERROR!, could not create ~fosstester/public_html"
	let $error_cnt += 1
fi

cp ~fosstester/archives/fossDirsOnly.tar.bz2 ~fosstester/public_html
if [ "$?" -ne 0 ]
then
	echo "ERROR!, could not copy fossDirsOnly.tar.bz2 to fosstester/public_html"
	let $error_cnt += 1
fi
#
# now make a test dir in licenses for server upload testing
#
cd ~fosstester/licenses
mkdir -p Tdir
cp BSD_style_* Tdir

#
# make sure fosstester owns things and folks can read them.
#
cd ~fosstester
chown -R fosstester:fosstester archives licenses public_html
chmod -R a+rwx archives licenses public_html

if [ $error_cnt -ne 0 ]
then
	echo "There were previous errors, will exit with 1 (Fail)"
	exit 1
fi
exit 0