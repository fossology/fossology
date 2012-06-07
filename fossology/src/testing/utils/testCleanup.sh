#!/bin/bash
#
# cron wrapper to launch the runTestCleanup as fosstester
#
cd fossology/tests
export  PATH=$PATH:/usr/local/bin
./runTestCleanup.php > cleanup.log
