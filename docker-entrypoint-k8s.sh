#!/bin/bash
# FOSSology docker-entrypoint script
# Copyright Siemens AG 2016, fabio.huser@siemens.com
# Copyright TNG Technology Consulting GmbH 2016, maximilian.huber@tngtech.com
# Copyright Orange, nicolas1.toussaint@orange.com
#
# Copying and distribution of this file, with or without modification,
# are permitted in any medium without royalty provided the copyright
# notice and this notice are preserved.  This file is offered as-is,
# without any warranty.
#
# Description: startup helper script for the FOSSology Docker container,
#              simlpified for Kubernetes and Openshit

# Usage: Expects one of two argument: web or scheduler
#

set -o errexit -o nounset -o pipefail

db_host="${FOSSOLOGY_DB_HOST:-localhost}"
db_name="${FOSSOLOGY_DB_NAME:-fossology}"
db_user="${FOSSOLOGY_DB_USER:-fossy}"
db_password="${FOSSOLOGY_DB_PASSWORD:-fossy}"

cron_start="${FOSSOLOGY_CRON_START:-false}"

echo "$(basename $0): $@"

# Write configuration
cat <<EOM > /usr/local/etc/fossology/Db.conf
dbname=$db_name;
host=$db_host;
user=$db_user;
password=$db_password;
EOM

sed -i 's/address = .*/address = '"${FOSSOLOGY_SCHEDULER_HOST:-localhost}"'/' \
    /usr/local/etc/fossology/fossology.conf

# Wait for Database to be ready
test_for_postgres() {
  PGPASSWORD=$db_password psql -h "$db_host" "$db_name" "$db_user" -c '\l' >/dev/null
  return $?
}
until test_for_postgres; do
  >&2 echo "Postgres is unavailable - sleeping"
  sleep 1
done
echo "Postgres is available - carry on"

# Configure and start container
case "$1" in
maintenance)
  echo 'Run Maintenance jobs'
  /usr/local/share/fossology/maintagent/agent/maintagent -A
  ;;
backup)
  echo 'Run backup jobs'
  /usr/local/lib/fossology/fo-backup-S3.sh --backup-all
  ;;
inspect)
  echo 'Stay idle to allow rsh connections'
  while true
  do
    echo "$(date) - Looping"
    sleep 30
  done
  ;;
scheduler)
  echo 'Run post-install script'
  /usr/local/lib/fossology/fo-postinstall --container-mode --scheduler-only --common --database # --licenseref
  echo 'Run DB config script'
  /usr/local/lib/fossology/fo-config-db
  echo 'Run Scheduler config script'
  /usr/local/lib/fossology/fo-config-scheduler
  echo 'Starting Scheduler...'
  exec /usr/local/share/fossology/scheduler/agent/fo_scheduler \
    --log /dev/stdout \
    --verbose=3 \
    --reset
  ;;
web)
  echo 'Run Web config script'
  /usr/local/lib/fossology/fo-config-web
  echo "Run Bitnami Apache Entrypoint"
  exec /opt/bitnami/scripts/apache/entrypoint.sh /opt/bitnami/scripts/apache/run.sh
  ;;
*)
  echo "Invalid or missing argument: [$1]"
  exit 1
  ;;
esac

