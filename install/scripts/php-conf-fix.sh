#!/bin/bash
# SPDX-FileCopyrightText: © Fossology contributors

# SPDX-License-Identifier: GPL-2.0-only
#
# Simple shell script to adjust php.ini to recommended Fossology  settings as of 2013
#
# uses phpIni to store file path - change if required
#
PHP_PATH=$(php --ini | awk '/\/etc\/php.*\/cli$/{print $5}')
phpIni="${PHP_PATH}/../apache2/php.ini"

echo 'Automated php.ini configuration adjustments'
echo
if [ -f /etc/redhat-release ]; then
    phpIni=/etc/php.ini
    TIMEZONE=`readlink -f /etc/localtime | sed 's%/usr/share/zoneinfo/%%'`
else
    TIMEZONE=`cat /etc/timezone`
fi

if [ -z $TIMEZONE ]; then
    TIMEZONE="America/Denver"
fi

if [ -e $phpIni ]
then
    echo 'Copies php.ini to current directory and creates a backup file'
    echo 'Modifies it and then displays variance for confirmation.'
    cp $phpIni php.ini.orig
    cp php.ini.orig php.ini
    echo 'If happy with changes you should save original and move new one back.'
    echo 'Setting max execution time to 300 seconds (5 mins)'
    sed -i 's/^\(max_execution_time\s*=\s*\).*$/\1300/' php.ini
    echo 'Setting memory limit to 702M'
    sed -i 's/^\(memory_limit\s*=\s*\).*$/\1702M/' php.ini
    echo 'Setting post max size to 701M'
    sed -i 's/^\(post_max_size\s*=\s*\).*$/\1701M/' php.ini
    echo 'Setting max upload filesize to 700M'
    sed -i 's/^\(upload_max_filesize\s*=\s*\).*$/\1700M/' php.ini
    echo "Setting timezone to $TIMEZONE"
    sed -i "s%.*date.timezone =.*%date.timezone = $TIMEZONE%" php.ini
    echo 'php.ini adjusted!'
    echo
    echo 'Display the changes made'
    diff php.ini.orig php.ini
    echo $1
    if [ "$1" == "--overwrite" ]
    then
        cp -f php.ini $phpIni
    else
        echo 'If these are OK you should copy php.ini back and restart apache'
    fi
else
    echo 'php.ini was not located as expected. Please adjust phpIni to suit.'
fi
