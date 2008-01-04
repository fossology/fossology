#!/usr/bin/php
<?php

/**
 * Wrapper to run all tests
 * 
 * @todo figure out where the test archives should be stored and 
 * installed.
*/
// Standard requires for any test. 
require_once('/usr/local/simpletest/unit_tester.php');
require_once('/usr/local/simpletest/reporter.php');

$test = &new TestSuite('All tests for cp2foss');
// parameters tests valid and invalid inputs
$test->addTestFile('parameters.php');
$test->addTestFile('desc.php');
$test->run(new TextReporter());

?>
