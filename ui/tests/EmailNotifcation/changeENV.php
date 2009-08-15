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


$Usage = "{$argv[0]}: [-h] -s <subject-string> -c <change-string>\n";

print "changeENV starting....\n";
$opts = getopt('hs:c:');
//print "changeENV: opts is:\n";print_r($opts) . "\n";

if (empty($opts)) {
  print $Usage;
  exit(1);
}

if (array_key_exists("h",$opts)) {
  print $Usage;
  exit(0);
}

if (array_key_exists("s",$opts)) {
  $subject = $opts['s'];
  if(!strlen($subject)) {
    print $Usage;
    exit(1);
  }
}
else {
  print $Usage;
  exit(1);
}
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
$changed = exec("sed -e '1,\$s/$subject/$change2/' $testEnv", $out, $rtn);
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