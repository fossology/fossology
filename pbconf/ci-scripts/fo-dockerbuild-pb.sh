#!/bin/bash
# SPDX-FileCopyrightText: © Fossology contributors

# SPDX-License-Identifier: GPL-2.0-only

# part of the scripting for building packages with package builder
# second script that runs docker containers for target platforms
set -x

# first adding the pbconf file
# note that backslashes are because of the cat command
cat<<PBRC > $HOME/.pbrc

# auto generated from the package scripts of fossology for proejct builder

pbdefdir default = \$ENV{'HOME'}/cache-project-builder
pbstoponerr default = true

pburl fossology = git+https://github.com/fossology/fossology.git
pbdefdir fossology = \$ENV{'HOME'}/
pbconfurl fossology = git+https://github.com/fossology/fossology.git
pbconfdir fossology = \$ENV{'HOME'}/fossology/pbconf

pbconfurl pb = svn://svn.project-builder.org/pb/pbconf
pbconfdir pb = \$ENV{'HOME'}/cache-project-builder/pb/pbconf

# Docker VE build setup
velogin default = pb
velist fossology = ubuntu-14.04-x86_64,centos-7-x86_64,fedora-25-x86_64
#velist fossology = fedora-25-x86_64

# Local delivery
sshhost default = localhost
sshlogin default = $USER
sshdir default = /tmp/test

PBRC

# the grand docker loop ...
PRJ=fossology
DISTROS="$DISTROS `pbgetparam -p $PRJ velist | sed 's|,| |g'`"
FORCE=$1
for d in $DISTROS; do
	echo "[DOCKER] Building for $d..."
	echo "==========================="
	dname=`echo $d | cut -d- -f1`
	dver=`echo $d | cut -d- -f2`
	step=`docker images | grep " $d "`
	if [ _"$step" = _"" ] || [ "$FORCE" = "-f" ]; then
		pb -p pb -r 0.14.2 -T docker -t $d -m $d newve -i ${dname}:$dver
	fi
	step=`docker images | grep " ${d}-pb "`
	if [ _"$step" = _"" ] || [ "$FORCE" = "-f" ]; then
		pb -p pb -r 0.14.2 -T docker -t $d -m $d sbx2setupve
	fi
	step=`docker images | grep " ${d}-pb-$PRJ "`
	if [ _"$step" = _"" ] || [ "$FORCE" = "-f" ]; then
		pb -p $PRJ -T docker -t $d -m $d sbx2prepve all
	fi
	pb -p $PRJ -T docker -t $d -m $d sbx2ve all
done
