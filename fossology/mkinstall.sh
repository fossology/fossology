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

cat << EOF
#!/bin/bash
# This is the installation script.
# It will make sure all the files have the correct permissions,
# configure things as needed, and generate intelligent default settings.

export PATH=/sbin:/bin:/usr/sbin:/usr/bin:/usr/local/sbin:/usr/local/bin

export DEBUG=""
export INSTALLFILES=0
export INSTALLLICENSES=1
export FORCEFILES=0
export PLACEFILES=1
export IGNORECPERRORS=0
export CONFIGOK=1
export CONFIGDB=1
export CONFIGPROXY=1
export CONFIGREPO=1
export CONFIGSCHEDULER=1
export CHECKREPO=0
while [ "\$1" != "" ] ; do
  if [ "\$1" == "-d" ] ; then
    DEBUG=echo
  elif [ "\$1" == "-I" ] ; then
    PLACEFILES=0
  elif [ "\$1" == "-C" ] ; then
    PLACECONFIG=0
  elif [ "\$1" == "-i" ] ; then
    INSTALLFILES=1
    PLACEFILES=1
  elif [ "\$1" == "-F" ] ; then
    IGNORECPERRORS=1
  elif [ "\$1" == "-f" ] ; then
    FORCEFILES=1
    PLACEFILES=1
  elif [ "\$1" == "-u" ] ; then
    FORCEFILES=1
    PLACEFILES=1
    INSTALLFILES=1
    INSTALLLICENSES=0
  elif [ "\$1" == "-R" ] ; then
    CHECKREPO=1
  else
    echo "Unknown command-line parameter: '\$1'"
    echo "Usage: \$0 [options]"
    echo "  -d :: debug (do everything except the actual install)"
    echo "  -f :: forcefully install all files"
    echo "  -F :: force copies -- ignore any 'file in use' copy errors"
    echo "  -i :: install files that are missing"
    echo "  -u :: update files (but do not change licenses)"
    echo "  -I :: do not install files"
    echo "  -C :: do not create configuration files"
    echo "  -R :: check permissions in the repository (can be slow for massive systems)"
    exit;
  fi
  shift
done

