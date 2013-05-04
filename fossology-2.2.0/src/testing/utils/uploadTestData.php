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
 * Upload Test data to the repo
 *
 * Uses the simpletest framework, this way it doesn't matter where the
 * repo is, it will get uploaded, and this is another set of tests.
 *
 * @param URL obtained from the test enviroment globals
 *
 * @version "$Id: uploadTestData.php 1738 2008-12-03 02:31:24Z rrando $"
 *
 * Created on Aug 15, 2008
 */

/* Upload the following files from the fosstester home directory:
 * - simpletest_1.0.1.tar.gz
 * - gplv2.1
 * - Affero-v1.0
 * - http://www.gnu.org/licenses/gpl-3.0.txt
 * - http://www.gnu.org/licenses/agpl-3.0.txt
 */


require_once '/usr/local/simpletest/unit_tester.php';
require_once '/usr/local/simpletest/web_tester.php';
require_once '/usr/local/simpletest/reporter.php';
require_once ('TestEnvironment.php');
require_once('testClasses/timer.php');

global $URL;
$start = new timer();
$Svn = `svnversion`;
$date = date('Y-m-d');
$time = date('h:i:s-a');
print "Starting Upload-Prep Tests on: " . $date . " at " . $time . "\n";
print "Using Svn Version:$Svn\n";
$test = &new TestSuite('Fossology Repo UI Upload-Prep Test');
$test->addTestFile('uplTestData.php');

if (TextReporter::inCli())
{
  $results = $test->run(new TextReporter()) ? 0 : 1;
  print "Ending Upload-Prep Tests at: " . date('r') . "\n";
  $elapseTime = $start->TimeAgo($start->getStartTime());
  print "The Upload-Prep Tests took {$elapseTime}to run\n";
  exit ($results);
}
$test->run(new HtmlReporter());
print "<pre>Ending Upload-Prep at: " . date('r') . "</pre>\n";
$elapseTime = $start->TimeAgo($start->getStartTime());
print "<pre>The Upload-Prep Tests took {$elapseTime}to run</pre>\n";
?>
