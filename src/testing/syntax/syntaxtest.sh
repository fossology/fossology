#!/bin/bash
# SPDX-FileCopyrightText: © Fossology contributors

# SPDX-License-Identifier: GPL-2.0-only

# number of threads to use
threads=4

function usage()
{
    echo "Check syntax of source files (currently only PHP scripts)" >&2
    echo >&2
    echo "Usage: $0 [-hvt]" >&2
    echo "  -h: print this help message" >&2
    echo "  -t: number of threads to use (default $threads)" >&2
    echo "  -v: verbose output" >&2
}

verbose=0

function isVerbose()
{
    if [ "$verbose" -eq 0 ]
    then
        return 1
    fi

    return 0
}

while getopts ":ht:v" opt
do
  case "$opt" in
    t)
      threads=$OPTARG
      ;;

    v)
      verbose=1
      ;;

    h | \?)
      usage
      exit 1
      ;;

    :)
      echo "ERROR: argument -$OPTARG requires a value" >&2
      usage
      exit 1
      ;;
      
  esac
done

# ensure the php binary exists
if ! which php >/dev/null
then
    echo "ERROR: could not find php binary" >&2
    exit 1
fi

if isVerbose
then
    echo "Using PHP binary: $(which php)"
fi

# make sure we're in the src directory
cd $(dirname $0)/../..

if isVerbose
then
    echo "Scanning directory: $(pwd)"
fi

if [ "${PWD##*/}" != "src" ]
then
    echo "ERROR: could not navigate to src directory" >&2
    exit 1
fi

# extra verbose output
if isVerbose
then
    echo "Using $threads threads"
fi

# scan each of the php files
phpScanResults=$(find . -not \( -path ./vendor -prune \) -not \( -path ./nomos/agent_tests/testdata -prune \) -type f -name \*.php | xargs -L1 -P$threads -I {} sh -c "php --syntax-check {} || :")
phpScanErrors=$(echo "$phpScanResults" | egrep -v '^No syntax errors detected in' | egrep -v '^$')

if isVerbose
then
    echo "=== raw results ==="
    echo "$phpScanResults"
    echo "=== end raw results ==="
    echo
fi

if [ "$phpScanErrors" != "" ]
then
    echo "Syntax errors detected:"
    echo "$phpScanErrors"
    exit 1
fi

echo "No syntax errors detected"
exit 0
