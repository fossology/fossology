#!/usr/bin/php
<?php
/*
 SPDX-FileCopyrightText: © 2008 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * Are there any jobs running?
 *
 * Wait for 2 hours for the test jobs to finish, check every 10 minutes
 * to see if they are done.
 *
 * NOTE: this program depends on the UI testing infrastructure at this
 * point.
 *
 * @return boolean (0 = success, 1 failure)
 *
 * @version "$Id: wait4jobs.php 2511 2009-09-10 00:25:40Z rrando $"
 *
 * @TODO: make a general program that can wait an arbitrary time, should
 * also allow for an interval, e.g. check for 2 hours every 7 min.
 *
 * Created on Jan. 15, 2009
 */

require_once('TestEnvironment.php');
require_once('testClasses/check4jobs.php');

define("TenMIN", "600");
//print "I am:{$argv[0]}\n";

$Jq = new check4jobs();

/* check every 10 minutes, wait at most 3 hours for test jobs to finish */
$done = FALSE;
for($i=1; $i<=18; $i++) {
  //print "DB:W4Q: checking Q...\n";
  $number = $Jq->Check();
  if ($number != 0) {
    //print "sleeping 10 min...\n";
    sleep(TenMIN);
  }
  else {
    print "no jobs found in the Q:$number\n";
    $done = TRUE;
    break;
  }
}
if($done === FALSE) {
  print "{$argv[0]} waited for 2 hours and the jobs are still not done\n" .
        "Please investigate\n";
  exit(1);
}
if($done === TRUE){
  exit(0);
}