# This must run as root.
if [ \`id -u\` != "0" ] ; then
  echo "ERROR: The install must run as root."
  echo "To fix this, run this command as root: sudo \$0 -f"
  echo "Aborting."
  \$DEBUG exit 1
fi

# Set variables
`grep "=" Makefile.conf | sed -e 'y/()/{}/'`

#####################################################
if [ "\$PLACEFILES" != "0" ] ; then
# Get file list
FILELIST="`cd install ; find . -type f | sort | sed -e 's@^\./@@' | grep -v 'etc/default/fossology'`"

LINKLIST="`cd install ; find . -type l | sort | sed -e 's@^\./@@'`"

# Make sure the user and group exists.
if [ "\$DESTDIR" == "" ] ; then
  grep -q "^\${PROJECTGROUP}:" /etc/group || groupadd "\${PROJECTGROUP}"
  grep -q "^\${PROJECTUSER}:" /etc/passwd
  if [ "\$?" != "0" ] ; then
     useradd -c "\${PROJECT}" -g "\${PROJECTGROUP}" -m -s /bin/bash -d "\${PROJECTHOME}" "\${PROJECTUSER}"
     if [ "\$?" != "0" ] ; then
       echo "ERROR: Unable to create user '\${PROJECTUSER}'"
       echo "       The command was:"
       echo "       useradd -c '\${PROJECT}' -g '\${PROJECTGROUP}' -m -s /bin/bash -d '\${PROJECTHOME}' '\${PROJECTUSER}'"
       exit 1
     fi
  fi
else
  # Installing to a destdir.  User/group better exist.
  echo "WARNING: Not creating user '\${PROJECTUSER}'"
  echo "WARNING: Not creating group '\${PROJECTGROUP}'"
fi

# A few notes: I chose useradd/groupadd over adduser/addgroup because of
# better shadow password support.
# Also, if you are ONLY using this on the local system, then you can
# set the user's shell to /bin/false.  The shell is only needed when using
# ssh to control agents on remote hosts.

# Set file permissions: Owned by user, Group accessible, no other.
echo "# Checking files (exist and permissions)"
# Remove obsolete licenses
\$DEBUG \$RM -rf "\${AGENTDATADIR}/licenses"
# Remove obsolete ui
if [ "\${WEBDIR}" != "" ] ; then
  \$DEBUG \$RM -rf "\${WEBDIR}/"*
fi
echo "\$FILELIST" | while read i ; do
  if [ "\$FORCEFILES" == 1 ] || [ ! -f "/\$i" ] ; then
    if [ "\$FORCEFILES" == 1 ] ; then
      echo "Installing /\$i"
      DIR="\${i%/*}"
      [ ! -d "/\$DIR" ] && \$DEBUG \$MKDIR -p "/\$DIR"
      [ -f "/\$i" ] && \$DEBUG \$RM -f "/\$i"
      \$DEBUG \$CP -p "install/\$i" "/\$i"
      if [ \$? != 0 ] && [ "\$IGNORECPERRORS" == 0 ] ; then
	echo "ERROR: Copy of 'install/\$i' to '/\$i' failed."
	\$DEBUG exit -1
      fi
    elif [ "\$INSTALLFILES" == 1 ] ; then
      echo "File /\$i is missing.  Installing."
      DIR="\${i%/*}"
      [ ! -d "/\$DIR" ] && \$DEBUG \$MKDIR -p "/\$DIR"
      \$DEBUG \$CP -p "install/\$i" "/\$i"
      if [ \$? != 0 ] && [ "\$IGNORECPERRORS" == 0 ] ; then
	echo "ERROR: Copy of 'install/\$i' to '/\$i' failed."
	\$DEBUG exit -1
      fi
    else
      echo "File /\$i is missing."
      echo "If you are running this install script by hand,"
      echo "then use '\$0 -f' to install the files."
      echo "If you are running this install script through a package installer,"
      echo "then check to make sure the package not corrupted -- because it"
      echo "seems to be missing a file."
      \$DEBUG exit -1
    fi
  fi
  # user and group permissions should be set by tar.  Do not change them.
  \$DEBUG chmod o-rwx "/\$i"
  \$DEBUG chmod g-w "/\$i"
  if [ "\$i" == "\${i/fossology/}" ] ; then
    # Not in a fossology directory
    \$DEBUG chmod a+r "/\$i"
  fi
  \$DEBUG chown \${PROJECTUSER}:\${PROJECTGROUP} "/\$i"
done
# Fix one file (should be owned by root)
\$DEBUG chown root:root /etc/init.d/fossology
\$DEBUG chmod 755 /etc/init.d/fossology
# Ensure that the web directory is group-accessible by fossy.
\$DEBUG chown fossy:fossy \$WEBDIR
\$DEBUG chmod 775 \$WEBDIR

for i in \$LINKLIST ; do
  if [ "\$FORCEFILES" == 1 ] || [ ! -h "/\$i" ] ; then
    if [ "\$FORCEFILES" == 1 ] ; then
      echo "Installing /\$i"
      # Can't use "cp -l" across mounts
      # Instead: use tar to move it
      if [ "\$DEBUG" != "" ] ; then
        echo "(cd install ; tar -cf - \"\$i\") | (cd / ; tar -xf -)"
      else
        (cd install ; tar -cf - "\$i") | (cd / ; tar -xf -)
      fi
    elif [ "\$INSTALLFILES" == 1 ] ; then
      echo "Link /\$i is missing.  Installing."
      if [ "\$DEBUG" != "" ] ; then
        echo "(cd install ; tar -cf - \"\$i\") | (cd / ; tar -xf -)"
      else
        (cd install ; tar -cf - "\$i") | (cd / ; tar -xf -)
      fi
    else
      echo "Link /\$i is missing."
      echo "If you are running this install script by hand,"
      echo "then use '\$0 -f' to install the files."
      echo "If you are running this install script through a package installer,"
      echo "then check to make sure the package not corrupted -- because it"
      echo "seems to be missing a file."
      \$DEBUG exit -1
    fi
  fi
done

# A few programs need to be public
\$DEBUG chmod 644 "\${AGENTDATADIR}/UnMagic.mime"
for i in departition ununpack ; do
  \$DEBUG chmod 755 "\${AGENTDIR}/\$i"
  \$DEBUG \$RM "/usr/local/bin/\$i" 2>/dev/null
  \$DEBUG ln -s "\${AGENTDIR}/\$i" "/usr/local/bin/\$i"
done

# One dependency from ununpack may need a symbolic link
if [ -z "\`which unrar\`" ] && [ ! -z "\`which unrar-free\`" ] ; then
  # Debian and other variations call it "unrar-free" instead of "unrar".
  # However, if you install it via the source, then it is "unrar".
  # Ununpack wants it called unrar.
  echo "Linking \`which unrar-free\` to /usr/local/bin/unrar"
  ln -s "\`which unrar-free\`" /usr/local/bin/unrar
  # Don't worry about missing files -- check.sh will catch it.
fi

# Ensure directories exist
if [ ! -d "\$VARDATADIR" ] ; then
  \$DEBUG \$MKDIR -p "\$VARDATADIR"
fi
if [ "\$DESTDIR" != "" ] ; then
  if [ ! -d "\$PROJECTHOME" ] ; then
    \$DEBUG \$MKDIR -p "\$PROJECTHOME"
  fi
fi

for i in "\$VARDATADIR" "\$PROJECHOME" ; do
  if [ -d "\$i" ] ; then
    \$DEBUG chown -R \${PROJECTUSER}:\${PROJECTGROUP} "\$i"
    \$DEBUG chmod 770 "\$i"
    \$DEBUG chmod g+s "\$i"
  fi
done

fi # if PLACEFILES
#####################################################


DBDIR="\$DATADIR/dbconnect"
DBCONF="\$DBDIR/\${PROJECT}"
REPOCONF="\$DATADIR/repository"
SCHEDULERCONF="\$AGENTDATADIR/scheduler.conf"
PROXYCONF="\$AGENTDATADIR/proxy.conf"
#####################################################
# Create default configuration files
if [ "\$PLACECONFIG" != "0" ] ; then
## Create default DB file (this will need to be changed by the user)
echo "# Checking configuration files"

if [ ! -f "/etc/default/fossology" ] ; then
  \$DEBUG \$CP install/etc/default/fossology /etc/default/fossology
fi

if [ ! -d "\$DBDIR" ] ; then
  \$MKDIR -p "\$DBDIR"
fi
if [ ! -f "\$DBCONF" ] ; then
  echo "Creating configration \$DBCONF"
  if [ "\$DEBUG" != "" ] ; then
    echo "dbname=\${PROJECT};" ">" "\$DBCONF"
    echo "host=localhost;" ">>" "\$DBCONF"  # assume DB is local
    echo "user=\$PROJECTUSER;" ">>" "\$DBCONF"
    echo "password=\$PROJECTUSER;" ">>" "\$DBCONF"  # user WILL want to change this
  else
    echo "dbname=\${PROJECT};" > "\$DBCONF"
    echo "host=localhost;" >> "\$DBCONF"  # assume DB is local
    echo "user=\$PROJECTUSER;" >> "\$DBCONF"
    echo "password=\$PROJECTUSER;" >> "\$DBCONF"  # user WILL want to change this
  fi
    echo "Be sure to configure \$DBCONF for your environment."
    CONFIGOK=0
    CONFIGDB=0
else
  echo "Configration \$DBCONF already exists. No change."
fi

## Create repository configuration files
# The repository is VERY LARGE.  It can easily be on the order of terabytes.
# Historically, /var is a small partition and a bad place for this.
# /home usually has space, but is not desirable for application data.
# /usr and /etc are intended for read-only mounting.  So this is not idea.
# /srv or /opt is a very good place for it.  But these do not exist on
# every OS.  /srv is standard as of FHS 2.3 (January 2004).
# The solution: put the repository in the project's home directory.
# The admin can always edit \$REPOCONF/RepPath.conf to move it.
# Just be sure to set the permissions and groups correctly!
# And I don't recommend using symlinks just because of the overhead from
# traversing links.

if [ ! -d "\$REPOCONF" ] ; then
  \$MKDIR -p "\$REPOCONF"
fi
if [ ! -f "\$REPOCONF/Depth.conf" ] ; then
  if [ "\$DEBUG" != "" ] ; then
    echo "3" ">" "\$REPOCONF/Depth.conf"
  else
    echo "3" > "\$REPOCONF/Depth.conf"
  fi
  CONFIGOK=0
  CONFIGREPO=0
else
  echo "Configration \$REPOCONF/Depth.conf already exists. No change."
fi

if [ ! -f "\$REPOCONF/Hosts.conf" ] ; then
  if [ "\$DEBUG" != "" ] ; then
    echo 'localhost * 00 ff' ">" "\$REPOCONF/Hosts.conf"
  else
    echo 'localhost * 00 ff' > "\$REPOCONF/Hosts.conf"
  fi
  CONFIGOK=0
  CONFIGREPO=0
else
  echo "Configration \$REPOCONF/Hosts.conf already exists. No change."
fi

# Define directory for the repo
REPODIR="\$PROJECTHOME/repository"
if [ ! -f "\$REPOCONF/RepPath.conf" ] ; then
  # Set the repository path to the directory "./repository/" in the
  # user's directory.
  \$DEBUG \$MKDIR -p "\$REPODIR"
  \$DEBUG chown -R "\$PROJECTUSER" "\$REPODIR"
  \$DEBUG chgrp -R "\$PROJECTGROUP" "\$REPODIR"
  \$DEBUG find "\$REPODIR" -type d -exec chmod 2770 '{}' \;
  \$DEBUG find "\$REPODIR" -type f -exec chmod 770 '{}' \;
  if [ "\$DEBUG" != "" ] ; then
    echo "\$REPODIR/" ">" "\$REPOCONF/RepPath.conf"
  else
    echo "\$REPODIR/" > "\$REPOCONF/RepPath.conf"
  fi
  echo "The file \$REPOCONF/RepPath.conf points to the repository."
  echo "  The repository can be a mount point, and should be a large disk space."
  echo "  If you plan to process ISOs, then consider a terabyte of disk or larger."
  CONFIGOK=0
  CONFIGREPO=0
else
  echo "Configration \$REPOCONF/RepPath.conf already exists. No change."
fi

## The system may have been reset or changed.
## Make sure RepPath.conf is valid.
## This is basic sanity checking.
## The corrective steps COULD be automated, but are intentionally not.
## The problem could be due to a bad partition mount, wrong config file, or
## just a missing directory.  However, automating the fix would do the
## wrong thing if it is a bad mount or config file.
## The solution is to leave it to the human.
REPODIR=\`cat \$REPOCONF/RepPath.conf\`
if [ ! -d "\${REPODIR}" ] ; then
  echo "ERROR: Repository '\$REPODIR' is missing."
  echo "  - Recheck your configuration file:"
  echo "      \$REPOCONF/RepPath.conf"
  echo "  - Create this missing directory:"
  echo "      mkdir -p '\$REPODIR'"
  echo "  - Make it owned by user '\$PROJECTUSER' with group '\$PROJECTGROUP'."
  echo "      chown '\$PROJECTUSER' '\$REPODIR'"
  echo "      chgrp '\$PROJECTGROUP' '\$REPODIR'"
  echo "  - Set the directory permissions:"
  echo "      chmod 2770 \$REPODIR"
  echo "  - Make sure all files have the correct permissions."
  echo "    This step is only needed if file permissions are not correct."
  echo "    This can be very slow for a large repository."
  echo "      chown -R '\$PROJECTUSER':'\$PROJECTGROUP' '\$REPODIR'"
  echo "      find '\$REPODIR' -type d -exec chmod 2770 '{}' \;"
  echo "      find '\$REPODIR' -type f -exec chmod 770 '{}' \;"
  echo "    These three commands can be done using '\$0 -R'."
  echo "  - Re-run this install script."
  exit 1
fi
if [ "\$CHECKREPO" != 0 ] ; then
  echo "Checking the repository... (this could be slow)"
  echo "  - Checking ownership"
  \$DEBUG chown "\$PROJECTUSER":"\$PROJECTGROUP" "\$REPODIR"
  if [ \$? != 0 ] ; then
    echo "ERROR: 'chown \$PROJECTUSER:\$PROJECTGROUP \$REPODIR' failed."
    exit 2
  fi
  echo "  - Checking directories"
  \$DEBUG find "\$REPODIR" -type d -exec chmod 2770 '{}' \;
  if [ \$? != 0 ] ; then
    echo "ERROR: 'find \$REPODIR -type d -exec chmod 2770 {} \\;' failed."
    exit 2
  fi
  echo "  - Checking files"
  \$DEBUG find "\$REPODIR" -type f -exec chmod 770 '{}' \;
  if [ \$? != 0 ] ; then
    echo "ERROR: 'find \$REPODIR -type f -exec chmod 770 {} \\;' failed."
    exit 2
  fi
fi

## Create the scheduler's initial configuration
if [ ! -f "\$SCHEDULERCONF" ] ; then
  if [ "\$DEBUG" != "" ] ; then
    echo \$AGENTDIR/mkconfig -L ">" "\$SCHEDULERCONF"
  else
    \$AGENTDIR/mkconfig -L > "\$SCHEDULERCONF"
  fi
  echo "You should check \$SCHEDULERCONF and"
  echo "configure it to match your environment."
  CONFIGOK=0
  CONFIGSCHEDULER=0
else
  echo "Configration \$SCHEDULERCONF already exists. No change."
fi

## Create a proxy configuration file
if [ ! -f "\$PROXYCONF" ] ; then
  # Good initial defaults: Copy user's environment.
  if [ "\$DEBUG" != "" ] ; then
    echo "export http_proxy=\"\$http_proxy\"" ">" "\$PROXYCONF"
    echo "export shttp_proxy=\"\$shttp_proxy\"" ">>" "\$PROXYCONF"
    echo "export ftp_proxy=\"\$ftp_proxy\"" ">>" "\$PROXYCONF"
    echo "export no_proxy=\"\$no_proxy\"" ">>" "\$PROXYCONF"
    echo "#example:" ">>" "\$PROXYCONF"
    echo "#export http_proxy=\"http://server:8080\"" ">>" "\$PROXYCONF"
    echo "#export ftp_proxy=\"http://server:3128\"" ">>" "\$PROXYCONF"
    echo "#export no_proxy=\"localhost,10.1.2.3\"" ">>" "\$PROXYCONF"
  else
    echo "export http_proxy=\"\$http_proxy\"" > "\$PROXYCONF"
    echo "export shttp_proxy=\"\$shttp_proxy\"" >> "\$PROXYCONF"
    echo "export ftp_proxy=\"\$ftp_proxy\"" >> "\$PROXYCONF"
    echo "export no_proxy=\"\$no_proxy\"" >> "\$PROXYCONF"
    echo "#example:" >> "\$PROXYCONF"
    echo "#export http_proxy=\"http://server:8080\"" >> "\$PROXYCONF"
    echo "#export ftp_proxy=\"http://server:3128\"" >> "\$PROXYCONF"
    echo "#export no_proxy=\"localhost,10.1.2.3\"" >> "\$PROXYCONF"
  fi
  echo "You should check \$PROXYCONF and"
  echo "configure it to match your environment."
  CONFIGOK=0
  CONFIGPROXY=0
else
  echo "Configration \$PROXYCONF already exists. No change."
fi

## Set permissions on all of the default configuration files
for i in "\$DBCONF" "\$REPOCONF" "\$SCHEDULERCONF" "\$PROXYCONF" ; do
  \$DEBUG chown -R \${PROJECTUSER}:\${PROJECTGROUP} "\$i"
  \$DEBUG chmod -R o-rwx "\$i"
  if [ -d "\$i" ] ; then
    \$DEBUG chmod 750 "\$i"
  else
    \$DEBUG chmod 640 "\$i"
  fi
done

####################################################
# At this point, all files are placed and config files are created.
# See if the user must configure anything before continuing...
if [ "\$CONFIGOK" != "1" ] ; then
  echo "All files and configuration files have been placed on the system."
  echo "However, some configuration files needed to be created."
  echo "Check the configuration in the following files and directories:"
  if [ "\$CONFIGDB" != "1" ] ; then
    echo "    \$DBCONF"
  fi
  if [ "\$CONFIGREPO" != "1" ] ; then
    echo "    \$REPOCONF"
  fi
  if [ "\$CONFIGSCHEDULER" != "1" ] ; then
    echo "    \$SCHEDULERCONF"
  fi
  if [ "\$CONFIGPROXY" != "1" ] ; then
    echo "    \$PROXYCONF"
  fi
  echo "Then re-run this script."
  exit
fi


## Create the License.bsam file
## This requires DB access and everything must be installed first.
echo "# Checking database connectivity"
\${AGENTTESTDDIR}/dbcheck
if [ \$? == 0 ] ; then
  \${AGENTTESTDDIR}/dbinit \${AGENTTESTDDIR}/dbinit.sql
  if [ \$? != 0 ] ; then
    echo "ERROR: Database failed during configuration.\n"
    exit 1
  fi
  if [ \$INSTALLLICENSES == 1 ] ; then
    echo "# Adding licenses to database"
    (
    # remove the old file
    \$DEBUG rm -f \${AGENTDATADIR}/License.bsam.new 2>/dev/null
    cd \${AGENTDATADIR}/licenses
    find . -type f | grep -v "\.meta" | sed -e 's@^./@@' | while read i ; do
      echo "Processing \$i"
      if [ -f "\$i.meta" ] ; then
        \$DEBUG \${AGENTDIR}/Filter_License -Q -O -M "\$i.meta" "\$i" >> \${AGENTDATADIR}/License.bsam.new
      else
        \$DEBUG \${AGENTDIR}/Filter_License -Q -O "\$i" >> \${AGENTDATADIR}/License.bsam.new
      fi
      if [ "\$DEBUG" == "" ] && [ "\$?" != "0" ] ; then
	echo "ERROR processing license."
	exit 1
      fi
    done
    # Make sure the file is valid
    \$DEBUG \${AGENTDIR}/bsam-engine -t "\${AGENTDATADIR}/License.bsam.new"
    if [ "\$?" != "0" ] ; then
	echo "ERROR processing licenses."
	echo "  Please remove \${AGENTDATADIR}/License.bsam.new"
	echo "  and \${AGENTDATADIR}/License.bsam,"
	echo "  then re-run the install"
	exit 1
    fi
    \$DEBUG rm -f \${AGENTDATADIR}/License.bsam 2>/dev/null
    \$DEBUG mv \${AGENTDATADIR}/License.bsam.new \${AGENTDATADIR}/License.bsam 2>/dev/null
    )
    chown \$PROJECTUSER:\$PROJECTGROUP \${AGENTDATADIR}/License.bsam
    chmod 640 \${AGENTDATADIR}/License.bsam
  else
    echo "# Skipping licenses"
  fi
else
  echo "ERROR: Database not configured."
  echo "  Check \$DBCONF then re-run the install."
  exit 1
fi
fi # if PLACECONFIG

#####################################################
# See if Web server (www-data) exists.  If so add him to the group.
grep -q "^www-data:" /etc/group
if [ "\$?" == 0 ] ; then
  echo "# Adding user www-data to group \$PROJECTGROUP"
  WEBGROUPS=\`groups www-data 2>/dev/null | sed -e 's/.*: //' -e 's/ /,/g'\`
  if [ "\$WEBGROUPS" != "" ] ; then
    WEBGROUPS="\$WEBGROUPS,\$PROJECTGROUP"
  else
    WEBGROUPS="\$PROJECTGROUP"
  fi
  \$DEBUG usermod -G \$WEBGROUPS www-data
fi

#####################################################
# Initialize all agents
echo "# Initializing agents"
for i in bsam-engine engine-shell filter_clean Filter_License mimetype pkgmetagetta scheduler specagent ununpack wget_agent ; do
  echo "  Initializing \$i"
  \${AGENTDIR}/\$i -i
  if [ "\$?" != 0 ] ; then
    echo "ERROR: Failed to initialize database for agent \$i"
    exit 1
  fi
done

#####################################################

echo "# Automated installation completed"

# Additional instructions
echo "Be sure to:"
echo "  + Install PHP5"
echo "  + Configure your web server so \$WEBDIR"
echo "    is used for the user interface."
echo "  + Make sure your web server user is in group \$PROJECTUSER in /etc/group"
echo "    then restart your web server so it runs with the new group access."
echo "  + Double check the configuration in the following files and directories:"
echo "    \$DBCONF"
echo "    \$REPOCONF"
echo "    \$SCHEDULERCONF"
echo "    \$PROXYCONF"

EOF

