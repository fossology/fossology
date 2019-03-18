#! /bin/sh
#
# Author: Daniele Fognini, Andreas Wuerl
# Copyright (C) 2013-2015, 2018 Siemens AG
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

_runcopyright()
{
  ./copyright "$@" | sed -e '1d'
}

_runcopyrightPositives()
{
  ./copyright --regex '1@@<s>([^\0]*?)</s>' -T 0 "$1" | sed -e '1d'
}

_checkFound()
{
  while IFS="[:]'" read initial start length type unused content unused; do
    found="not found"
    while IFS="[:]'" read initial2 start2 length2 type2 unused2 content2 unused2; do
      if [ "x$content2" = "x$content" ]; then
        found="$content"
        break
      fi
    done <<EO2
$2
EO2

    assertEquals "$content" "$found"
    echo "$content"
  done <<EO1
$1
EO1

}

testAll()
{
  for file_raw in ../testdata/testdata3_raw; do
    file=${file_raw%_raw}
    expectedPositives="$( _runcopyrightPositives "$file_raw" )"
    found="$( _runcopyright -T 1 "$file" )"
    _checkFound "$expectedPositives" "$found"
  done
}

testUserRegexesDefault()
{
  tmpfile=$(mktemp)
  assertTrue $?
  echo "test one" > "$tmpfile"

  out=$( _runcopyright -T 0 --regex 'test[[:space:]]*o' "$tmpfile" )

  expected=$(printf "\t[0:6:cli] 'test o'")

  assertEquals "$expected" "$out"

  rm "$tmpfile"
}

testUserRegexesName()
{
  tmpfile=$(mktemp)
  assertTrue $?
  echo "test one" > "$tmpfile"

  out=$( _runcopyright -T 0 --regex 'test@@test[[:space:]]*o' "$tmpfile" )

  expected=$(printf "\t[0:6:test] 'test o'")

  assertEquals "$expected" "$out"

  rm "$tmpfile"
}

testUserRegexesNameAndGroup()
{
  tmpfile=$(mktemp)
  assertTrue $?
  echo "test one" > "$tmpfile"

  out=$( _runcopyright -T 0 --regex 'test@@1@@t(es)t[[:space:]]*' "$tmpfile" )

  expected=$(printf "\t[1:3:test] 'es'")

  assertEquals "$expected" "$out"

  rm "$tmpfile"
}

testUserRegexesNameAndGroup2()
{
  tmpfile=$(mktemp)
  assertTrue $?
  echo "test one" > "$tmpfile"

  out=$( _runcopyright -T 0 --regex 'test@@0@@t(es)t[[:space:]]*o' "$tmpfile" )

  expected=$(printf "\t[0:6:test] 'test o'")

  assertEquals "$expected" "$out"

  rm "$tmpfile"
}
