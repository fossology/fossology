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
/*
 * Runner script to run Basic tests
 */
// set the path for where simpletest is
$path = '/usr/share/php' . PATH_SEPARATOR;
set_include_path(get_include_path() . PATH_SEPARATOR . $path);

/* simpletest includes */
require_once '/usr/local/simpletest/unit_tester.php';
require_once '/usr/local/simpletest/web_tester.php';
require_once '/usr/local/simpletest/reporter.php';
require_once '/usr/local/simpletest/extensions/junit_xml_reporter.php';

require_once ('TestEnvironment.php');

// Include the lists of tests
require_once('siteTests.php');
require_once('basicTests.php');
require_once('userTests.php');
require_once('emailNoteTests.php');
require_once('pkgAgentTests.php');
//require_once('nomosTests.php');
//require_once('copyrightTests.php');
//require_once('verifyTests.php');

// @todo want to include the svn version in the test report, but it
// doesn't fit into the junit reporter... see if there is way to include it...
//$Svn = `svnversion`;
//print "Using Svn Version:$Svn\n";

global $testSuite;

$paths = array();
$home = getcwd();

$testSuite = &new TestSuite('Fossology UI Functional Tests');

// run createUIUsers first
$testSuite->addTestSuite('createUIUsers.php');

// site
//$sTests = & new TestSuite($siteTests['suiteName']);
$paths['site'] = $siteTests['testPath'];
if (chdir($siteTests['testPath']) === FALSE)
{
  //LogandPrint($LF, "ALL Tests ERROR: can't cd to $SiteTests\n");
  echo "Error! can't cd to {$siteTests['testPath']}\n";
}
addTests($testSuite, $siteTests['tests']);

// basic functional
//$btests = &new TestSuite($basicTests['suiteName']);
if (chdir('../BasicTests') === FALSE)
{
  //LogAndPrint($LF, "ALL Tests ERROR: can't cd to $BasicTests\n");
  echo "ERROR! can't cd to ../BasicTests\n";
}
$paths['basic'] = $basicTests['testPath'];
addTests($testSuite, $basicTests['tests']);

// User tests
if (chdir('../UserTests') === FALSE)
{
  //LogAndPrint($LF, "ALL Tests ERROR: can't cd to $BasicTests\n");
  echo "ERROR! can't cd to ../UserTests\n";
}
$paths['user'] = $userTests['testPath'];
addTests($testSuite, $userTests['tests']);

// Email notificiation tests
if (chdir('../EmailNotification') === FALSE)
{
  //LogAndPrint($LF, "ALL Tests ERROR: can't cd to $BasicTests\n");
  echo "ERROR! can't cd to ../EmailNotification\n";
}
$paths['user'] = $userTests['testPath'];
addTests($testSuite, $userTests['tests']);

//pkgagent
if (chdir($home) === FALSE)
{
  //LogAndPrint($LF, "ALL Tests ERROR: can't cd to $BasicTests\n");
  echo "ERROR! can't cd to $home\n";
}

if (chdir($pkgAgentTests['testPath']) === FALSE)
{
  //LogAndPrint($LF, "ALL Tests ERROR: can't cd to $BasicTests\n");
  echo "ERROR! can't cd to $home\n";
}
$paths['pkgagent'] = $pkgAgentTests['testPath'];
addTests($testSuite, $pkgAgentTests['tests']);

// run uploads
if(!_uploadTestData())
{
  $this->fail("Warning: One or more uploads failed, some verification tests may fail\n");
}
// wait for uploads to finish processing

if (chdir($home) === FALSE) {
  $UInoHome = "All Tests ERROR: can't cd to $Home\n";
  //LogAndPrint($LF, $UInoHome);
  echo $UInoHome;
}
//print "Waiting for jobs to finish...\n";
$last = exec('./wait4jobs.php', $tossme, $jobsDone);
//foreach($tossme as $line){
//  print "$line\n";
//}
//print "testFOSSology: jobsDone is:$jobsDone\n";
if ($jobsDone != 0)
{
  $errMsg = "ERROR! jobs are not finished after two hours, not running" .
    "verify tests, please investigate and run verify tests by hand\n" .
  "Monitor the job Q and when the setup jobs are done, run:\n" .
  "$myname -v -l $logFile\n";
  $this->fail($errMsg);
  exit(1);
}
if ($jobsDone == 0)
{
  if (chdir($home) === FALSE)
  {
    $cUInoHome = "All Tests ERROR: can't cd to $Home\n";
    //LogAndPrint($LF, $cUInoHome);
    echo $cUInoHome;
  }
}

// nomos
// copyright
// ?? where do new user access tests go?
// run verify

// note the above list does not include the phpunit tests which are trivial
// at this point.
// @todo add in phpunit tests

$testSuite->run(new JUnitXMLReporter());

// transform the report into html....

/**
 * /brief using the suite reference, add the tests in the testArray
 *
 * @param reference $suite, an object refrence to the TestSuite
 * @param array $testArray
 *
 */
function addTests($suite, $testArray)
{
  if(!is_array($testArray))
  {
    return;
  }
  foreach($testArray as $test)
  {
    //echo "test is:$test\n";
    $suite->addTestFile($test);
  }
} //addTests

/**
 * _uploadTestData()
 * \brief upload the test data files needed to for the functional tests
 * for the agents, nomos, copyright, and pkgagent verification tests.
 *
 * @return boolean
 *
 */
function _uploadTestData() {

  global $date;
  global $myname;
  global $home;
  global $logFile;
  global $LF;
  global $testSuite;

  $copyrOut = array();
  $agentAddOut = array();

  $errors = 0;
  if (chdir($home) === FALSE)
  {
    //LogAndPrint($LF, "_runTestEnvSetup ERROR: can't cd to $Home\n");
    echo "_uploadTestData ERROR! can't cd to $home\n";
    return(FALSE);
  }
  //LogAndPrint($LF, "\n");
  //$UpLast = exec("./uploadTestData.php  2>&1", $dummy, $SUrtn);
  $UpLast = system("./rftjunit.php  -l uplTestData.php -j $testSuite 2>&1", $SUrtn);
  //LogAndPrint($LF, "\n");
  //$UpLast = exec("./rftjunit.php  -l uploadCopyrightData.php  2>&1", $copyrOut, $Copyrtn);
  //$AALast = exec("./rftjunit.php -l AgentAddData.php -n  2>&1", $agentAddOut, $AArtn);
  $CrLast = system("./rftjunit.php  -l uploadCopyrightData.php  -j $testSuite 2>&1",$Copyrtn);
  $AALast = system("./rftjunit.php -l AgentAddData.php -j $testSuite  2>&1", $AArtn);
  //LogAndPrint($LF, "\n");
  // need to check the return on the setup and report accordingly.
  if ($SUrtn != 0) {
    //LogAndPrint($LF, "ERROR when running uploadTestData.php\n");
    echo "ERROR when running uploadTestData.php\n";
    echo $UpLast;
    $errors++;
  }
  if ($Copyrtn != 0) {
    //LogAndPrint($LF, "ERROR when running uploadCopyrightData.php\n");
    echo "ERROR when running uploadCopyrightData.php\n";
    echo $CrLast;
    $errors++;
  }
  if ($AArtn != 0) {
    //LogAndPrint($LF, "ERROR when running AgentAddData.php\n");
    echo "ERROR when running AgentAddData.php\n";
    echo $AALast;
    $errors++;
  }
  if ($errors != 0) {
    //print "Warning! There were errors in the test setup, one or more test may fail as a result\n";
    return(FALSE);
  }
  return(TRUE);
} //_uploadTestData
?>