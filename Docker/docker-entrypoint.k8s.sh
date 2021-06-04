#!/bin/bash
# FOSSology docker-entrypoint script for kubernetes
# SPDX-FileCopyrightText: 2021 Omar AbdelSamea <omarmohamed168@gmail.com>
# SPDX-License-Identifier: GPL-2.0
#
# Description: startup helper script for the FOSSology Docker container in kuberentes

set -o errexit -o nounset -o pipefail

sed -i 's/address = .*/address = '"${FOSSOLOGY_SCHEDULER_HOST:-scheduler}"'/' \
    /etc/fossology/fossology.conf

# Startup DB if needed or wait for external DB
if [[ "$1" == "scheduler" ]]; then
  echo '*****************************************************'
  echo 'WARNING: No database host was set and therefore the'
  echo 'internal database without persistency will be used.'
  echo 'THIS IS NOT RECOMENDED FOR PRODUCTIVE USE!'
  echo '*****************************************************'
  bash ./fo_conf.sh db
  sleep 10
  /etc/init.d/postgresql start
else
  test_for_postgres() {
    PGPASSWORD=$db_password psql -h "$db_host" "$db_name" "$db_user" -c '\l' >/dev/null
    return $?
  }
  until test_for_postgres; do
    >&2 echo "Postgres is unavailable - sleeping"
    sleep 1
  done
fi  

# # Setup environment and add fossology.conf to etcd
if [[ $# -eq 0 || ($# -eq 1 && "$1" == "scheduler") ]]; then
    /usr/lib/fossology/fo-postinstall --common --database --licenseref
    bash ./fo_conf.sh scheduler
fi

# Start Fossology
echo
echo 'Fossology initialisation complete; Starting up...'
echo
if [[ $# -eq 0 ]]; then
  /etc/init.d/cron start
  /etc/fossology/mods-enabled/scheduler/agent/fo_scheduler \
    --log /dev/stdout \
    --verbose=4095 \
    --reset &
  /usr/sbin/apache2ctl -D FOREGROUND
elif [[ $# -eq 1 && "$1" == "scheduler" ]]; then
  exec /etc/fossology/mods-enabled/scheduler/agent/fo_scheduler \
    --log /dev/stdout \
    --verbose=4095 \
    --reset
elif [[ $# -eq 1 && "$1" == "web" ]]; then
  service cron start
  exec /usr/sbin/apache2ctl -e info -D FOREGROUND
elif [[ $# -ge 1 && "$1" == "agent" ]]; then
  bash ./fo_conf.sh agent $2
  chmod +x /usr/share/fossology/scheduler/agent/fo_cli
  /usr/share/fossology/scheduler/agent/fo_cli --host=${FOSSOLOGY_SCHEDULER_HOST:-scheduler} \
  --port=24693 --reload || echo "Scheduler is initlaizing or not running"
  exec tail -f /dev/null
else
  exec "$@"
fi
