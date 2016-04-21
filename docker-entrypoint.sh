#!/bin/bash
# FOSSology docker-entrypoint script
# Copyright Siemens AG 2016, fabio.huser@siemens.com
#
# Copying and distribution of this file, with or without modification,
# are permitted in any medium without royalty provided the copyright
# notice and this notice are preserved.  This file is offered as-is,
# without any warranty.
#
# Description: startup helper script for the FOSSology Docker container

db_host="localhost"
db_name="fossology"
db_user="fossy"
db_password="fossy"

if [ "$FOSSOLOGY_DB_HOST" ]; then
	db_host="$FOSSOLOGY_DB_HOST"
fi
if [ "$FOSSOLOGY_DB_NAME" ]; then
	db_name="$FOSSOLOGY_DB_NAME"
fi
if [ "$FOSSOLOGY_DB_USER" ]; then
	db_user="$FOSSOLOGY_DB_USER"
fi
if [ "$FOSSOLOGY_DB_PASSWORD" ]; then
	db_password="$FOSSOLOGY_DB_PASSWORD"
fi

# Write configuration
cat <<EOM > /usr/local/etc/fossology/Db.conf
dbname=$db_name;
host=$db_host;
user=$db_user;
password=$db_password;
EOM

# Startup DB if needed or wait for external DB
if [ "$db_host" = 'localhost' ]; then
  echo '*****************************************************'
  echo 'WARNING: No database host was set and therefore the'
  echo 'internal database without persistency will be used.'
  echo 'THIS IS NOT RECOMENDED FOR PRODUCTIVE USE!'
  echo '*****************************************************'
  /etc/init.d/postgresql start
else
  testForPostgres(){
    PGPASSWORD=$db_password psql -h "$db_host" "$db_name" "$db_user" -c '\l' >/dev/null
    return $?
  }
  until testForPostgres; do
    >&2 echo "Postgres is unavailable - sleeping"
    sleep 1
  done
fi

# Setup environment
/usr/local/lib/fossology/fo-postinstall

# Start Fossology
echo
echo 'Fossology initialisation complete; Starting up...'
echo
/etc/init.d/fossology start
/usr/sbin/apache2ctl -D FOREGROUND
