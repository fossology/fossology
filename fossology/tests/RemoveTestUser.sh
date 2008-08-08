#!/bin/bash
#
# simple script to remove the test user needed for testing certain
# test cases. The user should not be logged in (Don't want to remove while
# testing.  The users home dir is removed.
#
# Must be run as root or sudo
#

# userdel
# Name, home dir path, uid, initial group, other groups, create home, shell,
# password (none) user-account

userdel -r fosstester