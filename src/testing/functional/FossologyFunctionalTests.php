#!/usr/bin/php
<?php
/*
 SPDX-FileCopyrightText: © 2011 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * \brief Run the simpletest FOSSology functional tests
 *
 * Produces a number of junit.xml files and processes those into html files.
 *
 * Assumes being run as the user jenkins by the application jenkins.  The
 * script can be run standalone by other users from the jenkins workspace area.
 *
 * @version "$Id$"
 */

// @todo add in phpunit tests

// ?? where do new user access tests go?


/* simpletest includes */
require_once '/usr/local/simpletest/unit_tester.php';
require_once '/usr/local/simpletest/collector.php';
require_once '/usr/local/simpletest/web_tester.php';
require_once '/usr/local/simpletest/reporter.php';
require_once '/usr/local/simpletest/extensions/junit_xml_reporter.php';

require_once('common-Report.php');

global $testSuite;
global $home;

// The tests are run inside of jenkins the workspace level, so you have to cd
// into the sources.
// @todo, fix this to work in both jenkins and non-jenkins runs.

if(chdir('fossology/tests') === FALSE)
{
  echo "FATAL! cannot cd to fossology/tests\n";
  exit(1);
}
$home = getcwd();

$WORKSPACE = NULL;

if(array_key_exists('WORKSPACE', $_ENV))
{
  $WORKSPACE = $_ENV['WORKSPACE'];
}

// this file is used for all reports
$xslFile = $WORKSPACE . '/fossology/tests/Reports/hudson/junit-noframes.xsl';

// check that test config file exists and that test data exists
$ckConfig = exec('./checkConfig.php', $configOut, $configRtn);
if($configRtn != 0)
{
  echo "FATAL! Cannot create test configuation file TestEnvironment.php in " .
    "....fossology/tests\n";
  exit(1);
}
$ckTdata = exec('./checkTestData.php', $tdOut, $tdRtn);
if($tdRtn != 0)
{
  echo "FATAL! Errors when downloading and installing test data\n";
  echo "Errors were:\n";print_r($tdOut) . "\n";
  exit(1);
}

// run the functional tests
$cmdLine = "$WORKSPACE" . '/fossology/tests/runFunctionalTests.php > '.
 "$WORKSPACE" . '/fossology/tests/Functional-Test-Results.xml';
$lastFunc = exec($cmdLine, $output, $rtn);

// Generate an html report from the junit report produced by the test run

//echo "Generating html report\n";
$inFile = $WORKSPACE . '/fossology/tests/Functional-Test-Results.xml';
$outFile = $WORKSPACE . '/fossology/tests/Reports/FunctionalTestResults.html';

$report = genHtml($inFile, $outFile, $xslFile);
if(!empty($report))
{
  echo "Error: Could not generate an Upload Test HTML report." .
 "FunctionalTestResults.html.\n";
}
// check for failures in the report
else
{
  try {
    $upFail = check4failures($inFile);
    if(!is_null($upFail))
    {
      echo "There were errors in $inFile\n";
      //print_r($upFail) . "\n";
      exit(1);
    }
  }
  catch (Exception $e)
  {
    echo "Failure: Could not check file $inFile for failures\n";
    exit(1);
  }
}

if(chdir($home) === FALSE)
{
  echo "FATAL! cannot cd to $home\n";
  exit(1);
}
// Run the uploads (which are a set of tests in themselves)
$cmdLine = "$WORKSPACE" . '/fossology/tests/runUploadsTest.php > ' .
  "$WORKSPACE" . '/fossology/tests/Uploads-Results.xml';
$lastFunc = exec($cmdLine, $output, $rtn);
if($rtn != 0)
{
  echo "NOTE: Uploads did not finish in 2 hours, will not run Verify Tests\n";
  exit(1);
}
// Generate an upload test html report from the junit report
//echo "Generating html report\n";
$inFile = $WORKSPACE . '/fossology/tests/Uploads-Results.xml';
$outFile = $WORKSPACE . '/fossology/tests/Reports/UploadsTestResults.html';
$upFail = array();

$report = genHtml($inFile, $outFile, $xslFile);
if(!empty($report))
{
  echo "Error: Could not generate an Upload Test HTML report " .
    "Uploads-Results.xml.\n";
}
// check for failures in the report
else
{
  try {
    $urFail = check4failures($inFile);
    if(!is_null($urFail))
    {
      echo "There were errors in the $inFile\n";
      //print_r($urFail) . "\n";
      exit(1);
    }
  }
  catch (Exception $e)
  {
    echo "Failure: Could not check file $inFile for failures\n";
    exit(1);
  }
}

// Run the upload verification tests
$xmlFile = "$WORKSPACE" . '/fossology/tests/Uploads-Test-Results.xml';
$cmdLine = "$WORKSPACE" . '/fossology/tests/runVerifyUploadsTests.php > ' .
  "$xmlFile";
$lastFunc = exec($cmdLine, $output, $rtn);

// remove the blank line at the top of the file
// @todo find out how the blank line is being generated and fix.
$inFile = $WORKSPACE . '/fossology/tests/Uploads-Test-Results.xml';
$outFile = $WORKSPACE . '/fossology/tests/Reports/VerifyTestResults.html';

$fileString = file_get_contents($inFile,FALSE ,NULL, 1);
$bytes = 0;
$bytes = file_put_contents($inFile, $fileString);
if($bytes > 0)
{
  // Generate an upload test html report from the junit report
  //echo "Generating html report\n";

  $report = genHtml($inFile, $outFile, $xslFile);
  if(!empty($report))
  {
    echo "Error: Could not generate an Upload Test HTML report " .
    "VerifyTestResults.html.";
  }
  // check for failures in the report
  else
  {
    try {
      $verFail = check4failures($inFile);
      if(!is_null($verFail))
      {
        echo "There were errors in the $inFile\n";
        //print_r($verFail) . "\n";
        exit(1);
      }
    }
    catch (Exception $e)
    {
      echo "Failure: Could not check file $inFile for failures\n";
      exit(1);
    }
  }
}
else
{
  echo "ERROR: No data written to file:\n$cacheFile\n";
}
