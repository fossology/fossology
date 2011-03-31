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
 * Runner script that runs the web tests
 */
// set the path for where simpletest is
$path = '/usr/share/php' . PATH_SEPARATOR;
set_include_path(get_include_path() . PATH_SEPARATOR . $path);

/* simpletest includes */
require_once '/usr/local/simpletest/unit_tester.php';
require_once '/usr/local/simpletest/web_tester.php';
require_once '/usr/local/simpletest/reporter.php';

require_once('../../../tests/TestEnvironment.php');
require_once('../../../tests/testClasses/timer.php');

global $URL;
global $USER;
global $PASSWORD;

$start = new timer();
$Svn = `svnversion`;
$date = date('Y-m-d');
$time = date('h:i:s-a');
print "\nStarting Site Tests on: " . $date . " at " . $time . "\n";
print "Using Svn Version:$Svn\n";
$test = &new TestSuite('Fossology Repo Site UI tests');
$test->addTestFile('AboutMenuTest.php');
$test->addTestFile('login.php');
$test->addTestFile('SearchMenuTest.php');
$test->addTestFile('OrgFoldersMenuTest-Create.php');
$test->addTestFile('OrgFoldersMenuTest-Delete.php');
$test->addTestFile('OrgFoldersMenuTest-Edit.php');
$test->addTestFile('OrgFoldersMenuTest-Move.php');
$test->addTestFile('OrgUploadsMenuTest-Delete.php');
$test->addTestFile('OrgUploadsMenuTest-Move.php');
$test->addTestFile('UploadInstructMenuTest.php');
$test->addTestFile('UploadFileMenuTest.php');
$test->addTestFile('UploadServerMenuTest.php');
$test->addTestFile('UploadUrlMenuTest.php');
$test->addTestFile('UploadOne-ShotMenuTest.php');
if (TextReporter::inCli())
{
  $results = $test->run(new TextReporter()) ? 0 : 1;
  print "Ending Site Tests at: " . date('r') . "\n";
  $elapseTime = $start->TimeAgo($start->getStartTime());
  print "The Site Tests took {$elapseTime}to run\n\n";
  exit($results);
}
$test->run(new HtmlReporter());
$elapseTime = $start->TimeAgo($start->getStartTime());
print "<pre>The Site Tests took {$elapseTime}to run</pre>\n\n";
?>