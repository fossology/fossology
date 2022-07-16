#!/usr/bin/env bash
# FOSSology utils.sh script
# SPDX-FileCopyrightText: © 2008-2014 Hewlett-Packard Development Company, L.P.

# SPDX-License-Identifier: GPL-2.0-only
#
# This script helps you install build and runtime dependencies on a system.
# It is NOT indented to replace package dependencies, it's just a tool to
# make testing the "upstream" build and install process. If you determine
# this script isn't installing something you think it should, consult
# the packaging metadata for the system in question as that is the
# canonical location for such info, then fix it there first and also
# update this file and the INSTALL document.

must_run_as_root() {
  # This must run as root.
  if [[ $(id -u) -ne 0 ]] ; then
    echo >&2 "ERROR: fo-installdeps must run as root."
    echo >&2 "Aborting."
    exit 1
  fi
}

need_lsb_release() {
  hash lsb_release 2>/dev/null || { cat >&2 <<EOF
ERROR: this program requires the lsb_release command. On Debian based
  systems this is probably in the lsb-release package, on
  Fedora/RedHat systems it is probably the redhat-lsb package.
Aborting.
EOF
  exit 1; }
}

show_help_for_mod_deps() {
  cat <<EOF
Usage: mod_deps [options]
  -r or --runtime    : install runtime dependencies
  -b or --buildtime  : install buildtime dependencies
  -e or --everything : install all dependencies (default)
  -y                 : Automatic yes to prompts
  -h or --help       : this help text
EOF
}

VERSION_PATTERN='([[:digit:]]+\.[[:digit:]]+\.[[:digit:]]+)(-?rc[[:digit:]]+)?-?([[:digit:]]*)-?[[:alnum:]]*'
VERSION_COMMAND="git describe --tags > /dev/null 2>&1 && git describe --tags | head -1 | sed -re 's/${VERSION_PATTERN}/\\1.\\3\\2/' | sed -re 's/\.$/.0/'"
