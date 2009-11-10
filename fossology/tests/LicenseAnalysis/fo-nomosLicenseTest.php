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

$masterPath = NULL;

/* process parameters */
$Usage = "{$argv[0]} [-h] {-f filepath | -d directorypath}\n" .
$options = getopt("hf:d:m:");
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
	$dir = $options['d'];
	$directory = rtrim($dir, '/');

	global $directory;
}
if (array_key_exists("m",$options)) {
	$mPath = $options['m'];
	$masterPath = rtrim($mPath, '/');
}
if (!array_key_exists("d",$options) && !array_key_exists("f",$options)) {
	print $Usage;
	exit(1);
}
/*
 * bug: where is a single file processed?
 */

/* load the master results to compare against, the results are filtered
 * as part of the load
 */
$Master = array();
$Master = loadMasterResults($masterPath);

//print "Master file is:\n";print_r($Master) . "\n";

/*
 Use a trick here.  Since the subdir paths for the eddy tests must be the
 same as the master results file, we can cheat.  using the directory passed in
 all we have to do is pass in the path from the master file, since they must
 be the same, it will be the correct path and don't have to actually do
 any processing of the directory passed in.
 */

/* Get the list of input files, using the subdir path as the key
 $FileList = array();
 $FL = allFilePaths($directory);
 print "allFilePaths returned:\n";print_r($FL) . "\n";

 $FileList = filesByDir($directory);
 //print "FilesByDir returned:\n";print_r($FileList) . "\n";
 */
/* analyze each file for possible licenses */
$all          = array();
$nomosResults = array();

// need file logic here....
$nomosResults = foLicenseAnalyis($Master, 'nomos');

//print "Nomos results are:\n";print_r($nomosResults) . "\n";

if(empty($nomosResults)) {
	print "FATAL! nomos analysis Failed!\n";
	debug_print_backtrace();
	exit(1);
}

$Results = compare2Master($nomosResults, $Master);

$totals     = $Results[0];
$allResults = $Results[1];

//print "Comparison results are:\n";print_r($allResults) . "\n";

print "Nomos license match results:\n";
print "\tPasses: {$totals['pass']}\n";
print "\tFailures: {$totals['fail']}\n";

if($totals['fail'] != 0) {
	print "Failures are:\n";
	foreach($allResults as $fpath => $results) {
		foreach($results as $key => $lic) {
			if($key === 'fail') {
				if(empty($lic)) {
					continue;
				}
				else {
					print "$fpath:\n";
					foreach($lic as $failure) {
						print "    $failure\n";
					}
				}
			}
		} // foreach
	} // foreach
} // if

//print "Comparison results are:\n";print_r($allResults) . "\n";
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

$saveFile = 'Nomos-Eddy-Results.' . date('YMd');
if(saveAllResults($saveFile, $allResults)){
	print "nomos results generated and saved in file:\n$saveFile\n";
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