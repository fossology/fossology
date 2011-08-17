#!/bin/bash
#
# cron wrapper to launch the nightly script as fosstester
#
cd fossology/tests
today=`date +'%b.%d.%Y'`
export  PATH=$PATH:/usr/local/bin
./Nightly.php > nightly-$today
