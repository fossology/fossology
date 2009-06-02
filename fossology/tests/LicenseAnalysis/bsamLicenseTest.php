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
 * bsamLicenseTest
 *
 * run bsam license analysis for eddy test files
 *
 *
 * created: Jun 1, 2009
 * @version "$Id:  $"
 */

// get input list
// run fosslic
// filter results
// write results to a file.

require_once('../commonTestFuncs.php');
require_once('testLicenseLib.php');

$ldir = '/home/fosstester/regression/license/eddy/GPL/GPL_v3';
//$ldir = '/home/fosstester/regression/license/eddy';

/* load the master results to compare against */
$Master = array();
$Master = loadMasterResults();

/* Get the list of input files */
$FileList = array();
$FileList = allFilePaths($ldir);
//print "allFilePaths returned:\n";print_r($FileList) . "\n";

/* use fosslic to analyze each file for possible licenses */
$all       = array();
$BsamRaw   = array();
$Bsam      = array();

$BsamRaw = foLicenseAnalyis($FileList,'bsam');
if(empty($BsamRaw)) {
  print "FATAL! Bsam analysis Failed!\n";
  debug_print_backtrace();
  exit(1);
}
//print "bsamRaw results are:\n";print_r($BsamRaw) . "\n";
/*
 * cleanup for bsam results:
 * 0. Remove filepath as part of the results
 * 1. change ,\s to ,
 * 2. change spaces in words to _ (e.g. GPL v3 -> GPL_v3)
 * 3. remove -style
 * 4. remove ' around name
 */

/* remove the filepath as part of the resutls */
foreach($BsamRaw as $file => $result) {
  $tList = preg_replace("/.*?:/",'',$result);  // filepath
  $tList = trim($tList);
  $noClist = str_replace(', ', ',', $tList);  //,\s to ,
  $slist = str_replace(' ', '_', $noClist);   // \s to _
  $qlist = str_replace('-style', '', $slist); // remove -style
  $alist = str_replace("'", '', $qlist);      // remove 's
  $list = filterFossologyResults($alist);     // name filter
  $all = explode(",",$list);
  $Bsam[$file] = $all;
}
//print "bsam results are:\n";print_r($Bsam) . "\n";

/* Compare to master */
$Results = compare2Master($Bsam, $Master);

print "Comparison results are:\n";print_r($Results) . "\n";

/* store comparison results in a file */
$saveFile = 'Bsam-Results.' . date('YMd');
print "save file would be:$saveFile\n";

if(saveResults($saveFile, $Results)){
  print "Bsam results generated and saved in file:\n$saveFile\n";
}
else {
  print "Error! could not save Bsam results, printing to the screen\n";
  foreach($Bsam as $file => $result){
    print "$file:\n";print_r($result) . "\n";
  }
  exit(1);
}
?>