#!/usr/bin/php
<?php
/*
 Copyright (C) 2011-2013 Hewlett-Packard Development Company, L.P.

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
 * \brief run the agent functional tests based on the inputfile funcTests.ini
 *
 * Note: this program relys on a utility call createRC.php found in testing/utils.
 * That program is run before this to set up SYSCONFDIR.
 *
 * @version "$Id$"
 * Created on Nov. 4, 2011 by Mark Donohoe
 */


require_once('../lib/bootstrap.php');
require_once('../lib/common-Report.php');
require_once('../lib/common-Test.php');
require_once('../lib/createRC.php');

global $failures;

if ( !defined('TESTROOT') ) {
  $TESTROOT = dirname(getcwd());
  $_ENV['TESTROOT'] = $TESTROOT;
  putenv("TESTROOT=$TESTROOT");
  define('TESTROOT',$TESTROOT);
}

/* when a Jenkins job executes, it sets some environment variables that are 
   available to this script.  $WORKSPACE is set to the absolute path of the
   Jenkins workspace (where the subversion working copy is checked out to) */
$WORKSPACE = NULL;

if ( array_key_exists('WORKSPACE', $_ENV) ) {
  $WORKSPACE = $_ENV['WORKSPACE'];
}

$func = TESTROOT . "/functional";

if ( @chdir($func) === FALSE ) {

  echo "FATAL!, could not cd to:\n$func\n";
  exit(1);

}

createRC();
$sysConf = array();
$sysConf = bootstrap();
putenv("SYSCONFDIR={$GLOBALS['SYSCONFDIR']}");
$_ENV['SYSCONFDIR'] = $GLOBALS['SYSCONFDIR'];

$modules = array();
$funcList = array();

// get the list of functional tests to run
$modules = parse_ini_file('../dataFiles/funcTests.ini',1);
foreach ($modules as $key => $value) {
  $funcList[] = $key;
}

// @todo fix this, I don't think you need to check for workspace.
if ( is_null($WORKSPACE) ) {
  // back to fossology/src
  backToParent('../..');
}
else {
  if (@chdir($WORKSPACE . "/src") === FALSE)
  {
    echo "FATAL! " . __FILE__ . " could not cd to " . $WORKSPACE . "/src\n";
    exit(1);
  }
}

/* store the current working directory, from which each test will begin */
$original_directory = getcwd();

$failures = 0;

foreach ( $funcList as $funcTest ) {

  /* start off each test in the original directory */
  chdir($original_directory);

  echo "\n";
  echo "$funcTest\n";

  /* check the first three characters of the subdirectory for 'lib' or 'cli' */
  $other = substr($funcTest, 0, 3);

  /* for the special case of subdirectories in src/lib/ and src/cli, 
     the tests will be defined in a tests/ subdirectory.  For example:
         src/lib/c/tests/
         src/lib/php/tests/
         cli/tests/  */
  if($other == 'lib' || $other == 'cli') {

    if(@chdir($funcTest . '/tests') === FALSE) {

      echo "Error! cannot cd to " . $funcTest . "/tests, skipping test\n";
      $failures++;
      continue;

    }
  }

  /* for normal agents, the tests will be defined in an agent_tests/Functional
     subdirectory */
  else {

    if(@chdir($funcTest . '/agent_tests/Functional') === FALSE) {

      echo "Error! cannot cd to " . $funcTest . "/agent_tests/Functional, skipping test\n";
      $failures++;
      continue;

    }
  }

  $Make = new RunTest($funcTest);
  $runResults = $Make->MakeTest();
  //debugprint($runResults, "run results for $funcTest\n");

  if($funcTest == 'nomos')
  {
    $diffResult = array();
    foreach ($Make->makeOutput as $makeOutput)
    if((strpos($makeOutput, '< File')!=false) || (strpos($makeOutput, '> File')!=false))
    {
      $diffResult[]  = $makeOutput;
    }
    if(count($diffResult)!=0)
    {
      //echo "Nomos result have " . count($diffResult) . " difference!\n";
      //print_r($diffResult);
      foreach($diffResult as $diff)
        echo substr($diff, strpos($diff, '=> ')+3) . "\n";
      $runResults['nomosfunc'] = count($diffResult);
    }

    /** cp nomos-regression-test.html to ./reports/functional/ */
    exec("cp ./nomos-regression-test.html ".TESTROOT.  "/reports/functional/");
  }
  $Make->printResults($runResults);

  if ( !processXUnit($funcTest) ) {
    echo "Error! could not create html report for $funcTest at\n" .
    __FILE__ . " on " . __LINE__ . "\n";
  }
  continue;

}   // foreach ( $funcList as $funcTest )

if($failures) {
  exit(1);
}

exit(0);

?>
