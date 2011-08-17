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
$test = &new TestSuite('Fossology Test Clean Up');
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
?>
