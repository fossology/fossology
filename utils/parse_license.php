#!/usr/bin/php
<?php
/***********************************************************
  Copyright (C) 2014 Hewlett-Packard Development Company, L.P.

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

?>
