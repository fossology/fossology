#!/usr/bin/php
<?php
/***********************************************************
 Copyright (C) 2011 Hewlett-Packard Development Company, L.P.

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

// Figure out where we are

if(!defined('TESTROOT'))
{
  $trPath = __DIR__;
  define('TESTROOT',$trPath);
}
if(chdir(TESTROOT) === FALSE)
{
  echo "FATAL! cannot cd to:\n" . TESTROOT . "\n";
  exit(1);
}
$home = getcwd();

$WORKSPACE = NULL;

if(array_key_exists('WORKSPACE', $_ENV))
{
  $WORKSPACE = $_ENV['WORKSPACE'];
}

// this file is used for all reports
if(is_NULL($WORKSPACE))
{
  $xslFile = TESTROOT . '/Reports/junit-noframes.xsl';
}
else
{
  $xslFile = $WORKSPACE . '/fossology/tests/Reports/junit-noframes.xsl';
}

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
$cmdLine = TESTROOT . '/runFunctionalTests.php > '. TESTROOT .
  '/Functional-Test-Results.xml 2>&1';
$lastFunc = exec($cmdLine, $output, $rtn);

// Generate an html report from the junit report produced by the test run

//echo "Generating html report\n";
$inFile = TESTROOT . '/Functional-Test-Results.xml';
$outFile = TESTROOT . '/Reports/FunctionalTestResults.html';

$report = genHtml($inFile, $outFile, $xslFile);
if(!empty($report))
{
  echo "Error: Could not generate an Functinoal Test HTML report: " .
 "FunctionalTestResults.html.\n";
  echo "Errors were:$report\n";
}
// check for failures in the report
else
{
  try {
    $upFail = check4failures($inFile);
    if(!is_null($upFail))
    {
      echo "There were errors in $inFile\n";
    }
  }
  catch (Exception $e)
  {
    echo "Failure: Could not check file $inFile for failures\n";
  }
}

if(chdir($home) === FALSE)
{
  echo "FATAL! cannot cd to $home\n";
  exit(1);
}
// Run the uploads (which are a set of tests in themselves)

$cmdLine = TESTROOT . '/runUploadsTest.php > ' .
TESTROOT . '/Uploads-Results.xml';
$lastFunc = exec($cmdLine, $output, $rtn);
if($rtn != 0)
{
  echo "NOTE: Uploads did not finish in 2 hours, will not run Verify Tests\n";
  exit(1);
}
// Generate an upload test html report from the junit report
//echo "Generating html report\n";
$inFile = TESTROOT . '/Uploads-Results.xml';
$outFile = TESTROOT . '/Reports/UploadsTestResults.html';
$upFail = array();

$report = NULL;
$report = genHtml($inFile, $outFile, $xslFile);
if(!empty($report))
{
  echo "Error: Could not generate an Upload Test HTML report " .
    "Uploads-Results.xml.\n";
  echo "Errors were:$report\n";
}
// check for failures in the report
else
{
  try {
    $urFail = check4failures($inFile);
    if(!is_null($urFail))
    {
      echo "There were errors in the $inFile\n";
    }
  }
  catch (Exception $e)
  {
    echo "Failure: Could not check file $inFile for failures\n";
  }
}

// Run the upload verification tests
$xmlFile = TESTROOT . '/Uploads-Test-Results.xml';
$cmdLine = TESTROOT . '/runVerifyUploadsTests.php > ' .
  "$xmlFile";
$lastFunc = exec($cmdLine, $output, $rtn);

// remove the blank line at the top of the file
// @todo find out how the blank line is being generated and fix.
$inFile = TESTROOT . '/Uploads-Test-Results.xml';
$outFile = TESTROOT . '/Reports/VerifyTestResults.html';

$fileString = file_get_contents($inFile,FALSE ,NULL, 1);
$bytes = 0;
$bytes = file_put_contents($inFile, $fileString);
if($bytes > 0)
{
  // Generate an upload test html report from the junit report
  //echo "Generating html report\n";
  $report = NULL;
  $report = genHtml($inFile, $outFile, $xslFile);
  if(!empty($report))
  {
    echo "Error: Could not generate an Upload Test HTML report " .
    "VerifyTestResults.html.";
    echo "Errors were:$report\n";
  }
  // check for failures in the report
  else
  {
    try {
      $verFail = check4failures($inFile);
      if(!is_null($verFail))
      {
        echo "There were errors in the $inFile\n";
      }
    }
    catch (Exception $e)
    {
      echo "Failure: Could not check file $inFile for failures\n";
    }
  }
}
else
{
  echo "ERROR: No data written to file:\n$inFile\n";
  echo "bytes written is:$bytes\n";
}
?>
