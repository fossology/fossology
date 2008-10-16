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
 * @TODO: user specified log file (uses a default one now)
 * @TODO: -n option for 'no cleanup'?
 * @TODO: remove Testing Directory
 *
 */
$usage .= "Usage: $argv[0] [-a] | [-b] | [-h] | [-s] | [-v]\n";
$usage .= "-a: Run all FOSSology Test Suites\n" .
"-b: Run the basic test suite. This runs the SiteTests and any Tests" .
"that don't depend on uploads\n" .
"-h: Display Usage\n" .
"-s: Run SiteTests only (this is a lightweight suite)\n" .
"-v: Run the Verify Tests.  These tests require uploads to be uploaded first." .
"    See the test documentation for details\n";

static $setUp = FALSE;
$errors = 0;
/*
 * process parameters and run the appropriate test suite,
 * redirect  outputs to log file
 */
$date = date('Y-m-d');
$myname = $argv[0];
$SiteTests = '../ui/tests/SiteTests';
$BasicTests = '../ui/tests/BasicTests';
$VerifyTests = '../ui/tests/VerifyTests';

$Home = getcwd();
$pid = getmypid();

$options = getopt('abhsv');
if(empty($options))
{
  print "$usage\n";
  exit (0);
}
if (array_key_exists('h', $options))
{
  print "$usage\n";
  exit (0);
}
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

  if(chdir($Home) === FALSE)
  {
    print "_runSetupVerify ERROR: can't cd to $Home\n";
  }
  $SetupLast = exec("./uploadTestData.php >> /tmp/AllFOSSologyTests-$date 2>&1",$dummy,$SUrtn);
  // need to check the return on the setup and report accordingly.
  if ($SUrtn != 0)
  {
    print "ERROR in Test Setup.  Some or all Verify Tests may fail\n";
    print "Check the file /tmp/AllFOSSologyTests-$date for details\n";
    $errors++;
  }
  if($errors == 0)
  {
    $setUp = TRUE;
    print "Monitor the job Q and when the setup jobs are done, run:\n";
    print "$myname -v\n";
  }
} //_runSetupVerify

// ************ ALL ********************
if (array_key_exists("a", $options))
{
   if(chdir($Home) === FALSE)
  {
    print "All Tests ERROR: can't cd to $Home\n";
  }
  print "Running All Tests\n";
  if(chdir($SiteTests) === FALSE)
  {
    print "ALL Tests ERROR: can't cd to $SiteTests\n";
  }
  $SiteLast = exec("./runSiteTests.php > " .
  "/tmp/AllFOSSologyTests-$date 2>&1", $dummy , $Srtn);
  if(chdir('../BasicTests') === FALSE)
  {
    print "ALL Tests ERROR: can't cd to $BasicTests\n";
  }
  $BasicLast = exec("./runBasicTests.php >> " .
  "/tmp/AllFOSSologyTests-$date 2>&1", $dummy , $Brtn);
  /*
   * The verify tests require that uploads be done first.  The best we
   * can do for now is to run the setup and then tell the tester to run
   * the verify tests after the the setup is done.
   */
  _runSetupVerify();
}

// Basic
if (array_key_exists("b", $options))
{
  if(chdir($Home) === FALSE)
  {
    print "Basic Tests ERROR: can't cd to $Home\n";
  }
  print "Running Basic/SiteTests\n";
  if(chdir($SiteTests) === FALSE)
  {
    print "Basic/Site Tests ERROR: can't cd to $SiteTests\n";
  }
  $SiteLast = exec("./runSiteTests.php > " .
  "/tmp/BasicFOSSologyTests-$date 2>&1", $dummy, $Srtn);

  if(chdir($BasicTests) === FALSE)
  {
    print "Basic Tests ERROR: can't cd to $BasicTests\n";
  }
  $BasicLast = exec("./runBasicTests.php > " .
  "/tmp/BasicFOSSologyTests-$date 2>&1", $dummy, $Srtn);
}
if (array_key_exists("s", $options))
{
  print "Running SiteTests\n";
  if(chdir($SiteTests) === FALSE)
  {
    print "Site Tests ERROR: can't cd to $SiteTests\n";
  }
  $SiteLast = exec("./runSiteTests.php > " .
  "/tmp/SiteFOSSologyTests-$date 2>&1", $dummy, $Srtn);
}

// ******************** Verify ******************************
if (array_key_exists("v", $options))
{
  if(chdir($Home) === FALSE)
  {
    print "Verify Tests ERROR: can't cd to $Home\n";
  }
  if(chdir($VerifyTests) === FALSE)
  {
    print "Verify Tests ERROR: can't cd to $VerifyTests\n";
  }
  $VerifyLast = exec("./runVerifyTests.php >> " .
  "/tmp/VerifyFOSSologyTests-$date 2>&1", $dummy, $Prtn);
}

/*
 * this program should remove the testing folder, which will do a lot of
 * clean up for the tests.
 */
?>