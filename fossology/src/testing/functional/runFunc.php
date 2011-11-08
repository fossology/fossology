#!/usr/bin/php
<?php
/*
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
 */

/**
 * \brief debug diff env/path when running with exec
 *
 * @version "$Id$"
 * Created on Nov. 4, 2011 by Mark Donohoe
 */

global $failures;

if(!defined('TESTROOT'))
{
  $path = __DIR__;
  $plenth = strlen($path);
  // remove /functional from the end.
  $TESTROOT = substr($path, 0, $plenth-11);
  $_ENV['TESTROOT'] = $TESTROOT;
  putenv("TESTROOT=$TESTROOT");
  define('TESTROOT',$TESTROOT);
}

$WORKSPACE = NULL;

if(array_key_exists('WORKSPACE', $_ENV))
{
  $WORKSPACE = $_ENV['WORKSPACE'];
}

$func = TESTROOT . "/functional";

if(@chdir($func) === FALSE)
{
  echo "FATAL!, could not cd to:\n$func\n";
  exit(1);
}
require_once('../lib/common-Report.php');
require_once('../lib/common-Test.php');

$modules = array();
$funcList = array();

// get the list of functional tests to run
$modules = parse_ini_file('../dataFiles/funcTests.ini',1);
foreach($modules as $key => $value)
{
  $funcList[] = $key;
}

// @todo fix this, I don't think you need to check for workspace.
if(is_null($WORKSPACE))
{
  // back to fossology/src
  backToParent('../..');
}
else
{
  if(@chdir($WORKSPACE . "/fossology/src") === FALSE)
  {
    echo "FATAL! __FILE__ could not cd to " . $WORKSPACE . "/fossology/src\n";
    exit(1);
  }
}

$failures = 0;
foreach($funcList as $funcTest)
{
  echo "\n";
  echo "$funcTest\n";
  $other = substr($funcTest, 0, 3);
  if($other == 'lib' || $other == 'cli')
  {
    if(@chdir($funcTest . '/tests') === FALSE)
    {
      echo "Error! cannot cd to " . $funcTest . "/tests, skipping test\n";
      $failures++;
      continue;
    }
  }
  else
  {
    if(@chdir($funcTest . '/agent_tests/Functional') === FALSE)
    {
      echo "Error! cannot cd to " . $funcTest . "/agent_tests/Functional, skipping test\n";
      $failures++;
      continue;
    }
  }
  $Make = new RunTest($funcTest);
  $runResults = $Make->MakeTest();
  $Make = new RunTest($funcTest);
  $runResults = $Make->MakeTest();
  //debugprint($runResults, "run results for $funcTest\n");
  printResults($runResults);

  if(!processXUnit($funcTest))
  {
    echo "Error! could not create html report for $funcTest at\n" .
    __FILE__ . " on " . __LINE__ . "\n";
  }
  backToParent('../../..');
  continue;
} // for
if($failures)
{
  exit(1);
}
exit(0);

?>