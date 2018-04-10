#! /bin/sh
# Copyright (C) 2018, Siemens AG
#
# This program is free software; you can redistribute it and/or
# modify it under the terms of the GNU General Public License
# version 2 as published by the Free Software Foundation.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License along
# with this program; if not, write to the Free Software Foundation, Inc.,
# 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.

# Create a test config directory

confDir="./testconf"
confFile="/fossology.conf"
agentDir="../.."

mkdir -p $confDir
touch $confDir$confFile
echo "[FOSSOLOGY]\ndepth = 0\npath = $(confDir)\n" >> $confDir$confFile
touch $confDir/Db.conf
echo "dbname=fossology;\nhost=localhost;\nuser=fossy;\npassword=fossy;\n" >> $confDir/Db.conf
install -D ../../../../VERSION $confDir/VERSION
install -D $agentDir/VERSION $confDir/mods-enabled/ununpack/VERSION
ln -fs $agentDir/agent $confDir/mods-enabled/ununpack

../../agent/ununpack -c $confDir -Cv -d . -R -L ./$1.xml $1

# Remove the test config directory

rm -rf $confDir
