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
 * Run one or more FOSSoloyg test suites
 *
 * @version "$Id:  $"
 *
 * Created on Sept. 19, 2008
 */

/**
 * testFossology [-a] | [-b] | [-h] | [-s] | [-p]
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
$usage .= "Usage: $argv[0] [-l path] {[-a] | [-b] | [-h] | [-s] | [-v -l]}\n";
$usage .= "-a: Run all FOSSology Test Suites\n" .
"-b: Run the basic test suite. This runs the SiteTests and any Tests" .
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
}

/******************** Verify ******************************************/
if (array_key_exists("v", $options))
{
  if (array_key_exists("l", $options))
  {
    $logFile = $options['l'];
  } else
  {
    print "Error, must supply a path to a log file with -v option\n";
    print $usage;
    exit (1);
  }

  $VLF = fopen($logFile, 'a');
  $Sstart = "\nRunning Verify Tests on: $date at $time\n";
  LogAndPrint($VLF, $Sstart);

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
  $VerifyLast = exec("./runVerifyTests.php >> $logFile 2>&1", $dummy, $Prtn);
  fclose($VLF);

 if (chdir($Home) === FALSE)
  {
    $noVhome = "Verify Tests ERROR: can't cd to $Home\n";
  }
  $resHome = "/home/fosstester/public_html/TestResults/Data/Latest/";
  $reportHome = "$resHome" . "$logFileName";
  if (!rename($logFile, $reportHome))
  {
    print "Error, could not move\n$logFile\nto\n$reportHome\n";
    print "Please move it by hand so the reports will be current\n";
  }
}

/*
 * this program does not remove the testing folders in case there
 * was a failure and it needs to be looked at.  Run the script/test
 * runTestCleanup.php to clean things up.
 */
?>