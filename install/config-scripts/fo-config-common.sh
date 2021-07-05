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
# Author: Nicolas Toussaint nicolas1.toussaint@orange.com
#

# This file contains common functions used by configuration scripts

log_date=false # override in calling scripts

f_log() {
    local _date=""
    for a in "$@"
    do
        case "$1" in
        -s)
            echo "*** *** *** *** *** *** *** *** *** *** *** *** *** *** "
            shift ;;
        -l)
            [ $log_date = "true" ] && _date="$(date '+%Y-%m-%d %H:%M:%S') *** "
            shift ;;
        *) break;;
        esac
    done

    echo "*** ${_date}$*"
    true # make sure the function returns Zero
}

f_fatal() {
    echo "ERROR $0: $*"
    exit 1
}

f_query() {
    # Temporary file required to workaround missing PIPESTATUS feature in DASH shell
    local tmpfile=$(mktemp) || return 1
    PGPASSWORD="$FOSSOLOGY_DB_PASSWORD" psql -h "$FOSSOLOGY_DB_HOST" -U "$FOSSOLOGY_DB_USER" -d "$FOSSOLOGY_DB_NAME" \
        --tuples-only --quiet -c "$1" >$tmpfile || return 1
    sed 's/^ *//' $tmpfile
    rm $tmpfile
}

# Update conf_value in table sysconfig
# Arg 1: Variable Name
# Arg 2: Value
f_update_db_sysconfig() {
    echo " - Update $1"
    f_query "update sysconfig set conf_value = '$2' where variablename = '$1';" || \
        f_fatal "Error configuring DB entry '$Update $1'"
}

# Use with a query that returns a single count()
# Exits with code 1 if query returns 0, with code 0 otherwise
f_query_row_exists() {
    local ret=$(f_query "$1")
    echo "$ret" | grep -q '^[0-9][0-9]*$' || f_fatal "Query returned unexpected data: [$ret]"
    test $ret -ne 0
}

# Returns 0 if provided Cron prefix is correct, 1 otherwise
# Arg1: Cron prefix, like "* * * * *"
f_cron_validate_schedule() {
    # Verify that the prefix contains 5 distinct fields
    echo "$1" | \
        sed 's/\s\s*[^$]/\n/g' | wc -l | grep -q 5 || return 1
	echo "$1" | grep -q '[0-9\*/ ]*' || return 1
	return 0
}

# Update specific configuration entry in a file, where:
# Arg1: file path
# Arg2: Left part of the line to be modified
# Arg3: Right part with the value to be inserted in the file
f_mod_conf_file() {
    [ -f $1 ] || f_fatal
    if grep -q "^$2 " $1
    then
        sed -i "s/^$2 .*$/$2 $3/" $1 || f_fatal
    else
        echo "$2 $3" >> $1
    fi
    grep -Hn "^$2" $1
}

