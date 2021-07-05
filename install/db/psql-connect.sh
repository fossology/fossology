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

# Facilitates local connection to the PostgreSQL database,
# reusing environment variables provided in containers

[ -n "$POSTGRESQL_USERNAME" ] && export PGUSER=$POSTGRESQL_USERNAME
[ -n "$POSTGRESQL_PASSWORD" ] && export PGPASSWORD=$POSTGRESQL_PASSWORD

[ -n "$FOSSOLOGY_DB_USER" ] && export PGUSER=$FOSSOLOGY_DB_USER
[ -n "$FOSSOLOGY_DB_PASSWORD" ] && export PGPASSWORD=$FOSSOLOGY_DB_PASSWORD

[ -n "$FOSSOLOGY_DB_NAME" ] && export PGDATABASE=$FOSSOLOGY_DB_NAME
[ -n "$FOSSOLOGY_DB_HOST" ] && DB_HOST_OPTION="-h $FOSSOLOGY_DB_HOST"

cat <<EOS
USER: $PGUSER
DATABASE: $PGDATABASE
HOST: $FOSSOLOGY_DB_HOST
EOS

psql $DB_HOST_OPTION "$@"
