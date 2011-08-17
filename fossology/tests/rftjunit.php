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
 *
 * \brief Run one or more tests using simpletest.
 *
 * Test output will appear on stdout in junit xml format.  Errors are printed in
 * plain text.
 *
 * @param string -l $list a quoted string with space seperated items
 * @param resource -j $Ref, an object reference from new TestSuite();
 *
  * @version "$Id: $"
 *
 * @todo make -l optional, should be able to just say fo-runTests foo.php and
 * have it run foo.php
 *
 * Created on May 20, 2011
 */

$path = '/usr/local/simpletest' . PATH_SEPARATOR;
set_include_path(get_include_path() . PATH_SEPARATOR . $path);
if (!defined('SIMPLE_TEST'))
define('SIMPLE_TEST', '/usr/local/simpletest/');

/* simpletest includes */
require_once SIMPLE_TEST . 'unit_tester.php';
require_once SIMPLE_TEST . 'reporter.php';
require_once SIMPLE_TEST . 'web_tester.php';
require_once SIMPLE_TEST . 'extensions/junit_xml_reporter.php';

require_once ('TestEnvironment.php');

$Usage =  "Usage: $argv[0] options...
           Options:
             [ {<test-file.php> || a single test or
             [-l 'list of tests'}] a list of tests, space seperated
             [-j 'object-reference']
             To run everything in a directory $argv[0] -l \"`ls`\"
             -j is used by the test harness to combine output with the rest of
             the tests.\n" ;


$options = getopt("j:l:");

/*
 Must have at least 1 argument (the test file to run)
 */
$RunList = array();
$aTest   = NULL;
$suiteRef = NULL;

//print "argc,argv is:$argc\n";print_r($argv) . "\n";
//print "argc is:$argc\n";

if (empty($options)) {
  if ($argc < 2){
    print $Usage;
    exit(1);
  }
}
if($argc >= 2){
  /*
   If first argument does not start with a '-' then it must be a test to run
   */
  $len = strspn($argv[1],'-');
  if(strspn($argv[1],'-') == 0) {
    $aTest = $argv[1];
  }
}
if (array_key_exists("j",$options)) {
  /* split on spaces AND newlines so you can do a -l "`ls`" */
  $suiteRef = $options['j'];
  //print "runx: runlist is:\n"; print_r($RunList) . "\n";
}

if (array_key_exists("l",$options)) {
// split on spaces AND newlines so you can do a -l "`ls`"
  $RunList = preg_split('/\s|\n/',$options['l']);
  //print "runx: runlist is:\n"; print_r($RunList) . "\n";
}

if (!is_null($aTest)) {
  if(file_exists($aTest)) {
    $RunList = array($aTest);
    //print "DB: runlist after assignment of aTest is:\n";print_r($RunList) . "\n";
  }
  else {
    print "Error! File $aTest does not exist!\n";
    exit(1);
  }
}
// if no object pointer passed in, then create one for use in adding the test
if(empty($suiteRef))
{
  $suiteRef = & new TestSuite("Fossology tests");
}

/*
 * tests will run serially in the order supplied.
 *
 * allow filenames without .php or with it
 */
//print "DB: runlist is:\n";print_r($RunList) . "\n";
foreach($RunList as $ptest) {
  if(preg_match('/^.*?\.php/',$ptest)) {
    $test = $ptest;
  }
  else {
    $test = $ptest . ".php";
  }
  $suiteRef->addTestFile("$test");
}

$suiteRef->run(new JUnitXMLReporter());
?>
