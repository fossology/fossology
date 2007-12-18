#!/usr/bin/php
<?php

/**
 * Wrapper to run all tests
*/
// Standard requires for any test. 
require_once('/usr/local/simpletest/unit_tester.php');
require_once('/usr/local/simpletest/reporter.php');

$test = &new TestSuite('All CLI tests');
$test->addTestFile('1stest.php');
$test->run(new TextReporter());

?>
