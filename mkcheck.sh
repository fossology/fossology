#!/bin/bash
# This script creates the "check.sh" script.
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

cat << EOF
#!/bin/bash
# This is the check script.
# It will make sure that every operation dependency is available and
# configured properly.  If there is an issue, it will identify it but
# not correct it.  (This is only a "check" script, not a "fix" script.)
# This code is indentionally implemented independently for cross-validation.

export PATH=/sbin:/bin:/usr/sbin:/usr/bin:/usr/local/sbin:/usr/local/bin
export CHECKREPO=0
export CHECKSCHEDULER=0
export ERRORS=/tmp/fosscheck.\$\$

while [ "\$1" != "" ] ; do
  if [ "\$1" == "-R" ] ; then
    CHECKREPO=1
  elif [ "\$1" == "-S" ] ; then
    CHECKSCHEDULER=1
  else
    echo "Unknown command-line parameter: '\$1'"
    echo "Usage: \$0 [options]"
    echo "  -R :: check the entire repository (could be very slow)"
    echo "  -S :: check the scheduler's configuration"
    exit;
  fi
  shift # unused right now, but will be used if there are command-line options.
done

# Set variables
rm -f \$ERRORS
`grep "=" Makefile.conf | sed -e 'y/()/{}/'`

#####################################################
# Check for build dependencies
echo "Checking build dependencies..."
for i in include/openssl/md5.h include/postgresql/libpq-fe.h include/extractor.h lib/libextractor.so ; do
  if [ ! -r "/usr/\$i" ] && [ ! -r "/usr/local/\$i" ] ; then
    echo "ERROR: Build dependency missing: \$i"
    touch \$ERRORS
  fi
done

#####################################################
# Make sure user and groups exist
grep -q "^\${PROJECTUSER}:" /etc/passwd
if [ "\$?" != 0 ] ; then
  echo "ERROR: User '\$PROJECTUSER' does not exist in /etc/passwd."
  touch \$ERRORS
fi
grep -q "^\${PROJECTGROUP}:" /etc/group
if [ "\$?" != 0 ] ; then
  echo "ERROR: Group '\$PROJECTGROUP' does not exist in /etc/group."
  touch \$ERRORS
fi
# make sure the user is in the group
groups "\$PROJECTUSER" | sed -e 's/.*://' | ( grep -q -w "\$PROJECTGROUP"
if [ "\$?" != 0 ] ; then
  echo "ERROR: User '\$PROJECTUSER' is not a member of group '\$PROJECTGROUP'."
  touch \$ERRORS
fi
)

#####################################################
# Get file list
FILELIST="`cd install ; find . -type f | sort | sed -e 's@^\./@/@' | grep -v init.d`"

FILELISTROOT="`cd install ; find . -type f | sort | sed -e 's@^\./@/@' | grep init.d`"

LINKLIST="`cd install ; find . -type l | sort | sed -e 's@^\./@/@'`"

