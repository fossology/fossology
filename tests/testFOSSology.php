#!/usr/bin/php
<?php

/***********************************************************
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
 ***********************************************************/

/**
 * testFOSSology
 *
 * Run one or more FOSSology test suites
 *
 * @version "$Id:  $"
 *
 * Created on Sept. 19, 2008
 */

/**
 * testFossology [-l path] {[-a] | [-b] | [-h] | [-s] | [-v -l]
 *
 * Run one or more FOSSology Test Suites
 * -a: run all tests suites.  -a overrides all other switches supplied.
 * -b: run the basic test suite. This runs the SiteTests and any
 * PageTests that don't depend on uploads
 * -s: run SiteTests only (this is a lightweight suite)
 * -p: run PageTests only. PageTests are the UI functional tests
 *
 * @param string either -a, -b, -s or -p
 *
 * @TODO: check for a initial / in the logfile path name? to guard
 * against the issue I ran into with it not writting to the correct
 * logfile.
 * @TODO: -c option for cleanup
 *
 */

require_once('testClasses/check4jobs.php');

$usage .= "Usage: $argv[0] [-l path] {[-a] | [-b] | [-h] | [-s] | [-v -l]}\n";
$usage .= "-a: Run all FOSSology Test Suites\n" .
"-b: Run the basic test suite. This runs the SiteTests and any Tests " .
"that don't depend on uploads\n" .
"-h: Display Usage\n" .
"-l path: test results file path \n" .
"-s: Run SiteTests only (this is a lightweight suite)\n" .
"-v -l: Run the Verify Tests.  These tests require uploads to be uploaded first." .
"    See the test documentation for details\n" .
"    You must specify a log file when using -v, best to use the same log file" .
"    that was used in a previous run for example -a -l foo, then -v -l foo when" .
"    all the files have been uploaded with -a.";

global $logFile;
global $logFileName;
global $LF;

/*
 * process parameters and run the appropriate test suite,
 * redirect  outputs to log file
 */
$setUp = FALSE;
$errors = 0;
$date = date('Y-m-d');
$time = date('h:i:s-a');
$defaultLog = "/FossTestResults-$date-$time";
$myname = $argv[0];
$SiteTests = '../ui/tests/SiteTests';
$BasicTests = '../ui/tests/BasicTests';
$VerifyTests = '../ui/tests/VerifyTests';

$Home = getcwd();
$pid = getmypid();

$options = getopt('abhl:sv');
if (empty ($options))
{
  print "$usage\n";
  exit (0);
}
if (array_key_exists('h', $options))
{
  print "$usage\n";
  exit (0);
}
if (array_key_exists('l', $options))
{
  $logFile = $options['l'];
  $logFileName = basename($logFile);
} else
{
  // Default Log file, use full path to it
  $cwd = getcwd();
  $logFile = $cwd . $defaultLog;
  //$logFileName = $defaultLog;
  $logFileName = basename($logFile);
}

//$LF = fopen($logFile, 'w') or die("can't open $logFile $phperrormsg\n");
//print "Using log file:$logFile\n";

/**
 * _runSetupPage()
 *
 * private helper function Sets the static variable $setUp if the test
 * setups ran without error.
 *
 */
function _runSetupVerify()
{
  global $date;
  global $myname;
  global $Home;
  global $logFile;
  global $LF;

  if (chdir($Home) === FALSE)
  {
    LogAndPrint($LF, "_runSetupVerify ERROR: can't cd to $Home\n");
  }
  print "\n";
  $SetupLast = exec("./uploadTestData.php >> $logFile 2>&1", $dummy, $SUrtn);
  // need to check the return on the setup and report accordingly.
  if ($SUrtn != 0)
  {
    LogAndPrint($LF, "ERROR in Test Setup.  Some or all Verify Tests may fail\n");
    LogAndPrint($LF, "Check the file $logFile for details\n");
    $errors++;
  }
  if ($errors == 0)
  {
    $setUp = TRUE;
    print "Monitor the job Q and when the setup jobs are done, run:\n";
    print "$myname -v -l $logFile\n";
  }
} //_runSetupVerify

function getSvnVer()
{
  return (`svnversion`);
}

function LogAndPrint($FileHandle, $message)
{
  if (empty ($message))
  {
    return (FALSE);
  }
  if (empty ($FileHandle))
  {
    return (FALSE);
  }
  if (-1 == fwrite($FileHandle, $message))
  {
    print $message;            // if we don't do this nothing will print
    return (FALSE);
  }
  print $message;
  return (TRUE);
}

$Svn = getSvnVer();

