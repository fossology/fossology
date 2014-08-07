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

/** how to use: php unique.php input_file **/
/** will remove the duplicate lines on input_file */

if ($argc < 2 || empty($argv[1])) {
   print "please specify the file you want to parse.\n";
   exit;
}

//print "scanning_file is:$scanning_file\n";
$scanning_file = $argv[1];
$lines = file($scanning_file);
$count1 = count($lines);
$lines = array_unique($lines);
$count2 = count($lines);
file_put_contents($scanning_file, implode('', $lines));

?>
