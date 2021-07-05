#!/bin/sh
#
# This program is free software; you can redistribute it and/or
# modify it under the terms of the GNU General Public License
# version 2 as published by the Free Software Foundation.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License along
# with this program; if not, write to the Free Software Foundation, Inc.,
# 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
#
# SPDX-FileCopyrightText: 2021 Orange
# SPDX-License-Identifier: GPL-2.0-only
#
# Author: Nicolas Toussaint <nicolas1.toussaint@orange.com>

# This file provides configuration for Database and Scheduler.
# It relies on environment variables so as to be used directly
# in container based deployments

. $(dirname $0)/fo-config-common.sh

#### #### #### #### #### #### #### #### #### #### #### #### #### ####
# Remove default (and admin) account.
user_fossy_from_where_clause="from users where user_pk = 3 and user_name = 'fossy' and user_desc = 'Default Administrator';"
if f_query_row_exists "select count(user_pk) $user_fossy_from_where_clause"
then
    f_log -s "Remove default user 'fossy'"
    f_query "delete $user_fossy_from_where_clause" || f_fatal "Error removing default user"
else
    f_log -s "Default user 'fossy' not found"
fi
