#! /bin/sh
# SPDX-FileContributor: Gaurav Mishra <mishra.gaurav@siemens.com>
# SPDX-FileCopyrightText: © 2024 Siemens AG
#
# SPDX-License-Identifier: GPL-2.0-only

_run_compatibility()
{
  ../../../../build/src/compatibility/agent/compatibility -f "$1" -t "$2" -r "$3"
}

_has_all_needles()
{
  haystack="$1"
  shift

  for needle do
    case "$haystack" in
      *"$needle"*) continue ;;
      *) echo "1"; return ;;
    esac
    shift
  done

  echo "0"
}

testCompatibleFiles()
{
# test where all licenses are compatible
  compatibleJson="../../../compatibility/agent_tests/testdata/compatible-output.json"
  licenseTypesCsv="../../../compatibility/agent_tests/testdata/license-map.csv"
  compatibilityRule="../../../compatibility/agent_tests/testdata/comp-rules-test.yaml"
  if [ ! -f ${compatibleJson} ]; then
    fail "ERROR: test file not found...aborting test"
  fi

  result="$( _run_compatibility "${compatibleJson}" "${licenseTypesCsv}" "${compatibilityRule}" )"
  out=$( _has_all_needles "$result" "0BSD,GPL-2.0-only::true" "0BSD,MIT::true" "GPL-2.0-only,MIT::true" )
  assertTrue "Fails for expected compatibilities ${result}" "${out}"
}

testIncompatibleFiles()
{
# test where all licenses are incompatible
  compatibleJson="../../../compatibility/agent_tests/testdata/incompatible-output.json"
  licenseTypesCsv="../../../compatibility/agent_tests/testdata/license-map.csv"
  compatibilityRule="../../../compatibility/agent_tests/testdata/comp-rules-test.yaml"
  if [ ! -f ${compatibleJson} ]; then
    fail "ERROR: test file not found...aborting test"
  fi

  result="$( _run_compatibility "${compatibleJson}" "${licenseTypesCsv}" "${compatibilityRule}" )"
  out=$( _has_all_needles "$result" "Apache-2.0-only,GPL-2.0-only::false" )
  assertTrue "Fails for expected incompatibilities ${result}" "${out}"
}
