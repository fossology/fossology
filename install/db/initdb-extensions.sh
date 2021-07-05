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

set -e

export PGUSER=postgres
export PGPASSWORD=$POSTGRESQL_POSTGRES_PASSWORD
echo "Initialise database '$POSTGRESQL_DATABASE' with user: $PGUSER"
echo "Add 'uuid-ossp' extension"
psql --dbname="$POSTGRESQL_DATABASE" <<-'EOSQL'
		CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
EOSQL
echo "Initialise database $POSTGRESQL_DATABASE"
psql --dbname="$POSTGRESQL_DATABASE" < $(dirname $0)/fossologyinit.sql

