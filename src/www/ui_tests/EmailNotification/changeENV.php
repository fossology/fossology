#!/usr/bin/php
<?php
/*
 SPDX-FileCopyrightText: © 2008 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * changeENV.php
 *
 * change the test environment file.
 *
 * @param string -s $subject the subject string to change
 * @param string -c $change the string to change the subject to.
 *
 * @return 0 for OK, 1 for failure
 */

$argv = array();
$opts = array();

print "changeENV starting....\n";
$opts = getopt('hc:');
//print "changeENV: opts is:\n";print_r($opts) . "\n";
$Usage = "{$argv[0]}: [-h] -c <change-string>\n";

if (empty($opts)) {
  print $Usage;
  exit(1);
}

if (array_key_exists("h",$opts)) {
  print $Usage;
  exit(0);
}
/*
   Only required option, get it and check it
 */
if (array_key_exists("c",$opts)) {
  $change2 = $opts['c'];
  if(!strlen($change2)) {
    print $Usage;
    exit(1);
  }
}
else {
  print $Usage;
  exit(1);
}

$testEnv = '../../../tests/TestEnvironment.php';

$sedLine = "sed -e \"1,$ s/USER=.*/USER='$change2';/\" ".
               "-e \"1,$ s/WORD=.*/WORD='$change2';/\" $testEnv
               ";
$changed = exec($sedLine, $out, $rtn);
//print "output is:\n";print_r($out) . "\n";

$FH = fopen($testEnv, 'w') or die("Can't open $testEnv\n $phpErrorMsg");
foreach ($out as $line) {
  if(FALSE === fwrite($FH, "$line\n")) {
    print "FATAL! cannot wite to $testEnv\n";
    exit(1);
  }
}
fclose($FH);
exit(0);
