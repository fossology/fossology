#!/bin/bash
# SPDX-FileCopyrightText: © 2008 Hewlett-Packard Development Company, L.P.

# SPDX-License-Identifier: GPL-2.0-only
#
# simple script to remove the test user needed for testing certain
# test cases. The user should not be logged in (Don't want to remove while
# testing.  The users home dir is removed.
#
# Must be run as root or sudo
#

# userdel, remove the users directory

userdel -r fosstester