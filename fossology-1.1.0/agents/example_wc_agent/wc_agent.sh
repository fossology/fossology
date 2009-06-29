#!/bin/bash
# Example wc agent, written in shell script.
# This should be used with engine-shell.
#
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

# Set the path.
# If the paths in Makefile.conf change, then these will need to change.
export PATH=/usr/bin:/usr/local/fossology:/usr/local/fossology/agents:/usr/local/fossology/test.d

# This agent should appear in the scheduler.conf as:
# agent=wc | /usr/local/fossology/agents/engine-shell wc_agent '/usr/local/fossology/agents/wc_agent'

# engine-shell will convert all of the SQL columns into environment
# variables.  The MSQ will return pfile=... and pfile_fk=...
# These will become $ARG_pfile and $ARG_pfile_fk.

if [ "$ARG_pfile" == "" ] ; then
  echo "FATAL: \$ARG_pfile not set. Abording."
  exit -1
fi
if [ "$ARG_pfile_fk" == "" ] ; then
  echo "FATAL: \$ARG_pfile_fk not set. Abording."
  exit -1
fi

# Get the path to the actual file
RepFile=`reppath files "$ARG_pfile"`

# Get the word-count values and insert them into the database using dbinit.
wc "$RepFile" 2>/dev/null | while read Lines Words Bytes Name ; do
  # Convert wc to an SQL statement
  echo "!INSERT INTO agent_wc (pfile_fk,wc_words,wc_lines) VALUES ($ARG_pfile_fk,$Words,$Lines);"
  # The initial "!" tells dbinit to ignore insert failures.
  # Don't worry about checking if the value exists... If it did exist, then
  # the MSQ would have never called this program.
  # And if two agents happen to run on the same data, then the DB constraint
  # for unique values will prevent duplicates.
done | dbinit -

exit 0;  # done successfully
