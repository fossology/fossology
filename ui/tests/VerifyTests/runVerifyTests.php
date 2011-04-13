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
/*
 * Runner script to run Verify tests
 */
// set the path for where simpletest is
$path = '/usr/share/php' . PATH_SEPARATOR;
set_include_path(get_include_path() . PATH_SEPARATOR . $path);

/* simpletest includes */
require_once '/usr/local/simpletest/web_tester.php';
require_once '/usr/local/simpletest/reporter.php';

//require_once ('../../../tests/fossologyTestCase.php');
require_once ('../../../tests/TestEnvironment.php');
require_once('../../../tests/testClasses/timer.php');

$Svn = `svnversion`;
$start = new timer();
$date = date('Y-m-d');
$time = date('h:i:s-a');
print "\nStarting Verify Tests on: " . $date . " at " . $time . "\n";
print "Using Svn Version:$Svn\n";
$test = &new TestSuite('Fossology Repo UI Verification Functional tests');
//$test->addTestFile('browseUploadedTest.php');
$test->addTestFile('OneShot-lgpl2.1.php');
$test->addTestFile('OneShot-lgpl2.1-T.php');
$test->addTestFile('verifyFossI16L335U29.php');
$test->addTestFile('verifyFoss23D1F1L.php');
$test->addTestFile('verifyFossDirsOnly.php');

if (TextReporter::inCli())
{
  $results = $test->run(new TextReporter()) ? 0 : 1;
  print "Ending Verify Tests at: " . date('r') . "\n";
  $elapseTime = $start->TimeAgo($start->getStartTime());
  print "The Verify Tests took {$elapseTime}to run\n\n";
  exit($results);
}
$test->run(new HtmlReporter());
print "<pre>Ending Verify Tests at: " . date('r') . "</pre>\n";
$elapseTime = $start->TimeAgo($start->getStartTime());
print "<pre>The Verify Tests took {$elapseTime}to run</pre>\n\n";
?>