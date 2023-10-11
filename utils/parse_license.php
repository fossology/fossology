#!/usr/bin/php
<?php
/*
 SPDX-FileCopyrightText: © 2014 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

/**

/** how to use: php parse_license.php input_file > des_license_list.txt **/
/** please go through des_license_list.txt to do some changes if needed **/

if ($argc < 2 || empty($argv[1])) {
   print "please specify the file you want to parse.\n";
   exit;
}

$scanning_file = $argv[1];
//print "scanning_file is:$scanning_file\n";
$handle = fopen($scanning_file, "r");
if ($handle) {
  while (($line = fgets($handle)) !== false) {
    if (empty($line)) exit;

    if (strstr($line, "LS_")) print "$line";
    else { // not include 'LS_'
      $res = explode("\"", $line);
      $count = count($res);
      if (2 > $count) continue; // no found 
      //print_r($res);
      //print "count is:$count, line is:$line";
      for($i = $count - 1; $i >=0; $i--)
      {
        if (strstr($res[$i], ";")) // ignore the string include ';'
        {
          //print "res[$i] is:$res[$i]\n";
          continue;
        }
        else {
          print "$res[$i]\n";
          break;
        }
      } // for
    } // not include 'LS_'
  } // while
} else {
  // error opening the file.
} 
fclose($handle);
