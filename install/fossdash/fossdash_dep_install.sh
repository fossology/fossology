#!/bin/bash
#
# Copyright Darshan Kansagara <kansagara.darshan97@gmail.com>
# SPDX-License-Identifier: GPL-2.0
# Author: Darshan Kansagara <kansagara.darshan97@gmail.com>
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

apt-get update
apt-get install python3
apt-get -y install python3-psycopg2
apt-get -y install python3-requests
apt-get -y install python3-yaml