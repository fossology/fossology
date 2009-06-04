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
//$ldir = '/home/fosstester/regression/license/eddy/GPL';
//$ldir = '/home/fosstester/regression/license/eddy';

/* load the master results to compare against */
$Master = array();
$Master = loadMasterResults();

/* Get the list of input files */
$FileList = array();
$FileList = allFilePaths($ldir);
//print "allFilePaths returned:\n";print_r($FileList) . "\n";

/* use fosslic to analyze each file for possible licenses */
$all        = array();
$foNomosRaw = array();

$foNomosRaw = foLicenseAnalyis($FileList,'chanomos');
if(empty($foNomosRaw)) {
  print "FATAL! fo-nomos analysis Failed!\n";
  debug_print_backtrace();
  exit(1);
}
print "foNomos results are:\n";print_r($foNomosRaw) . "\n";

foreach($foNomosRaw as $file => $result) {
  $tList = trim($result);
  $list = filterNomosResults($tList);     // name filter
  $all = explode(",",$list);
  $foNomos[$file] = $all;
}
//print "foNomos results are:\n";print_r($foNomos) . "\n";

/* Compare to master */
$Results = compare2Master($foNomos, $Master);

print "Comparison results are:\n";print_r($Results) . "\n";

/* store comparison results in a file */
$saveFile = 'FoNomos-Results.' . date('YMd');
print "save file would be:$saveFile\n";
/*
if(saveResults($saveFile, $Results)){
  print "Bsam results generated and saved in file:\n$saveFile\n";
}
else {
  print "Error! could not save Bsam results, printing to the screen\n";
  foreach($Bsam as $file => $result){
    print "$file: $result\n";
  }
  exit(1);
}
*/
?>