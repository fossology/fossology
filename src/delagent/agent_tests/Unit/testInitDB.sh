#!/bin/sh
#  SPDX-FileCopyrightText: © 2011 Hewlett-Packard Development Company, L.P.

#  SPDX-License-Identifier: GPL-2.0-only

DBName=$1

FOSSYGID=$(id -Gn)
FOSSYUID=$(echo $FOSSYGID |grep -c 'fossy')
if [ $FOSSYUID -ne 1 ];then
  echo "Must be fossy group to run this script."
  exit 1
fi

#echo $UID
#echo $DBName

touch $HOME/connectdb.exp
{
  echo '#!/usr/bin/expect'
  echo 'set timeout 30'
  echo 'spawn psql -Ufossy -d '$DBName' < ./testdata/testdb_all.sql >/dev/null'
  echo 'expect "Password:"'
  echo 'send "fossy\r"'
  echo 'interact'
} >> $HOME/connectdb.exp


if expect $HOME/connectdb.exp;
then
  echo "Create Test database data Error!"
  exit 1
fi
#rm -f $HOME/connectdb.exp

echo "Create Test database data success!"
