#!/bin/bash
# This script creates the "install.sh" script.
# Copyright (C) 2007 Hewlett-Packard Development Company, L.P.
#
#  This program is free software; you can redistribute it and/or
#  modify it under the terms of the GNU General Public License
#  version 2 as published by the Free Software Foundation.
#  
#  This program is distributed in the hope that it will be useful,
#  but WITHOUT ANY WARRANTY; without even the implied warranty of
#  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
#  GNU General Public License for more details.
#  
#  You should have received a copy of the GNU General Public License along
#  with this program; if not, write to the Free Software Foundation, Inc.,
#  51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
#
# Simple script for determining what RPM can display.
# The output (after testing) is used in specagent.c for the popen() call.
echo -n "rpm -q --queryformat '"
echo "
Name
Epoch
Version
Release
Vendor
URL
Copyright
License
Distribution
Packager
Group
Icon
Summary
Obsoletes
Provides
Source
Patch
" \
| while read i ; do
if [ "$i" != "" ] ; then
  echo -n "$i: %{$i}\\n"
fi
done
echo "Requires:\\n' -R --specfile test.spec 2>/dev/null | grep -v '(none)'"

