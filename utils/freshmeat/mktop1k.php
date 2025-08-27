#!/usr/bin/php

<?php
/*
 mktop1k.php
 SPDX-FileCopyrightText: © 2007 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * mktop1k: extract the top 1000 Freshmeat projects from the rdf into a file.
 *
 * mktop1k makes no attempt to create unique output file names. You have been
 * warned.
 *
 * @param string $in-file path to uncompressed FM rdf xml file
 * @param string $out-file output file name. Will use cwd if no path supplied.
 *
 *
 * @package mktop1k
 * @author mark.donohoe@hp.com
 * @version 0.3
 *
 */

// FIXME: this should bet a global from pathinclude? $LIBDIR = '/usr/local/lib';
require_once("/usr/share/fossology/php/pathinclude.php");
require_once("$LIBDIR/lib_projxml.h.php");
//require_once("./lib_projxml.h.php");            // dev copy


$usage = <<< USAGE
Usage: mktop1k [-h] -i <in-file> -o <out-file> [-n nnn]
   Where: -h optional help, displays this message
          <in-file> path to an uncompressed Freshmeat rdf XML file
          <out-file> path to filename where the xml output will be generated.
          -n nnn optional parameter to indicate how many projects to
             extract.

             Default is 1000.

             The projects are always extracted in priority order.
             For example, -n 10 will get the top 10 Freshmeat packages.
             A range of numbers is not supported.

USAGE;

if ($argc <= 4) {
  echo $usage;
  exit(1);
}

// default number of projects to get.
$HowMany_projects = 1000;

for ($i = 1; $i < $argc; $i++) {
  switch ($argv[$i]) {
    case '-i':
      $i++;
      if (isset($argv[$i])) {
        $in_file = $argv[$i];
      }
      else {
        die("ERROR: Must specify an uncompressed filename after -i");
      }
      break;
    case '-h':
      echo $usage;
      exit(0);
      break;
    case '-n':
      $i++;
      if (isset($argv[$i])) {
        $HowMany_projects = (int) $argv[$i];
      }
      else {
        die("ERROR: Must specify a number between 1-1000 after -n");
      }
      break;
    case '-o':
      $i++;
      if (isset($argv[$i])) {
        $out_file = $argv[$i];
      }
      else {
        die("ERROR: Must specify an uncompressed filename after -o");
      }
      break;
    default:
      die("ERROR: Unknown argument: $argv[$i]\n$usage");
      break;
  }
}

$F1 = fopen("$in_file", 'r') or die("can't open file: $php_errormsg\n");

/* look for the top 1000 projects, when found, write the project
 entry to a file.

 NOTE: I'm bothered by something here... while one gets the top
 1000, there could be drastic differences (not likely between any two
 days, but possible)....It doesn't really affect this code, but could
 affect users of the output files.
 */

$Output = fopen("$out_file", 'w') or die("Can' open: $php_errormsg\n");

echo "Extracting the top $HowMany_projects projects from:\n$in_file\n";
echo "\nWriting the top $HowMany_projects projects to: $out_file\n";

// need a valid doc, write the header 1st, and open tag
write_hdr($Output);

while(false != ($line = fgets($F1, 1024))) {
  #  echo "Line is:\n$line\n";

  if (preg_match('/<project>/', $line)) {
    $proj_mark = ftell($F1);
  }
  elseif (preg_match('/<popularity_rank>[0-9].*</', $line)) {
    $pos = strpos($line, '>');
    $rank_pos = $pos + 1;
    $rank_end = strpos($line, '</', $rank_pos);
    $rank_len = $rank_end - $rank_pos;
    $rank = substr($line, $rank_pos, $rank_len);
    if ((int)$rank <= $HowMany_projects){
      //pdbg("Processing rank:$rank");
      write_entry($F1, $proj_mark, $Output);
    }
  }

}

// write the end tag and close up shop

close_tag($Output);
fclose($F1);
fclose($Output);

echo "Done\n";
