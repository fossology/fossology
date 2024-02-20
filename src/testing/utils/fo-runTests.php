#!/usr/bin/php
<?php
/*
 SPDX-FileCopyrightText: © 2008 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * run a test
 *
 * Run one or more tests using simpletest
 *
 * @param string -l $list a quoted string with space seperated items
 *
 * @return the test results, passes and failure.
 *
 * @version "$Id: fo-runTests.php 3491 2010-09-24 18:30:46Z rrando $"
 *
 * @todo make -l optional, should be able to just say fo-runTests foo.php and
 * have it run foo.php
 *
 * @todo add a -n suite-name parameter, optional, if none given and we have
 * an argument, then use the argument as the name, nothing?  Then use the
 * string 'Generic FOSSology Test Suite'.
 *
 * @todo always print the starting message with the date and time, bonus is
 * svn rev. (pass that in?) so the results parse correctly.
 *
 * Created on March 18, 2009
 */

$path = '/usr/local/simpletest' . PATH_SEPARATOR;
set_include_path(get_include_path() . PATH_SEPARATOR . $path);
if (!defined('SIMPLE_TEST'))
define('SIMPLE_TEST', '/usr/local/simpletest/');

/* simpletest includes */
require_once SIMPLE_TEST . 'unit_tester.php';
require_once SIMPLE_TEST . 'reporter.php';
require_once SIMPLE_TEST . 'web_tester.php';

require_once ('TestEnvironment.php');
if(defined('TESTROOT'))
{
  echo TESTROOT . "\n";
  require_once(TESTROOT . '/testClasses/timer.php');
}
else
{
  echo "ERROR! cannot load /testClasses/timer.php, is TESTROOT defined?\n";
  exit(1);
}

$Usage =  "Usage: $argv[0] options...
           Options:
             [ {<test-file.php> || a single test or
             [-l 'list of tests'}] a list of tests, space seperated
             [ -n <suite-name>]    optional test suite name
             [ -t 'A Title']       optional title\n
             To run everything in a directory $argv[0] -l \"`ls`\"\n" ;

/*
 * NOTE on parameters, can't guess a suite name from a list, so
 * only get a suite name if -n is used or no -a and a single test to run.
 */

/*
 "$argv[0] -l 'list of tests space seperated'\n or\n" .
 "$argv[0] -l \"`ls`\" to run everything in the directory\n".
 "\n$argv[0] -t 'Title' to supply an optional title\n";
 */

$options = getopt("l:n:t:");

/*
 Must have at least 1 argument (the test file to run)
 */
$RunList = array();
$aTest   = NULL;
$suite   = NULL;

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
if (array_key_exists("l",$options)) {
  /* split on spaces AND newlines so you can do a -l "`ls`" */
  $RunList = preg_split('/\s|\n/',$options['l']);
  //print "runx: runlist is:\n"; print_r($RunList) . "\n";
}
if (array_key_exists("n",$options)) {
  $suite = $options['n'];
  //print "DB: suite is:$suite\n";
}
// no suite specified
if (!is_null($aTest)) {
  if(file_exists($aTest)) {
    $suite = $aTest;
    $RunList = array($aTest);
    //print "DB: runlist after assignment of aTest is:\n";print_r($RunList) . "\n";
  }
  else {
    print "Error! File $aTest does not exist!\n";
    exit(1);
  }
}
// default name
if(is_null($suite)) {
  $suite = 'Generic FOSSology Test Suite';
}
$Title = NULL;
if (array_key_exists("t",$options)) {
  $Title = $options['t'];
  //print "DB: Title is:$Title\n";
}

//$Svn = `svnversion`;
$start = new timer();
$date = date('Y-m-d');
$time = date('h:i:s-a');
print "Starting $suite on: " . $date . " at " . $time . "\n";
//print "Using Svn Version:$Svn\n";
$Runtest = new TestSuite("Fossology tests $Title");
/*
 * tests will run serially...
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
  $Runtest->addTestFile("$test");
}

/*
 * leave the code below alone, it allows the tests to be run either by
 * the cli or in a web browser
 */

if (TextReporter::inCli()) {
  $results = $Runtest->run(new TextReporter()) ? 0 : 1;
  print "Ending $suite at: " . date('r') . "\n";
  $elapseTime = $start->TimeAgo($start->getStartTime());
  print "The suite $suite took {$elapseTime}to run\n\n";
  exit($results);
}

$Runtest->run(new HtmlReporter());
print "<pre>Ending $suite at: " . date('r') . "</pre>\n";
$elapseTime = $start->TimeAgo($start->getStartTime());
print "<pre>The suite $suite took {$elapseTime}to run</pre>\n";
