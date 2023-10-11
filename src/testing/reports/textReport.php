#!/usr/bin/php
<?php
/*
SPDX-FileCopyrightText: © 2008 Hewlett-Packard Development Company, L.P.

SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * textReport
 * \brief Generate a summary report from a single test run
 *
 * Takes the raw results file produced by the tests, parses it and generates
 * the summary on stdout.
 *
 * @param string $resultsFile -f <file>
 *
 * @version "$Id: textReport.php 3567 2010-10-15 01:06:58Z rrando $"
 *
 * Created on Aug. 25, 1009
 */

require_once('reportClass.php');
require_once('testSuites.php');


//$res = '/home/markd/Src/fossology/tests/FossTestResults-2009-08-25-10:31:39-pm';

$options = getopt("hf:");

$Usage = "$argv[0] [-h] -f <test-results-file>\n";

if(empty($options)) {
	print $Usage;
	exit(1);
}

if(array_key_exists('h',$options)) {
	print $Usage;
	exit(0);
}
if(array_key_exists('f',$options)) {
	$filePath = $options['f'];
	if(!strlen($filePath)) {
		print $Usage;
		exit(1);
	}
	if(!file_exists($filePath)) {
		print "Error! $filePath does not exist or is not readable\n";
		exit(1);
	}
}

$tr = new TestReport($filePath);

$results = $tr->parseResultsFile($filePath);
//print "got back the following from parseResultsFile:\n";
//print_r($results) . "\n";

$totalPasses     = 0;
$totalFailures   = 0;
$totalExceptions = 0;

/**
 * groupByType
 *
 * group a failure type by suite.  Used to gather up all failures or exceptions
 * for a given test suite.
 *
 * @param string $suiteName the name of the test suite
 * @param array $list an array of keys => array (the list)
 * @return array $failType
 *
 */
function groupByType($suiteName, $list) {

	if(!is_array($list)) {
		return(FALSE);
	}
	if(!strlen($suiteName)) {
		return(FALSE);
	}

	foreach($list as $nextList){
		foreach($nextList as $index => $resultList){
			$failTypeList[] = $resultList;
		}
	}
	return($failType[$suiteName] = $failTypeList);
} // groupByType

function printByType($typeName, $typeList) {

	if(!is_array($typeList)) {
		return(FALSE);
	}
	if(!strlen($typeName)) {
		return(FALSE);
	}

	print "The following Test Suites had $typeName:\n";
	foreach($typeList as $suite => $flist){
		//print "DB: fsuite and flist are:$fsuite,$flist\n";
		print "$suite:\n";
		$len = strlen($suite);
		$len++;                   // for the ':'
		printf("%'-{$len}s\n", '');
		foreach ($flist as $fline) {
			print "   $fline\n";
		}
		print "\n";
	}
} // printByType

// summarize the results for this run.  Note failures and exceptions by suite

$suiteFailures   = array();
$suiteExceptions = array();
$suitesRun       = array();

foreach($results as $suite => $result) {
	foreach($result as $partResult) {

		// gather the unique suites that were run
		if (array_key_exists($suite, $testSuites))
		{
			if(!in_array($suite, $suitesRun))
			{
				$suitesRun[] = $suite;
			}
		}
		if (is_array($partResult)) {
			if(array_key_exists('failures',$partResult)) {
				$suiteFailures[$suite] = groupByType($suite,$partResult);
			}

			if(array_key_exists('exceptions',$partResult)) {
				$suiteExceptions[$suite] = groupByType($suite,$partResult);
			}
		}
		// it's the suite result summary, compute totals
		else {
			list($passes, $fail, $except) = preg_split('/:/',$partResult);
			//print "DB: passes, fail, except are:$passes,$fail,$except\n";
			$totalPasses += $passes;
			$totalFailures += $fail;
			$totalExceptions+= $except;
		}
	}
}
// print the summary and any failures and exceptions

print "Test Results for FOSSology UI Test suite\n";
print "Tests run on $tr->Date at $tr->Time using SVN Version $tr->Svn\n";
print "The tests Suites that were run are:\n";
printf("%'-35s\n", '');

foreach ($suitesRun as $suite)
{
	if (array_key_exists($suite, $testSuites))
	{
		print "$suite: $testSuites[$suite]\n\n";
	}
}

print "Test Results Summary\n"; 
printf("%'-37s\n", '');
print "Total Passes: $totalPasses\n";
print "Total Failures: $totalFailures\n";
print "Total Exceptions: $totalExceptions\n";
printf("%'-37s\n", '');
//print "The following Test Suites had Failures:\n";
printByType('failures', $suiteFailures);
printf("%'-37s\n", '');
//print "The following Test Suites had Exceptions:\n";
printByType('Exceptions', $suiteExceptions);

exit(0);
