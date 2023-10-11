#!/usr/bin/php
<?php
/*
 SPDX-FileCopyrightText: © 2008 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * Simpletest run script template
 *
 * Run a test using simpletest, useful when working on a new test.
 *
 * @version "$Id: runtemplate.php 1657 2008-11-11 19:11:50Z rrando $"
 *
 * Created on Aug 27, 2008
 */

$path = '/usr/local/simpletest' . PATH_SEPARATOR;
set_include_path(get_include_path() . PATH_SEPARATOR . $path);
if (!defined('SIMPLE_TEST'))
  define('SIMPLE_TEST', '/usr/local/simpletest/');

/* simpletest includes */
require_once SIMPLE_TEST . 'unit_tester.php';
require_once SIMPLE_TEST . 'reporter.php';
require_once SIMPLE_TEST . 'web_tester.php';

require_once('TestEnvironment.php');
// adjust the path below as needed, the path below assumes ..fossology/tests
require_once('testClasses/timer.php');

/* replace the TestSuite string with one that describes what the test suite is */
$start = new timer();
print "Starting xxxx Tests at: " . date('r') . "\n";
$test = new TestSuite("Run Fossology tests");
/*
 * To run a test use addTestFile method. as many tests as needed can be run this way.
 * Just keep adding more $test->addTestFile(sometest) lines to this
 * file for each new test.
 */
$test->addTestFile('atest');
/*
 * edit the print statements as needed, but leave the code below alone,
 * it allows the tests to be run either by the cli or in a web browser
 */
 $elapseTime = $start->TimeAgo($start->getStartTime());

if (TextReporter :: inCli())
{
  $results = $test->run(new TextReporter()) ? 0 : 1;
  print "Ending xxx Tests at: " . date('r') . "\n";
  print "The xxxx Tests took {$elapseTime}to run\n";
  exit($results);
}
$test->run(new HtmlReporter());
$elapseTime = $start->TimeAgo($start->getStartTime());
print "<pre>The xxxx Tests took {$elapseTime}to run</pre>\n";
