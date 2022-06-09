#! /bin/sh
#
# Author: Daniele Fognini, Andreas Wuerl
# SPDX-FileCopyrightText: © 2013-2015, 2018 Siemens AG
# 
# SPDX-License-Identifier: GPL-2.0-only


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
