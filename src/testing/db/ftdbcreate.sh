#!/bin/bash
# FOSSology dbcreate script
# Copyright (C) 2008 Hewlett-Packard Development Company, L.P.
#
# This script checks to see if the the fossology db exists and if not
# then creates it.
#
# @verion "$Id$"

echo "*** Setting up the FOSSology database ***"

# At some point this is where we could dynamically set the db password


if [ -n "$1" ]
then
  dbname=$1
else
  echo "Error! No DataBase Name supplied"
  exit 1
fi

# first check that postgres is running
su postgres -c 'echo \\q|psql'
if [ $? != 0 ]; then
   echo "ERROR: postgresql isn't running"
   exit 2
fi

# then check to see if the db already exists
su postgres -c 'psql -l' |grep -q $dbname
if [ $? = 0 ]; then
   echo "NOTE: $dbname database already exists, not creating"
   echo "*** Checking for plpgsql support ***"
   su postgres -c "createlang -l $dbname" |grep -q plpgsql
   if [ $? = 0 ]; then
      echo "NOTE: plpgsql already exists in $dbname database, good"
   else
      echo "NOTE: plpgsql doesn't exist, adding"
      su postgres -c "createlang plpgsql $dbname"
      if [ $? != 0 ]; then
         echo "ERROR: failed to add plpgsql to $dbname database"
      fi
   fi
else
   echo "sysconfdir from env is -> $SYSCONFDIR"
   echo "*** Initializing database ***"
   echo "testroot is->$TESTROOT"
   if [ -z $TESTROOT ]
   then 
     TESTROOT=`pwd`;
     #echo "TESTROOT IS:$TESTROOT";
   #else
   #  echo "TestRoot from env is:{$TESTROOT}";
   fi
# change the name of the db in the sql file if a name was passed in
# or use the default name.
   didSed=
   if [ "$dbname" != "fosstest" ]
   then
     sed '1,$s/fosstest/'"$dbname"'/' < fosstestinit.sql > $$db.sql
     fossSql=$$db.sql
     didSed=1
   else
       fossSql='fosstestinit.sql'
   fi
  
   echo "DB: before su to postgres"
   su postgres -c "psql < $TESTROOT/$fossSql"
   if [ $? != 0 ] ; then
      echo "ERROR: Database failed during configuration."
      exit 3
   fi
   #clean up
   if [ -n "$didSed" ]
   then
     rm $$db.sql
   fi
fi


