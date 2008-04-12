#!/bin/bash
# fosscp agent
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

#
# NOTE: this code is bogus... Neal and I had a misunderstanding...this 
# code has been rewritten in php. See fosscp_agent.php.
#

# Set the path.
# If the paths in Makefile.conf change, then these will need to change.
export PATH=/usr/bin:/usr/local/fossology:/usr/local/fossology/agents:/usr/local
/fossology/test.d

# This agent should appear in the scheduler.conf as:
# agent=fosscopy | /usr/local/fossology/agents/engine-shell fosscp_agent '/usr/local/fossology/agents/fosscp_agent'

# engine-shell will convert all of the SQL columns into environment
# variables.  E.G. The MSQ will return pfile=... and pfile_fk=...
# These will become $ARG_pfile and $ARG_pfile_fk.
#
# Scheduler should pass in the following parameters
# archive
# folder_pk
# description (optional)
# name (optional), default is name of archive?
# recurse  : flag to indicate just files (0) or all contents(1)
#

tar='/bin/tar'
# Check Required parameters, save all parameters passed in
# if they are not saved they get over written on the next SQL.

if [ "$ARG_folder_pk" == "" ] ; then
  echo "FATAL: \$ARG_folder_pk not set. Aborting."
  exit -1
else
  parent_id=$ARG_folder_pk
fi
if [ "$ARG_recurse" == "" ] ; then
  echo "FATAL: \$ARG_recurse not set. Aborting."
  exit -1
else
  resurse=$ARG_recurse
fi
if [ "$ARG_upload_file" == "" ] ; then
  echo "FATAL: \$ARG_upload_file not set. Aborting."
  exit -1
else
  upload_file=$ARG_upload_file
fi
#
# Steps: 
# Check name and description
# make sure parent (folder_pk) exists
# if name given (or use default),
#   check to make sure name isn't associated with parent
#   IS: Fatal
#   NOT: create folder
#        get folder_pk of just created folder
#        create folderconents (use mode 1<<3, folder_pk, parent_id).
# depending on the recuse flag, tar up either just the files or the whole
# tree.
# schedule wget_agent on the upload_file
# schedule the default agents via fossjobs

if [ "$ARG_name" == "" ] ; then
  name=$ARG_upload_file
else
  name=$ARG_name
fi
if [ "$ARG_description" == "" ] ; then
  $ARG_description="Upload of $name"
else
  description=$ARG_description
fi

# Make sure the parent folder exists
echo "SELECT * FROM folder WHERE folder_pk = '$parent_id';"

if [ $ARG_folder_pk != $parent_id ]; then
  exit 1
fi

# folder name exists under the parent?
echo "SELECT * FROM leftnav WHERE name = '$name' AND parent = '$parent_id' AND foldercontents_mode = '1';"
if [ $ARG_folder_pk != "" ]; then
  exit 1
fi

# Create the folder
# Block SQL injection by protecting single quotes
#
#Protect the folder name with htmlentities. (this should happen in the plugin, before this is
# called.

// PostgreSQL quoting
$clean_name=`echo $name | sed -e "s#\'*#\'\'#pg"`
$c_description=`echo $description | sed -e "s#\'*#\'\'#pg"`

echo "!INSERT INTO folder (folder_name,folder_desc) VALUES ('$clean_name','$c_description');"

echo "SELECT folder_pk FROM folder WHERE folder_name='$clean_name' AND folder_desc='$c_description';"
if [ $ARG_folder_pk == "" ]; then
  echo "FATAL:Upload folder $clean_name was not get created, upload canceled"
  exit 1
else
  child_id=$ARGS_folder_pk
fi

echo "!INSERT INTO foldercontents (parent_fk,foldercontents_mode,child_id) VALUES ('$parent_id','1<<3','$child_id');"

# save files or the whole tree?
if [ $recurse == 1 ]; then
# tar up everything (check this incantation)
#res=`$tar cqf /tmp/fosscp_a_$$ $upload_file`
else
# save just the files (use find and a pipe)
# what is below doesn't work, get it to work on monday.
#`find fossology/ -maxdepth 1 -type f  -print | xargs -n 1 basename  > /tmp/fa.tarflist.$$`
#res=`$tar cqf /tmp/fosscp_f_$$ /tmp/fa.tarflist.$$`
fi

# use wget_agent to upload the file(s)
#wget_agent-d /tmp/fosscp.upload.$$ -k $upload_pk $upload_file

# schedule ununpack
# ununpack -C -d /tmp/?? -P -R -x 

# schedule default jobs via fossjobs
#/usr/local/bin/fossjobs 


exit 0;  # done successfully
