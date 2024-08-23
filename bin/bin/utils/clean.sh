#!/bin/bash

## Options parsing and setup
OPTS=$(getopt -o h --long delete-conffiles,delete-database,delete-repository,delete-user,delete-everything,help -n 'fo-cleanold' -- "$@")

if [ $? != 0 ]; then
   echo "ERROR: Bad option specified."
   OPTS="--help"
fi

eval set -- "$OPTS"

while true; do
  case "$1" in
    --delete-conffiles) RMCONF=1; shift;;
    --delete-database) RMDB=1; shift;;
    --delete-repository) RMREPO=1; shift;;
    --delete-user) RMUSER=1; shift;;
    --delete-everything) EVERYTHING=1; shift;;
    -h|--help)
      echo "Usage: fo_clean.sh [options]"
      echo "  --delete-conffiles  : delete configuration files"
      echo "  --delete-database   : delete database"
      echo "  --delete-repository : delete repository"
      echo "  --delete-user       : delete user and group"
      echo "  --delete-everything : delete everything"
      exit;;
    --) shift; break;;
    *) echo "Error: option $1 not recognised"; exit 1;;
  esac
done

if [ $EVERYTHING ]; then
  echo "*** Deleting everything ***"
  ./utils/fo_clean/fo_clear.sh
else
  # Run deletions in parallel where possible to save time
  [ $RMCONF ] && ./utils/fo_clean/fo_config.sh &
  [ $RMDB ] && ./utils/fo_clean/fo_db.sh &
  [ $RMREPO ] && ./utils/fo_clean/fo_repo.sh &
  [ $RMUSER ] && ./utils/fo_clean/fo_user.sh &

  wait
fi
