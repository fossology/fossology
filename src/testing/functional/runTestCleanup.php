#!/usr/bin/php
<?php
/*
 SPDX-FileCopyrightText: © 2008 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * run the test clean up script
 *
 * @param URL obtained from the test enviroment globals
 *
 * @version "$Id: runTestCleanup.php 2654 2009-11-24 01:03:56Z rrando $"
 *
 * Created on Dec. 10, 2008
 */

require_once '/usr/local/simpletest/web_tester.php';
require_once '/usr/local/simpletest/reporter.php';
require_once ('TestEnvironment.php');
require_once('testClasses/timer.php');

global $URL;
$start = new timer();
$Svn = `svnversion`;
$date = date('Y-m-d');
$time = date('h.i.s-a');
print "Starting Cleanup Tests on: " . $date . " at " . $time . "\n";
print "Using Svn Version:$Svn\n";
$test = new TestSuite('Fossology Test Clean Up');
$test->addTestFile('testCleanUp.php');

if (TextReporter::inCli())
{
  $results = $test->run(new TextReporter()) ? 0 : 1;
  print "Ending Clean Up Tests at: " . date('r') . "\n";
  $elapseTime = $start->TimeAgo($start->getStartTime());
  print "The Clean Up Tests took {$elapseTime}to run\n";
  exit ($results);
}
$test->run(new HtmlReporter());
print "<pre>Ending Clean Up at: " . date('r') . "</pre>\n";
$elapseTime = $start->TimeAgo($start->getStartTime());
print "<pre>The Clean Up Tests took {$elapseTime}to run</pre>\n";
