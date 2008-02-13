#!/bin/bash
# This script packages the directory into a tar file.
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

# make sure we're in a checked out svn copy
if [ ! -d .svn ] ; then
  echo "No SubVersion information found. This script requires an svn tree."
  exit 0
fi

# Check if SVN is available.  If not, then abort.
which svn >/dev/null 2>&1
if [ $? != 0 ] ; then
  echo "No SubVersion available."
  exit 1
fi
which svnversion >/dev/null 2>&1
if [ $? != 0 ] ; then
  echo "No svnversion available."
  exit 1
fi

######################################################################
# Package things up

eval `grep VERSION= Makefile.conf`

# SVN_REV is the last revision from svn info.  This is used for packaging.
SVN_REV="`svn info | grep '^Revision:' | awk '{print $2}'`"

TARBASE="fossology-$VERSION"
echo "Packaging $VERSION ($SVN_REV) into $TARBASE"

# Check for mixed revisions
# Warn if the current directory does not match SVN_REV, but allow it!
SVN_CURR="`svnversion -n .`"
if [ "$SVN_CURR" != "$SVN_REV" ] ; then
  echo "Revision ($SVN_REV) does not match current directory ($SVN_CURR)."
  echo "  Using $SVN_REV, not $SVN_CURR"
  echo "  To use $SVN_CURR, run 'svn ci' and 'svn up' before 'make tar'."
fi

[ -d "../$TARBASE" ] && rm -rf "../$TARBASE"
if [ -d "../$TARBASE" ] ; then
  echo "ERROR: Unable to delete ../$TARBASE"
  exit 2
fi

svn export -r "$SVN_REV" . "../$TARBASE" >/dev/null

# Process the directory
(
cd "../$TARBASE"

# Remove the dependency on svnversion; make the version static.
cp Makefile.conf Makefile.conf.svn
sed -e "s@^SVN_REV=.*@SVN_REV=${SVN_REV}@" Makefile.conf.svn > Makefile.conf
rm Makefile.conf.svn
)

# Create the tar
(
cd ..
[ -f "$TARBASE.tar.gz" ] && rm -f "$TARBASE.tar.gz"
tar --anchored --exclude="debian" -czf "$TARBASE.tar.gz" "$TARBASE"
)

# Clean up
rm -rf "../$TARBASE"

echo "../$TARBASE.tar.gz created."

