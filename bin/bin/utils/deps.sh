#!/usr/bin/env bash

source "$(dirname "${BASH_SOURCE[0]}")/fo_deps/utils.sh"

show_help() {
  cat <<EOF
Usage: install.sh [options]
  -r or --runtime    : install runtime dependencies
  -b or --buildtime  : install buildtime dependencies
  -e or --everything : install all dependencies (default)
  -o or --offline    : do not run composer installation
  -y                 : Automatic yes to prompts
  -h or --help       : this help text
EOF
}

YesOpt=''
OFFLINE=''
EVERYTHING=''
RUNTIME=''
BUILDTIME=''

OPTS=$(getopt -o rbeohy --long runtime,buildtime,everything,offline,help -n 'install.sh' -- "$@")

if [[ $? -ne 0 ]]; then
   OPTS="--help"
fi

eval set -- "$OPTS"

if [[ $OPTS == ' --' || $OPTS == ' -y --' ]]; then
  EVERYTHING=true
fi

while true; do
   case "$1" in
      -r|--runtime)     RUNTIME=true; shift;;
      -b|--buildtime)   BUILDTIME=true; shift;;
      -e|--everything)  EVERYTHING=true; shift;;
      -o|--offline)     OFFLINE=true; shift;;
      -y)               YesOpt='-y'; shift;;
      -h|--help)        show_help; exit;;
      --)               shift; break;;
      *)                echo "ERROR: option $1 not recognised"; exit 1;;
   esac
done

set -o errexit -o nounset -o pipefail

must_run_as_root
need_lsb_release

if [[ $EVERYTHING ]]; then
  echo "*** Installing both runtime and buildtime dependencies ***"
  RUNTIME=true
  BUILDTIME=true
fi

if [[ $BUILDTIME ]]; then
  echo "*** Calling fo_buildtime.sh to install buildtime dependencies ***"
  ./fo_deps/fo_buildtime.sh "$YesOpt" &
fi

if [[ $RUNTIME ]]; then
  echo "*** Calling fo_runtime.sh to install runtime dependencies ***"
  ./fo_deps/fo_runtime.sh "$YesOpt" &
fi

wait

# Install composer if not in offline mode
if [[ ! $OFFLINE ]]; then
  echo "*** Installing composer dependencies ***"
  ./fo_deps/fo_composer.sh
fi
