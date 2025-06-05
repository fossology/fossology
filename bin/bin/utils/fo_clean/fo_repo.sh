#!/bin/bash

if [ -d /srv/fossology/repository ]; then
  echo "*** Deleting repository ***"
  rm -rf /srv/fossology
  [ $? != 0 ] && echo "ERROR: failed to delete repository" && exit 1
else
  echo "NOTE: repository does not exist, not deleting"
fi
