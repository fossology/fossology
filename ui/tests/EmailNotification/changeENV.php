#!/usr/bin/php
<?php
/***********************************************************
 Copyright (C) 2008 Hewlett-Packard Development Company, L.P.

 This program is free software; you can redistribute it and/or
 modify it under the terms of the GNU General Public License
 version 2 as published by the Free Software Foundation.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License along
 with this program; if not, write to the Free Software Foundation, Inc.,
 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 ***********************************************************/

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
?>