/************* ALL Tests **********************************************/
if (array_key_exists("a", $options))
{
  $LF = fopen($logFile, 'w') or die("can't open $logFile $phperrormsg\n");
  print "Using log file:$logFile\n";

  if (chdir($Home) === FALSE)
  {
    LogAndPrint($LF, "All Tests ERROR: can't cd to $Home\n");
  }
  LogAndPrint($LF,
    "Running All Tests on: $date at $time using subversion version: $Svn\n");

  if (chdir($SiteTests) === FALSE)
  {
    LogandPrint($LF, "ALL Tests ERROR: can't cd to $SiteTests\n");
  }
  $SiteLast = exec("./runSiteTests.php >> $logFile 2>&1", $dummy, $Srtn);
  if (chdir('../BasicTests') === FALSE)
  {
    LogAndPrint($LF, "ALL Tests ERROR: can't cd to $BasicTests\n");
  }
  $BasicLast = exec("./runBasicTests.php >> $logFile 2>&1", $dummy, $Brtn);
  /*
   * The verify tests require that uploads be done first.  The best we
   * can do for now is to run the setup and then tell the tester to run
   * the verify tests after the the setup is done.
   */
  _runSetupVerify();
  fclose($LF);

  // wait for tests to finish

  $last = exec('./wait4jobs.php',$tossme, $jobsDone);
  if($jobDone != 0){
    print "ERROR! jobs are not finished after two hours, not running" .
          "verify tests, please investigate and run verify tests by hand\n";
    exit(1);
  }
  if($jobDone == 1){
    verifyUploads($logFile);
  }

  if(!is_null($rtn = saveResults())){
    print "ERROR! could not save the test results, please save by hand\n";
    exit(1);
  }
  exit(0);
}

/**************** Basic Tests (includes Site) *************************/
if (array_key_exists("b", $options))
{
  $LF = fopen($logFile, 'w') or die("can't open $logFile $phperrormsg\n");
  print "Using log file:$logFile\n";

  if (chdir($Home) === FALSE)
  {
    $BnoHome = "Basic Tests ERROR: can't cd to $Home\n";
    LogAndPrint($LF, $BnoHome);
  }
  $startB = "Running Basic/SiteTests on: $date at $time\n";
  LogAndPrint($LF, $startB);

  if (chdir($SiteTests) === FALSE)
  {
    $noBS = "Basic/Site Tests ERROR: can't cd to $SiteTests\n";
    LogAndPrint($LF, $noBS);
  }
  print "\n";
  $SiteLast = exec("./runSiteTests.php >> $logFile 2>&1", $dummy, $Srtn);

  if (chdir('../BasicTests') === FALSE)
  {
    $noBT = "Basic Tests ERROR: can't cd to $BasicTests\n";
    LogAndPrint($LF, $noBT);
  }
  print "\n";
  $BasicLast = exec("./runBasicTests.php >> $logFile 2>&1", $dummy, $Srtn);
  fclose($LF);
  exit(0);
}

/***************** SiteTest Only **************************************/
if (array_key_exists("s", $options))
{
  $LF = fopen($logFile, 'w') or die("can't open $logFile $phperrormsg\n");
  print "Using log file:$logFile\n";

  $Sstart = "Running SiteTests on: $date at $time\n";
  LogAndPrint($LF, $Sstart);

  if (chdir($SiteTests) === FALSE)
  {
    $noST = "Site Tests ERROR: can't cd to $SiteTests\n";
    LogAndPrint($LF, $noST);
  }
  $SiteLast = exec("./runSiteTests.php >> $logFile 2>&1", $dummy, $Srtn);
  fclose($LF);
  exit(0);
}

/******************** Verify ******************************************/

if (array_key_exists("v", $options)){
  if (array_key_exists("l", $options)){
    $logFile = $options['l'];
    print "Verify: logFile is:$logFile\n";
    $logFileName = basename($logFile);
    print "Verify: logFileName is:$logFileName\n";
  }
  else{
    print "Error, must supply a path to a log file with -v option\n";
    print $usage;
    exit (1);
  }

  if(!verifyUploads($logFile)){
    print "ERROR! could not verify upload tests, please investigate\n";
    exit(1);
  }

  if(!is_null($rtn = saveResults())){
    print "ERROR! could not save the test results, please save by hand\n";
    exit(1);
  }
  exit(0);
}

function saveResults(){

  global $Home;
  global $logFileName;
  global $LF;
  global $logFile;

  $resultsHome = "/home/fosstester/public_html/TestResults/Data/Latest/";

  if (chdir($Home) === FALSE){
    $nohome = "Save Data ERROR: can't cd to $Home\n";
    LogAndPrint($LF, $nohome);
    return($nohome);
  }
  print "saveResults: logFileName is:$logFileName\n";
  print "saveResults: resultsHome is:$resultsHome\n";
  $reportHome = "$resultsHome" . "$logFileName";
  if (!rename($logFile, $reportHome)){
    $E = "Error, could not move\n$logFile\nto\n$reportHome\n";
    $E .= "Please move it by hand so the reports will be current\n";
    return($E);
  }
  return(NULL);
}

function verifyUploads($logfile) {

  global $Home;
  global $VerifyTests;
  global $date;
  global $time;

  if(empty($logfile)){
    return(FALSE);
  }

  $VLF = fopen($logfile, 'a') or die("Can't open $logfile, $phperrormsg");
  $Vstart = "\nRunning Verify Tests on: $date at $time\n";
  LogAndPrint($VLF, $Vstart);

  if (chdir($Home) === FALSE)
  {
    $noVhome = "Verify Tests ERROR: can't cd to $Home\n";
    LogAndPrint($VLF, $noVhome);
  }
  if (chdir($VerifyTests) === FALSE)
  {
    $noVT = "Verify Tests ERROR: can't cd to $VerifyTests\n";
    LogAndPrint($VLF, $noVT);
  }
  $VerifyLast = exec("./runVerifyTests.php >> $logfile 2>&1", $dummy, $Prtn);
  fclose($VLF);
  return(TRUE);
}

/*
 * this program does not remove the testing folders in case there
 * was a failure and it needs to be looked at.  Run the script/test
 * runTestCleanup.php to clean things up.
 */
?>