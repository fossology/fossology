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

# Setup test db for ununpack agent

confDir="`pwd`/testconf"
confFile="/fossology.conf"
agentDir="../.."

mkdir -p $confDir
touch $confDir$confFile
echo "[FOSSOLOGY]\ndepth = 0\npath = $confDir\n" >> $confDir$confFile
cp `pwd`/../../../../install/defconf/Db.conf $confDir/Db.conf
ln -fs `pwd`/../../../../VERSION $confDir/VERSION
mkdir -p $confDir/mods-enabled/ununpack
ln -fs $agentDir/VERSION $confDir/mods-enabled/ununpack/VERSION
ln -fs $agentDir/agent $confDir/mods-enabled/ununpack
finalConf=$(/usr/bin/php ../../../testing/db/createTestDB.php -c $confDir -e)

dbname=$(awk -F "=" '/dbname/ {print $2}' $finalConf/Db.conf | tr -d '; ')
host=$(awk -F "=" '/host/ {print $2}' $finalConf/Db.conf | tr -d '; ')
user=$(awk -F "=" '/user/ {print $2}' $finalConf/Db.conf | tr -d '; ')
password=$(awk -F "=" '/password/ {print $2}' $finalConf/Db.conf | tr -d '; ')
version=$(awk -F "=" '/VERSION/ {print $2}' $finalConf/mods-enabled/ununpack/VERSION)
hashvalue=$(awk -F "=" '/COMMIT_HASH/ {print $2}' $finalConf/mods-enabled/ununpack/VERSION)

set PGPASSWORD=$password
psql -c "CREATE TABLE agent ( \
  agent_pk serial, \
  agent_name character varying(32) NOT NULL, \
  agent_rev character varying(32), \
  agent_desc character varying(255) DEFAULT NULL, \
  agent_enabled boolean DEFAULT true, \
  agent_parms text, \
  agent_ts timestamp with time zone DEFAULT now() \
);" $dbname $user
psql -c "INSERT INTO agent(agent_name, agent_rev) VALUES('ununpack','$version.$hashvalue');" $dbname $user

echo "user=$user;\nconfDir=$confDir;\nfinalConf=$finalConf;\ndbname=$dbname" > testVariables.var
