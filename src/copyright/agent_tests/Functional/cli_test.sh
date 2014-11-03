#! /bin/sh
#
# Author: Daniele Fognini, Andreas Wuerl
# Copyright (C) 2013-2014, Siemens AG
# 
# This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License version 2 as published by the Free Software Foundation.
# 
# This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
# 
# You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.


_runcopyright()
{
  ./copyright -T 1 "$1" | sed -e '1d'
}

_runcopyrightPositives()
{
  ./copyright --regex '<s>([^<]*)</s>' --regexId 1 -T 0 "$1" | sed -e '1d'
}

_checkFound()
{

echo "$1"
   while IFS="[:]'" read initial start length type unused content unused; do
      echo "i=$initial s=$start l=$length t=$type c='$content' u=$unused"
   done <<EO1
$1
EO1

}

testAll()
{
  for file_raw in ../testdata/*02_raw; do
    file=${file_raw%_raw}
    expectedPositives="$( _runcopyrightPositives "$file_raw" )"
    found="$( _runcopyright "$file" )"
    _checkFound "$expectedPositives" "$found"
  done
}

