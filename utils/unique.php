#!/usr/bin/php
<?php
/*
 SPDX-FileCopyrightText: © 2014 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/
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
