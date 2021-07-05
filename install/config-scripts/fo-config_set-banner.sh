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
# Set banner text
if [ -n "$1" ]
then
    f_log "Update banner message: '$1'"
    f_update_db_sysconfig "BannerMsg" "$1"
    echo
fi
