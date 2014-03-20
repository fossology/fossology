#!/bin/bash
#
clear
#
# Simple shell script to adjust ppostgresql.conf to recommended Fossology settings as of 2013
#
# Experimental investigation into automagically adjusting based upon memory size
#
# First cut - determine memory size...
#
# We'll squirt the memory size info into a temp file and then read it back into a variable
#
# head -n2 /proc/meminfo > memsize.tmp
# Now try and read the memory size information back in
#
# Use sed to extract the memory size
# sed -n 's/^\(^S*\)\(s*\)\(d*\)/\3/' memsize.tmp > memsize.val
#
MEM_SIZE_RAW=$(awk -F":" '$1~/MemTotal/{print $2}' /proc/meminfo )
MEM_SIZE=`echo $MEM_SIZE_RAW| cut -d' ' -f 1`
echo 'MEM_SIZE contains:' $MEM_SIZE
#
# Convert to gigabytes
let 'MEM_SIZE /= 1048576'
let 'MEM_SIZE += 1'
echo 'System Memory calculated to be:' $MEM_SIZE 'GB'
#
# Given Gigabytes - evaluate appropriate Postgresql values
#
let 'SHARED_BUFFERS=MEM_SIZE / 4'
SHARED_BUFFERS+="GB"
let 'EFFECTIVE_CACHE_SIZE=MEM_SIZE / 2'
EFFECTIVE_CACHE_SIZE+="GB"
let 'MAINTENANCE_WORK_MEM=MEM_SIZE * 50'
MAINTENANCE_WORK_MEM+="MB"
#
# Enable diagnostic output if required
# echo $SHARED_BUFFERS
# echo $EFFECTIVE_CACHE_SIZE
# echo $MAINTENANCE_WORK_MEM
#
#set -x
#
# function: adjustParam
# adjust parameter to a new value in a configuration file
# $1 is the parameter name
# $2 is the new value
# $3 is the config file name
adjustParam()   {
    sed -i 's/^#\{,1\}\('"$1"'\s*=\s*\)\(\S*\)\(.*\)$/\1'"$2"'\3/' "$3"
}
#
echo 'Automated postgresql.conf configuration adjustments. - V9.1 - Variable System Memory'
echo
#
# uses pgConf to store file path - change if required
#
pgConf=/etc/postgresql/9.1/main/postgresql.conf
if [ -e $pgConf ]
    then
    echo 'Copies postgresql.conf to current directory and creates a backup file'
    echo 'Modifies it and then displays variance for confirmation.'
    cp $pgConf pgsql_conf.orig
    cp pgsql_conf.orig postgresql.conf
    echo 'If happy with changes you should save original and move new one back.'

    # set filename once for the script
    file=postgresql.conf

    echo "Setting listen_addresses to '*'"
    param=listen_addresses
    value="'*'"
    adjustParam "$param" "$value" "$file"

    echo 'Setting max_connections to 50'
    param=max_connections
    value=50
    adjustParam "$param" "$value" "$file"

    echo 'Setting shared_buffers to 1GB'
    param=shared_buffers
#    value=1GB
    value="$SHARED_BUFFERS"
    adjustParam "$param" "$value" "$file"

    echo 'Setting effective_cache_size to 2GB'
    param=effective_cache_size
#    value=2GB
    value="$EFFECTIVE_CACHE_SIZE"
    adjustParam "$param" "$value" "$file"

    echo 'Setting work_mem to 128MB'
    param=work_mem
    value=128MB
    adjustParam "$param" "$value" "$file"

    echo 'Setting maintenance_work_mem to 200MB'
    param=maintenance_work_mem
#    value=200MB
    value="$MAINTENANCE_WORK_MEM"
    adjustParam "$param" "$value" "$file"

    echo 'Setting fsync to on'
    param=fsync
    value=on
    adjustParam "$param" "$value" "$file"

    echo 'Setting full_page_writes  to off'
    param=full_page_writes
    value=off
    adjustParam "$param" "$value" "$file"

    echo 'Setting standard_conforming_strings to on'
    param=standard_conforming_strings
    value=on
    adjustParam "$param" "$value" "$file"

    echo 'Setting autovacuum to on'
    param=autovacuum
    value=on
    adjustParam "$param" "$value" "$file"

    echo 'postgresql.conf adjusted!'
    echo
    echo 'Display the changes made'
    diff pgsql_conf.orig postgresql.conf
    echo 'If these are OK you should copy postgresql.conf back and restart apache'
    else
    echo 'postgresql.conf was not located as expected. Please adjust pgConf to suit.'
fi

