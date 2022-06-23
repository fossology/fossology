#! /bin/sh
# SPDX-FileCopyrightText: © 2019 Siemens AG

# SPDX-License-Identifier: GPL-2.0-only

_runojo()
{
  ./ojo "$@" | sed -e '1d'
}

_getLicenseName()
{
  echo `echo $1 | grep -o ": '[^']*" | cut -d\' -f2 | tr '\n' ' ' | rev | cut -c2- | rev`
}

test0BSD()
{
# test to see if the file exists
  testFile="../../../nomos/agent_tests/testdata/NomosTestfiles/SPDX/0BSD"
  if [ ! -f ${testFile} ]; then
    fail "ERROR: test file not found...aborting test"
  fi
  
  found="$( _runojo "${testFile}" )"
  out="$( _getLicenseName "$found" )"
  assertEquals "0BSD" "${out}"
}

testEmpty()
{
# test to see if the file exists
  testFile="../../../nomos/agent_tests/testdata/empty"
  if [ ! -f ${testFile} ]; then
    fail "ERROR: test file not found...aborting test"
  fi
  
  found="$( _runojo "${testFile}" )"
  out="$( _getLicenseName "$found" )"
  assertEquals "" "${out}"
}

testNoLic()
{
# test to see if the file exists
  testFile="../../../nomos/agent_tests/testdata/noLic"
  if [ ! -f ${testFile} ]; then
    fail "ERROR: test file not found...aborting test"
  fi
  
  found="$( _runojo "${testFile}" )"
  out="$( _getLicenseName "$found" )"
  assertEquals "" "${out}"
}

testDFSL1()
{
# test to see if the file exists
  testFile="../../../nomos/agent_tests/testdata/NomosTestfiles/SPDX/D-FSL-1.0"
  if [ ! -f ${testFile} ]; then
    fail "ERROR: test file not found...aborting test"
  fi
  
  found="$( _runojo "${testFile}" )"
  out="$( _getLicenseName "$found" )"
  assertEquals "D-FSL-1.0" "${out}"
}

testCCBYNCND1()
{
# test to see if the file exists
  testFile="../../../nomos/agent_tests/testdata/NomosTestfiles/SPDX/CC-BY-NC-ND-1.0"
  if [ ! -f ${testFile} ]; then
    fail "ERROR: test file not found...aborting test"
  fi
  
  found="$( _runojo "${testFile}" )"
  out="$( _getLicenseName "$found" )"
  assertEquals "CC-BY-NC-ND-1.0" "${out}"
}

testBSD2ClausePatent()
{
# test to see if the file exists
  testFile="../../../nomos/agent_tests/testdata/NomosTestfiles/SPDX/BSD-2-Clause-Patent"
  if [ ! -f ${testFile} ]; then
    fail "ERROR: test file not found...aborting test"
  fi
  
  found="$( _runojo "${testFile}" )"
  out="$( _getLicenseName "$found" )"
  assertEquals "BSD-2-Clause-Patent" "${out}"
}

testGFDL12Plus()
{
# test to see if the file exists
  testFile="../../../nomos/agent_tests/testdata/NomosTestfiles/SPDX/GFDL-1.2+"
  if [ ! -f ${testFile} ]; then
    fail "ERROR: test file not found...aborting test"
  fi
  
  found="$( _runojo "${testFile}" )"
  out="$( _getLicenseName "$found" )"
  assertEquals "GFDL-1.2+" "${out}"
}

testGFDL12OrLater()
{
# test to see if the file exists
  testFile="../../../nomos/agent_tests/testdata/NomosTestfiles/SPDX/GFDL-1.2-or-later"
  if [ ! -f ${testFile} ]; then
    fail "ERROR: test file not found...aborting test"
  fi
  
  found="$( _runojo "${testFile}" )"
  out="$( _getLicenseName "$found" )"
  assertEquals "GFDL-1.2-or-later" "${out}"
}

testGFDL12()
{
# test to see if the file exists
  testFile="../../../nomos/agent_tests/testdata/NomosTestfiles/SPDX/GFDL-1.2"
  if [ ! -f ${testFile} ]; then
    fail "ERROR: test file not found...aborting test"
  fi
  
  found="$( _runojo "${testFile}" )"
  out="$( _getLicenseName "$found" )"
  assertEquals "GFDL-1.2" "${out}"
}

testGFDL12Only()
{
# test to see if the file exists
  testFile="../../../nomos/agent_tests/testdata/NomosTestfiles/SPDX/GFDL-1.2-only"
  if [ ! -f ${testFile} ]; then
    fail "ERROR: test file not found...aborting test"
  fi
  
  found="$( _runojo "${testFile}" )"
  out="$( _getLicenseName "$found" )"
  assertEquals "GFDL-1.2-only" "${out}"
}

testMplBsd2MitApache2()
{
# test to see if the file exists
  testFile="../../../nomos/agent_tests/testdata/NomosTestfiles/SPDX/MPL-2.0_AND_BSD-2-Clause_AND_MIT_OR_Apache-2.0.txt"
  if [ ! -f ${testFile} ]; then
    fail "ERROR: test file not found...aborting test"
  fi
  
  found="$( _runojo "${testFile}" )"
  out="$( _getLicenseName "$found" )"
  assertEquals "MPL-2.0-no-copyleft-exception BSD-2-Clause MIT Apache-2.0 Dual-license" "${out}"
}
