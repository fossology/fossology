#!/usr/bin/php
<?php
/*
 SPDX-FileCopyrightText: © 2007 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

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

$test = new TestSuite('cp2foss Test Suite');
// parameters tests valid and invalid inputs
//echo "Running Parameter Tests\n";
$test->addTestFile('parameters.php');
//echo "Running -d 'description' Tests\n";
$test->addTestFile('desc.php');
//echo "Running Recursion, directory input Tests\n";
$test->addTestFile('dashR.php');
$test->addTestFile('duplicate-Upfolder.php');
$test->run(new TextReporter());


