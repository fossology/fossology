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
$u .= "Usage: $argv[0] [-a] | [-b] | [-h] | [-s] | [-p] | [-v]\n";
$u .= "-a: Run all FOSSology Test Suites\n" .
"-b: Run the basic test suite. This runs the SiteTests and any PageTests" .
"that don't depend on uploads\n" .
"-h: Display Usage\n" .
"-s: Run SiteTests only (this is a lightweight suite)" .
"-p: Run fix this!...... are the UI functional tests, they " .
"require files to uploaded before they are run, see the test documentation" .
"for details\n";
$usage = $u;

static $setUp = FALSE;
$errors = 0;
/*
 * process parameters and run the appropriate test suite,
 * redirect  outputs to log file
 */
$date = date('Y-m-d');
$myname = $argv[0];
$SiteTests = '../ui/plugins/tests/SiteTests';
$PageTests = '../ui/plugins/tests/PageTests';
$VerifTests = '../ui/plugins/tests/VerifyTests';
$Home = getcwd();

$options = getopt('abhsp');
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
function _runSetupPage()
{
  global $date;
  global $myname;
  global $Home;

  if(chdir($Home) === FALSE)
  {
    print "_runSetupPage ERROR: can't cd to $Home\n";
  }
  $SetupLast = exec("./uploadTestData.php >> /tmp/AllFOSSologyTests-$date 2>&1",$dummy,$SUrtn);
  // need to check the return on the setup and report accordingly.
  if ($SUrtn != 0)
  {
    print "ERROR in Test Setup.  Some or all Page Tests may fail\n";
    print "Check the file /tmp/SetUpFOSSologyTests-$date for details\n";
    $errors++;
  }
  if($errors == 0)
  {
    $setUp = TRUE;
    print "Monitor the job Q and when the setup jobs are done, run:\n";
    print "$myname -v\n";
  }
} //_runSetupPage

// ************ ALL ********************
if (array_key_exists("a", $options))
{
  print "Running All Tests\n";
  if(chdir($SiteTests) === FALSE)
  {
    print "ALL Tests ERROR: can't cd to $SiteTests\n";
  }
  $SiteLast = exec("./runSiteTests.php > " .
  "/tmp/AllFOSSologyTests-$date 2>&1", $dummy , $Srtn);
  /*
   * The page tests require that uploads be done first.  The best we
   * can do for now is to run the setup and then tell the tester to
   * run the page tests after the the setup is done.
   */
  _runSetupPage();
}

// Basic
if (array_key_exists("b", $options))
{
  print "Sorry, basic tests not yet operational\n";
  exit (0);
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

// ******************** Page *******************************************
if (array_key_exists("p", $options))
{
  print "Running PageTests\n";
  if(!$setUp)
  {
    print "Setup for the Pages tests does not appear to have been run\n";
    print "running....\n";
    _runSetupPage();
  }
  if(chdir($PageTests) === FALSE)
  {
    print "Page Tests ERROR: can't cd to $PageTests\n";
  }
  $PageLast = exec("./runPageTests.php >> " .
  "/tmp/PageFOSSologyTests-$date 2>&1", $dummy, $Prtn);
}

//Verify
if (array_key_exists("v", $options))
{
  print "would run:\n";
  print "./runVerifyTests.php >> " .
  "/tmp/VerifyFOSSologyTests-$date 2>&1, '', $Prtn);";
  if(chdir($VerifyTests) === FALSE)
  {
    print "ERROR: can't cd to $VerifyTests\n";
  }
  //$VerifyLast = exec("./runVerifyTests.php >> " .
  //"/tmp/VerifyFOSSologyTests-$date 2>&1", $dummy, $Prtn);
}

/*
 * this program should remove the testing folder, which will do a lot of
 * clean up for the tests.
 */
?>