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

# Insert licenses defined in the 'resources/licenses' files
# Expects a list of CSV files as arguments, one license entry per file
#
# To create CSV license file, add it via the web interface,
# and then export it to CSV from psql:
#   \copy (select * from license_ref where rf_shortname = 'My-Proprietary-License') \
#    to /tmp/out.csv with csv
#

. $(dirname $0)/fo-config-common.sh

f_log -s "Add home brewed licenses to database"
[ -n "$1" ] || f_fatal "Error: expecting license files as arguments"

for csv in "$@"
do
    f_log "Handle License file: $csv"
    # Note trick to read CSV file,
    # because newlines within CSV fields are encoded with CR characters.
    lic_name=$(grep -z . $csv | cut -z -d ',' -f2)
    lic_md5=$(grep -z . $csv | cut -z -d ',' -f18)
    [ -n "$lic_md5" ] || f_fatal "Problem with licencse file: '$csv'"

    if f_query_row_exists "select count(rf_pk) from license_ref where rf_md5 = '$lic_md5';"
    then
        echo "License already in the DB: $lic_name"
    else
        echo "Adding license: $lic_name"
        # Find new Primary Key
        max_pk=$(f_query "select max(rf_pk) from license_ref;")
        new_pk=$((max_pk+1))
        # Update license file with new Primary Key
        sed -i "s/^\([^,]*,$lic_name,\)/$new_pk,$lic_name,/" $csv || \
            f_fatal "Failed updating $csv"
        docker cp $csv $docker_container:/tmp/
        f_query "\\copy license_ref from '/tmp/$(basename $csv)' csv"
    fi
done
