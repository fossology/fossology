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
 * @version "$Id: $"
 */

// @todo add in phpunit tests

// ?? where do new user access tests go?


/* simpletest includes */
require_once '/usr/local/simpletest/unit_tester.php';
require_once '/usr/local/simpletest/collector.php';
require_once '/usr/local/simpletest/web_tester.php';
require_once '/usr/local/simpletest/reporter.php';
require_once '/usr/local/simpletest/extensions/junit_xml_reporter.php';

/*$where = dirname(__FILE__);
 echo "where is:$where\n";
 if(preg_match('!/home/jenkins.*?tests.*!', $where, $matches))
 {
 echo "running from jenkins....fossology/tests\n";
 require_once('fossology/tests/common-report.php');

 }
 else
 {
 echo "using requires for running outside of jenkins\n";
 require_once('common-Report.php');
 }
 */

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
 "FunctionalTestResults.html. Error was\n$report\n";
}
// check for failures in the report
else
{
  try {
    $upFail = check4failures($inFile);
    if(!is_null($upFail))
    {
      echo "There were errors in $inFile\n";
      print_r($upFail) . "\n";
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
    "Uploads-Results.xml. Error was\n$report\n";
}
// check for failures in the report
else
{
  try {
    $urFail = check4failures($inFile);
    if(!is_null($urFail))
    {
      echo "There were errors in the $inFile\n";
      print_r($urFail) . "\n";
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
    echo "Error: Could not generate an Upload Test HTML report" .
    "VerifyTestResults.html Error was\n$report\n";
  }
  // check for failures in the report
  else
  {
    try {
      $verFail = check4failures($inFile);
      if(!is_null($verFail))
      {
        echo "There were errors in the $inFile\n";
        print_r($verFail) . "\n";
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
  echo "ERROR: No data written to file:\n$file\n";
}


/**
 * \brief Generate an html report from the junit xml
 *
 * @param string $inFile the xml input file
 * @param string $outFile the html output filename, the name should include .html
 * @param string $xslFile the xsl file used to transform the xml to html
 *
 * @return NULL on success, string on failure
 *
 * @todo check if there is an html extension, if not add one.
 */
function genHtml($inFile=NULL, $outFile=NULL, $xslFile=NULL)
{
  // check parameters
  if(empty($inFile))
  {
    return('Error: no input file');
  }
  else if(empty($outFile))
  {
    return('Error: no Output file');
  }
  else if(empty($xslFile))
  {
    return('Error: no xsl file');
  }
  $cmdLine = "Reports/hudson/xml2Junit.php -f $inFile -o $outFile -x $xslFile";
  $last = exec("$cmdLine", $out, $rtn);
  //echo "Last line of output from xml2:\n$last\n";
  //echo "output from xml2 is:\n";
  //print_r($out) . "\n";

  if($rtn != 0)
  {
    $errorString = 'Error: xml2Junit had errors, see below';
    $errorString .= implode(' ', $out);
    return($errorString);
  }
  return(NULL);
} // genHtml

?>