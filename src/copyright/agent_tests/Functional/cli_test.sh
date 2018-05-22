#! /bin/sh
#
# Author: Daniele Fognini, Andreas Wuerl
# Copyright (C) 2013-2015, 2018 Siemens AG
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

confDir="`pwd`/testconf"
confFile="/fossology.conf"
agentDir="../.."
finalConf=""
dbname=""
host=""
user=""
password=""

_dbSetup()
{
  dbname=$(awk -F "=" '/dbname/ {print $2}' $finalConf/Db.conf | tr -d '; ')
  host=$(awk -F "=" '/host/ {print $2}' $finalConf/Db.conf | tr -d '; ')
  user=$(awk -F "=" '/user/ {print $2}' $finalConf/Db.conf | tr -d '; ')
  password=$(awk -F "=" '/password/ {print $2}' $finalConf/Db.conf | tr -d '; ')
  version=$(awk -F "=" '/VERSION/ {print $2}' $finalConf/mods-enabled/copyright/VERSION)
  hashvalue=$(awk -F "=" '/COMMIT_HASH/ {print $2}' $finalConf/mods-enabled/copyright/VERSION)

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
  psql -c "INSERT INTO agent(agent_name, agent_rev) VALUES('copyright','$version.$hashvalue');" $dbname $user
}

oneTimeSetUp()
{
  mkdir -p $confDir
  touch $confDir$confFile
  echo "[FOSSOLOGY]\ndepth = 0\npath = $confDir\n" >> $confDir$confFile
  cp `pwd`/../../../../install/defconf/Db.conf $confDir/Db.conf
  mkdir -p $confDir/mods-enabled/copyright
  mkdir -p $confDir/mods-enabled/ecc
  mkdir -p $confDir/mods-enabled/keyword
  cp ../../../../VERSION $confDir/VERSION
  cp $agentDir/agent/copyright $confDir/mods-enabled/copyright/copyright
  cp $agentDir/agent/copyright.conf $confDir/mods-enabled/copyright/copyright.conf
  cp $agentDir/VERSION-copyright $confDir/mods-enabled/copyright/VERSION
  cp $agentDir/agent/ecc $confDir/mods-enabled/ecc/ecc
  cp $agentDir/agent/ecc.conf $confDir/mods-enabled/ecc/ecc.conf
  cp $agentDir/VERSION-ecc $confDir/mods-enabled/ecc/VERSION
  cp $agentDir/agent/keyword $confDir/mods-enabled/keyword/
  cp $agentDir/agent/keyword.conf $confDir/mods-enabled/keyword/keyword.conf
  cp $agentDir/VERSION-keyword $confDir/mods-enabled/keyword/VERSION
  finalConf=$(/usr/bin/php ../../../testing/db/createTestDB.php -c $confDir -e)
  _dbSetup
}

oneTimeTearDown()
{
  set PGPASSWORD=$password
  psql -c "DROP DATABASE $dbname;" postgres $user
  rm -rf $confDir $finalConf
}

_runcopyright()
{
  ./copyright -c $finalConf "$@" | sed -e '1d'
}

_runcopyrightPositives()
{
  ./copyright -c $finalConf --regex '1@@<s>([^\0]*?)</s>' -T 0 "$1" | sed -e '1d'
}

_checkFound()
{
  while IFS="[:]'" read initial start length type unused content unused; do
    found="not found"
    while IFS="[:]'" read initial2 start2 length2 type2 unused2 content2 unused2; do
      if [ "x$content2" = "x$content" ]; then
        found="$content"
        break
      fi
    done <<EO2
$2
EO2

    assertEquals "$content" "$found"
    echo "$content"
  done <<EO1
$1
EO1

}

testAll()
{
  for file_raw in ../testdata/testdata3_raw; do
    file=${file_raw%_raw}
    expectedPositives="$( _runcopyrightPositives "$file_raw" )"
    found="$( _runcopyright -T 1 "$file" )"
    _checkFound "$expectedPositives" "$found"
  done
}

testUserRegexesDefault()
{
  tmpfile=$(mktemp)
  assertTrue $?
  echo "test one" > "$tmpfile"

  out=$( _runcopyright -T 0 --regex 'test[[:space:]]*o' "$tmpfile" )

  expected=$(printf "\t[0:6:cli] 'test o'")

  assertEquals "$expected" "$out"

  rm "$tmpfile"
}

testUserRegexesName()
{
  tmpfile=$(mktemp)
  assertTrue $?
  echo "test one" > "$tmpfile"

  out=$( _runcopyright -T 0 --regex 'test@@test[[:space:]]*o' "$tmpfile" )

  expected=$(printf "\t[0:6:test] 'test o'")

  assertEquals "$expected" "$out"

  rm "$tmpfile"
}

testUserRegexesNameAndGroup()
{
  tmpfile=$(mktemp)
  assertTrue $?
  echo "test one" > "$tmpfile"

  out=$( _runcopyright -T 0 --regex 'test@@1@@t(es)t[[:space:]]*' "$tmpfile" )

  expected=$(printf "\t[1:3:test] 'es'")

  assertEquals "$expected" "$out"

  rm "$tmpfile"
}

testUserRegexesNameAndGroup2()
{
  tmpfile=$(mktemp)
  assertTrue $?
  echo "test one" > "$tmpfile"

  out=$( _runcopyright -T 0 --regex 'test@@0@@t(es)t[[:space:]]*o' "$tmpfile" )

  expected=$(printf "\t[0:6:test] 'test o'")

  assertEquals "$expected" "$out"

  rm "$tmpfile"
}
