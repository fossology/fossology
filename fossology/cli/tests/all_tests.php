#!/usr/bin/php
<?php
/***********************************************************
 Copyright (C) 2007 Hewlett-Packard Development Company, L.P.

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
 * Wrapper to run all tests
 *
 * @todo figure out where the test archives should be stored and
 * installed.
*/
// Standard requires for any test.
require_once('/usr/local/simpletest/unit_tester.php');
require_once('/usr/local/simpletest/mock_objects.php');
require_once('/usr/local/simpletest/reporter.php');

$command = '/usr/local/bin/test.cp2foss';

$test = &new TestSuite('cp2foss Test Suite');
// parameters tests valid and invalid inputs
//echo "Running Parameter Tests\n";
$test->addTestFile('parameters.php');
//echo "Running -d 'description' Tests\n";
$test->addTestFile('desc.php');
//echo "Running Recursion, directory input Tests\n";
$test->addTestFile('dashR.php');
$test->addTestFile('duplicate-Upfolder.php');
$test->run(new TextReporter());

?>
