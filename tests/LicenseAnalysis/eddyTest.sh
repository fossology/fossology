#!/bin/sh
#
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
#
#
# eddyTest, run the eddy test files through nomos (glen's version).
#
# quick and dirty, like all shell scripts.

# should take parms and check it/them... should also check for root, as 
# the old nomos liked to run as root......later.

nomos="/OSRB/bin/nomos"
#eddyfileroot="/home/markd/all/GPL/GPL_v3"
eddyfileroot="/home/markd/all"
results="/home/markd/GnomosResults-`date '+%F'`"
raw="/home/markd/GnomosResults-`date '+%F'`.raw"
# since we append to raw, remove it so the next run is clean
rm $raw
#
# note, this will create a file with a long line of junk at the top, just 
# remove it.with an editor.
cd /home/markd
filelist="`find $eddyfileroot -type f`"
for file in $filelist
do
    echo -n "$file: " >> $raw
    $nomos --file $file >> $raw
done

#
# sed the raw results into final form
#
sed -e '1,$s/\/home\/markd\/all\///' $raw > $results