#####################################################
# Make sure all files exist and have the right permissions
echo "Checking installed files..."
echo "\$FILELIST" | while read i ; do
  # every file should be in group "fossy", group readable, and not "other".
  DIR="\${i%/*}"
  NAME="\${i##*/}"
  if [ ! -e "\$i" ] ; then
    echo "ERROR: '\$i' not found."
    touch \$ERRORS
  else
    CHECK1=\`find "\$DIR" -follow -name "\$NAME" -group "\$PROJECTGROUP" -print 2>/dev/null\`
    CHECK2=\`find "\$DIR" -follow -name "\$NAME" \\( -perm -g=r -a ! -perm -o=rwx \\) -print 2>/dev/null\`
    if [ "\$CHECK1" == "" ] ; then
      echo "ERROR: '\$i' must be '\$PROJECTGROUP'."
      touch \$ERRORS
    fi
    if [ "\$CHECK2" == "" ] ; then
      echo "ERROR: '\$i' must be group readable and not other."
      touch \$ERRORS
    fi
  fi
done

echo "\$FILELISTROOT" | while read i ; do
  # Just make sure they exist and are executable
  DIR="\${i%/*}"
  NAME="\${i##*/}"
  if [ ! -e "\$i" ] ; then
    echo "ERROR: '\$i' not found."
    touch \$ERRORS
  else
    CHECK1=\`find "\$DIR" -follow -name "\$NAME" -perm -u=x -print 2>/dev/null\`
    CHECK2=\`find "\$DIR" -follow -name "\$NAME" \\( ! -perm -g=w -a ! -perm -o=w \\) -print 2>/dev/null\`
    if [ "\$CHECK1" == "" ] ; then
      echo "ERROR: '\$i' must be executable."
      touch \$ERRORS
    fi
    if [ "\$CHECK2" == "" ] ; then
      echo "ERROR: '\$i' permissions must be not be writable by anyone but the owner."
      touch \$ERRORS
    fi
  fi
done

echo "\$LINKLIST" | while read i ; do
  # every file should be in group "fossy", group readable, and not "other".
  # but the link may be a directory
  DIR="\${i%/*}"
  NAME="\${i##*/}"
  if [ ! -e "\$i" ] ; then
    echo "ERROR: Link '\$i' not found."
    touch \$ERRORS
  else
    CHECK1=\`find "\$DIR" -follow -name "\$NAME" -xtype f -group "\$PROJECTGROUP" -print 2>/dev/null\`
    CHECK2=\`find "\$DIR" -follow -name "\$NAME" -xtype d -print 2>/dev/null\`
    CHECK3=\`find "\$DIR" -follow -name "\$NAME" \\( -perm -g=r -a ! -perm -o=rwx \\) -print 2>/dev/null\`
    # If there is no file and it is not a directory...
    if [ "\$CHECK1" == "" ] && [ "\$CHECK2" == "" ] ; then
      echo "ERROR: Link target '\$i' must be group '\$PROJECTGROUP'."
      touch \$ERRORS
    fi
    if [ "\$CHECK3" == "" ] ; then
      echo "ERROR: Link target '\$i' permissions must be group readable and not other."
      touch \$ERRORS
    fi
  fi
done

# Check for config files
DBDIR="\$DATADIR/dbconnect"
DBCONF="\$DBDIR/\${PROJECT}"
REPOCONF="\$DATADIR/repository"
SCHEDULERCONF="\$AGENTDATADIR/scheduler.conf"
PROXYCONF="\$AGENTDATADIR/proxy.conf"

echo "Checking configuration files..."
for i in "\$DBCONF" "\$REPOCONF/Depth.conf" "\$REPOCONF/Hosts.conf" "\$REPOCONF/RepPath.conf" "\$SCHEDULERCONF" "\$PROXYCONF" ; do
  DIR="\${i%/*}"
  NAME="\${i##*/}"
  if [ ! -f "\$i" ] ; then
    echo "ERROR: Configuration file '\$i' does not exist."
    touch \$ERRORS
  else
    CHECK1=\`find "\$DIR" -name "\$NAME" \\( -perm -g=r -a ! -perm -o=rwx \\) -print 2>/dev/null\`
    if [ "\$CHECK1" == "" ] ; then
      echo "ERROR: Configuration file '\$i' permissions must be group readable and not other."
    touch \$ERRORS
    fi
  fi
done

# Check directories for group usage
REPO="\`cat \$REPOCONF/RepPath.conf 2>/dev/null\`"
REPO="\${REPO%/}"
for i in "\$VARDATADIR" "\$REPO" ; do
  if [ ! -d "\$i" ] ; then
    echo "ERROR: Directory '\$i' does not exist."
    touch \$ERRORS
  else
    CHECK1=\`find "\$i" -maxdepth 0 \\( -perm -g=srwx -a ! -perm -o=rwx \\) -print 2>/dev/null\`
    if [ "\$CHECK1" == "" ] ; then
      echo "ERROR: Directory '\$i' permissions must be group srwx and not other."
      touch \$ERRORS
    fi
  fi
done

############################################################
# Check run-time requirements
echo "Checking run-time requirements..."
grep -q "^www-data:" /etc/group
if [ "\$?" == 0 ] ; then
  groups www-data | grep -w -q "\$PROJECTGROUP"
  if [ "\$?" != 0 ] ; then
    echo "ERROR: user 'www-data' is not in group '\$PROJECTGROUP'."
    touch \$ERRORS
  fi
fi

# Look for ununpack requirements
\$AGENTDIR/ununpack 2>&1 | grep "not found in" | while read i ; do
  echo "ERROR: \$i"
  touch \$ERRORS
done

############################################################
# Check the scheduler!
if [ "\$CHECKSCHEDULER" != "0" ] ; then
  \$AGENTDIR/scheduler -t -q
  if [ "\$?"  != "0" ] ; then
    echo "ERROR: Scheduler failed.  Check \$SCHEDULERCONF"
    touch \$ERRORS
  fi
fi

############################################################
# Check the full repo!
if [ "\$CHECKREPO" != "0" ] ; then
  echo "Checking the repository..."
  if [ "\$REPO" == "" ] ; then
    echo "ERROR: Unable to check the repository."
    touch \$ERRORS
  else
    find "\$REPO" ! -group \$PROJECTGROUP ! -perm -g=rw | while read i ; do
      echo "ERROR: Repository not group '\$PROJECTGROUP' accessible: \$i"
      touch \$ERRORS
    done
  fi
fi

############################################################
echo "Check completed."
if [ ! -f "\$ERRORS" ] ; then
  echo "No problems detected!"
else
  echo "Please correct the issues before continuing."
  rm -f "\$ERRORS"
fi

exit

EOF

