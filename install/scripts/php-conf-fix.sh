#!/bin/bash
#
# Simple shell script to adjust php.ini to recommended Fossology  settings as of 2013
#
# uses phpIni to store file path - change if required
#
echo 'Automated php.ini configuration adjustments'
echo
phpIni=/etc/php5/apache2/php.ini
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
    echo 'php.ini adjusted!'
    echo
    echo 'Display the changes made'
    diff php.ini.orig php.ini
    echo 'If these are OK you should copy php.ini back and restart apache'
    else
    echo 'php.ini was not located as expected. Please adjust phpIni to suit.'
fi

