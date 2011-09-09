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
 * Runner script to run Basic tests
 */
// set the path for where simpletest is
$path = '/usr/share/php' . PATH_SEPARATOR;
set_include_path(get_include_path() . PATH_SEPARATOR . $path);

/* simpletest includes */
require_once '/usr/local/simpletest/reporter.php';

require_once ('../../../tests/TestEnvironment.php');
require_once('../../../tests/testClasses/timer.php');

$start = new timer();
$Svn = `svnversion`;
$date = date('Y-m-d');
$time = date('h:i:s-a');
print "\nStarting Basic Functional Tests on: " . $date . " at " . $time . "\n";
print "Using Svn Version:$Svn\n";
$test = new TestSuite('Fossology Repo UI Basic Functional tests');
// Must run BasicSetup first, it creates the folder the other tests need.
$test->addFile('BasicSetup.php');
$test->addFile('UploadFileTest.php');
$test->addFile('UploadUrlTest.php');
$test->addFile('UploadSrvArchiveTest.php');
$test->addFile('uploadSrvDirTest.php');
$test->addFile('uploadSrvFileTest.php');
$test->addFile('CreateFolderTest.php');
$test->addFile('DeleteFolderTest.php');
$test->addFile('editFolderTest.php');
$test->addFile('editFolderNameOnlyTest.php');
$test->addFile('editFolderDescriptionOnlyTest.php');
$test->addFile('moveFolderTest.php');
$test->addFile('DupFolderTest.php');
$test->addFile('DupUploadTest.php');
$test->addFile('createFldrDeleteItTest.php');

if (TextReporter::inCli())
{
  $results = $test->run(new TextReporter()) ? 0 : 1;
  print "Ending Basic Tests at: " . date('r') . "\n";
  $elapseTime = $start->TimeAgo($start->getStartTime());
  print "The Basic Tests took {$elapseTime}to run\n\n";
  exit($results);
}
$test->run(new HtmlReporter());
$elapseTime = $start->TimeAgo($start->getStartTime());
print "<pre>The Basic Functional Tests took {$elapseTime}to run</pre>\n\n";
?>