#!/bin/bash
# This script creates the "uninstall.sh" script.
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

eval `grep "=" Makefile.conf | sed -e 'y/()/{}/'`

cat << EOF
#!/bin/bash
# This is the deinstallation script.
# It will make sure all the files are removed.

export DEBUG=""
export FORCEFILES=0
export FORCEREPO=0
while [ "\$1" != "" ] ; do
  if [ "\$1" == "-d" ] ; then
    DEBUG=echo
  elif [ "\$1" == "-f" ] ; then
    FORCEFILES=1
  elif [ "\$1" == "-R" ] ; then
    FORCEREPO=1
  else
    echo "Unknown command-line parameter: '\$1'"
    echo "Usage: \$0 [-d] [-f]"
    echo "  -d :: debug (do everything except the actual install)"
    echo "  -f :: forcefully remove all files (including configuration files)"
    echo "  -R :: forcefully remove the entire repository"
    exit;
  fi
  shift
done

# This must run as root.
if [ \`id -u\` != "0" ] ; then
  echo "This script must run as root.  Aborting."
  \$DEBUG exit
fi

# Set variables
`grep "=" Makefile.conf | sed -e 'y/()/{}/'`

# Get file list
FILELIST="`cd install ; find . -type f | sort | sed -e 's@\./@/@'` ${AGENTDATADIR}/License.bsam"

#######
# mkdir has -p to recursively create.
# rmdirp is a function to recursively delete.
function rmdirp
{
  D="\${1%/*}"
  if [ "\$D" == "\$1" ] ; then
    if [ "\$1" != "" ] && [ -e "\$1" ] && [ ! -d "\$1" ] ; then
      \$DEBUG \$RM "\$1"
    fi
  else
    if [ -d "\$1" ] ; then
      \$DEBUG \$RMDIR "\$1" > /dev/null 2>&1
    elif [ -e "\$1" ] ; then
      \$DEBUG \$RM "\$1"
    fi
    if [ ! -e "\$1" ] ; then
      # if it has been removed, then recurse
      rmdirp "\$D"
    fi
  fi
} # rmdirp()

# Remove files and empty directories
echo "\$FILELIST" | while read i ; do
  rmdirp "\$i"
done

# Remove other directories
\$DEBUG \$RM -rf "\$VARDATADIR"

# Remove configuration file and users IF forced to
if [ "\$FORCEFILES" == 1 ] ; then
  DBCONF="\$DATADIR/dbconnect/ossdb"
  REPOCONF="\$DATADIR/repository"
  SCHEDULERCONF="\$AGENTDATADIR/scheduler.conf"
  PROXYCONF="\$AGENTDATADIR/proxy.conf"
  for i in "\$DBCONF" "\$REPOCONF/Depth.conf" "\$REPOCONF/Hosts.conf" "\$REPOCONF/RepPath.conf" "\$SCHEDULERCONF" "\$PROXYCONF" ; do
    rmdirp "\$i"
  done

  # Remove the user and group.
  \$DEBUG userdel -r "\${PROJECTUSER}"
  \$DEBUG groupdel "\${PROJECTGROUP}"
fi # if force removal

# Remove entire repository if required
# All other removals are recoverable.  This one means starting all over!
# Also, this one may cross mount-mounts!
if [ "\$FORCEREPO" == 1 ] ; then
  \$DEBUG \$RM -rf "\$SRVDATADIR"
  \$DEBUG \$RM -rf "\$OPTDATADIR"
fi

EOF

