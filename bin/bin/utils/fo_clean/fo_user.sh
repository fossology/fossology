#!/bin/bash

echo "*** Deleting user and group ***"
if grep -q "^fossy:" /etc/passwd; then
  userdel -r fossy && echo "User fossy deleted." || echo "ERROR: failed to delete user"
else
  echo "User fossy does not exist."
fi

if grep -q "^fossy:" /etc/group; then
  groupdel fossy && echo "Group fossy deleted." || echo "ERROR: failed to delete group"
else
  echo "Group fossy does not exist."
fi
