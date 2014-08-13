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


_runmonk()
{
  ../../agent/monk "$@"
}

_testAllFull()
{
  out="$( _runmonk "$@" | grep 'full match' )"

  if [ -z "${out}" ]; then
    fail "no result for $*"
  else
    while IFS='"' read t1 a t2 b t3; do
      lic="$(basename "$a")"
      licExpected="$b"
      assertEquals "$lic" "$licExpected"
    done <<EOF
$out
EOF
  fi
}

_testAllNone(){
  out="$( _runmonk -v "$@" | grep 'no match' )"

  if [ -z "${out}" ]; then
    fail "no result for $*"
  else
    while IFS='"' read t1 a t2; do
      found=''
      for file; do
        if [ "x$file" = "x$a" ]; then
          found='yes'
        fi
      done
      assertEquals "$found" "yes"
    done <<EOF
$out
EOF
  fi
}

_testADiff(){
  testFile="$1"
  expectedLic="$2"

  shift 2
  out="$( _runmonk "$testFile" | grep "diff match between \"$testFile\" and \"$expectedLic\"" )"

  if [ -z "${out}" ]; then
    echo "Was waiting for: 'diff match between \"$testFile\" and \"$expectedLic\"' here" >&2
    _runmonk "$testFile" >&2
    fail "diff match not found for $testFile"
  fi
}

# \file cli_test.sh:testAllSingle
# \brief Perform a cli license analysis on all test files one by one
#       License returned should equal fileName
#

testAllSingle()
{
  for file in ../testlicenses/expectedFull/*; do
    _testAllFull "$file"
  done
}

# \file cli_test.sh:testAll
# \brief Perform a cli license analysis on all test files together
#       License returned should equal fileName
#

testAll()
{
  _testAllFull ../testlicenses/expectedFull/*
}

# \file cli_test.sh:testOneNegative
# \brief Perform a cli license analysis on a file which is not a license
#       There should be no output
#

testOneNegative()
{
  echo "This is not a license" > /tmp/negative
  out="$( _runmonk /tmp/negative )"

  assertNull "$out"

  rm -f /tmp/negative
}

# \file cli_test.sh:testNegativeWithVerbose
# \brief Perform a cli license analysis on a file which is not a license in verbose mode
#       The output should be no license found
#

testNegativeWithVerbose()
{
  echo "This is not a license" > /tmp/negative1
  echo "This is not a license either" > /tmp/negative2
  echo "This is one too" > /tmp/negative3

  _testAllNone /tmp/negative*

  rm -f /tmp/negative*
}

# \file cli_test.sh:testAllDiffs
# \brief Perform a cli license analysis on all diff test files one by one
#

testAllDiffs()
{
  for file in ../testlicenses/expectedDiff/*; do
    expectedLic="$(basename "$file")"
    expectedLic="${expectedLic%%,*}"
    _testADiff "$file" "$expectedLic"
  done
}

