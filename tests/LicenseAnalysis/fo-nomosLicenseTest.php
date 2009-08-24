#!/usr/bin/php
<?php
/*
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
 */
/**
 * fo-nomosLicenseTest
 *
 * run fo-nomos license analysis for eddy test files
 *
 * Usage: [-h] {-f filepath | -d directorypath}
 *
 * created: Jun 1, 2009
 * @version "$Id:  $"
 */

require_once('../commonTestFuncs.php');
require_once('testLicenseLib.php');

$ldir = '/home/fosstester/regression/license/eddy/GPL';
//$ldir = '/home/fosstester/regression/license/eddy/GPL/GPL_v3'
//$ldir = '/home/fosstester/regression/license/eddy';


/* process parameters */
$Usage = "{$argv[0]} [-h] {-f filepath | -d directorypath}\n" .
$options = getopt("hf:d:");
if (empty($options)) {
  print $Usage;
  exit(1);
}
if (array_key_exists("h",$options)) {
  print $Usage;
  exit(0);
}
if (array_key_exists("f",$options)) {
  $file = $options['f'];
}
if (array_key_exists("d",$options)) {
  $directory = $options['d'];
}
if (!array_key_exists("d",$options) && !array_key_exists("f",$options)) {
  print $Usage;
  exit(1);
}

/* load the master results to compare against */
$Master = array();
$Master = loadMasterResults();

/* Get the list of input files */
$FileList = array();
$FL = allFilePaths($directory);
//print "allFILEPATHS returned:\n";print_r($FL) . "\n";
$FileList = filesByDir($directory);
print "FilesByDir returned:\n";print_r($FileList) . "\n";

exit(777);

/* analyze each file for possible licenses */
$all        = array();
$foNomosRaw = array();

$foNomosRaw = foLicenseAnalyis($FileList,'chanomos');
if(empty($foNomosRaw)) {
  print "FATAL! fo-nomos analysis Failed!\n";
  debug_print_backtrace();
  exit(1);
}
//print "foNomos results are:\n";print_r($foNomosRaw) . "\n";

// need to expand this and put it back together like it was passed in.
$fileResults = array();
foreach($foNomosRaw as $topDir => $results) {
  foreach($results as $dir => $fileList) {
    foreach($fileList as $file => $answer) {
      $answer = trim($answer);
      $list = filterNomosResults($answer);     // name filter
      $all = explode(",",$list);
      $fileResults[$file] = $all;
    }
    $dirResults[$dir] = $fileResults;
    $fileResults = array();
  }
  $filtered[$topDir] = $dirResults;
  $dirResults = array();
}
//print "Filtered foNomos results are:\n";print_r($filtered) . "\n";


/* Compare to master */
$Results = compare2Master($filtered, $Master);

$totals     = $Results[0];
$tbyDir     = $Results[1];
$allResults = $Results[2];
print "Comparison totals are:\n";print_r($totals) . "\n";
print "Totals by Dir are:\n";print_r($tbyDir) . "\n";
print "Comparison results are:\n";print_r($allResults) . "\n";

exit(777);
/* store comparison results in a file */
$saveFile = 'FoNomos-Results-Summary.' . date('YMd');
if(saveTotals($saveFile, 'foNomos', $totals)){
  print "fo-nomos Summary results generated and saved in file:\n$saveFile\n";
}
else {
  print "Error! could not save results, printing to the screen\n";
  foreach($totals as $file => $result){
    print "$file: $result\n";
  }
}

$saveFile = 'FoNomos-All-Results.' . date('YMd');
if(saveAllResults($saveFile, $allResults)){
  print "fo-nomos results generated and saved in file:\n$saveFile\n";
  exit(0);
}
else {
  print "Error! could not save results, printing to the screen\n";
  foreach($Result as $file => $result){
    print "$file: $result\n";
  }
  exit(1);
}
?>