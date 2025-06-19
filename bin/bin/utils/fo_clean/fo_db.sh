#!/bin/bash

if ! su postgres -c 'psql -l' &> /dev/null; then
  echo "ERROR: postgresql isn't running, not deleting database"
  exit 1
fi

if su postgres -c 'psql -l' | grep -q fossology; then
  echo "*** Deleting database ***"
  pkill -f -u postgres fossy || true
  su postgres -c 'drop database fossology'
  [ $? != 0 ] && echo "ERROR: failed to delete database" && exit 1
else
  echo "NOTE: fossology database does not exist, not deleting"
fi
