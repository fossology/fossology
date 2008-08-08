#!/bin/bash
#
# simple script to create the test user needed for testing certain
# test cases.
#
# Must be run as root or sudo
#

# add group
groupadd -g 666777 fosstester

# useradd:
# Name, home dir path, uid, initial group, other groups, create home, shell,
# password (none) user-account

useradd -c 'Fossolgy Test User' -d /home/fosstester -u 666777 -g fosstester \
-G fossy,sudo,users -m -s /bin/bash -p 'Brksumth1n' fosstester
