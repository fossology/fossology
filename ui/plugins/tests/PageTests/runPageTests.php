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
 * Runner script to run Page level tests
 */
// set the path for where simpletest is
$path = '/usr/share/php' . PATH_SEPARATOR;
set_include_path(get_include_path() . PATH_SEPARATOR . $path);

/* simpletest includes */
require_once '/usr/local/simpletest/unit_tester.php';
require_once '/usr/local/simpletest/web_tester.php';
require_once '/usr/local/simpletest/reporter.php';

require_once ('../../../../tests/fossologyWebTestCase.php');
require_once ('../../../../tests/TestEnvironment.php');

$test = &new TestSuite('Fossology Repo UI Page Functional tests');
//$test->addTestFile('UploadFileTest.php');
//$test->addTestFile('UploadUrlTest.php');
//$test->addTestFile('UploadSrvTest.php');
//$test->addTestFile('OneShot-lgpl2.1.php');
//$test->addTestFile('OneShot-lgpl2.1-T.php');
//$test->addTestFile('CreateFolderTest.php');
//$test->addTestFile('DeleteFolderTest.php');
//$test->addTestFile('EditFolderTest.php');
//$test->addTestFile('editFolderNameOnlyTest.php');
//$test->addTestFile('editFolderDescriptionOnlyTest.php');
//$test->addTestFile('MoveFolderTest.php');
$test->addTestFile('browseUploadedTest.php');

if (TextReporter::inCli())
{
  exit ($test->run(new TextReporter()) ? 0 : 1);
}
$test->run(new HtmlReporter());


?>