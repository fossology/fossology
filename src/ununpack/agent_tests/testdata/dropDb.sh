#! /bin/sh
# Copyright (C) 2018, Siemens AG
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

# Remove the test config directory

user=$(awk -F "=" '/user/ {print $2}' testVariables.var | tr -d '; ')
confDir=$(awk -F "=" '/confDir/ {print $2}' testVariables.var | tr -d '; ')
finalConf=$(awk -F "=" '/finalConf/ {print $2}' testVariables.var | tr -d '; ')
dbname=$(awk -F "=" '/dbname/ {print $2}' testVariables.var | tr -d '; ')

psql -c "DROP DATABASE $dbname;" postgres $user
rm -rf $confDir $finalConf testVariables.var
