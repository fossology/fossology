#!/usr/bin/php
<?php
/*
 * Runner script that runs the web tests
 */
require_once('/usr/local/simpletest/web_tester.php');
require_once('/usr/local/simpletest/reporter.php');

$test = &new TestSuite('Fossology Repo Web site tests');
$test->addTestFile('foss_ui.php');
exit ($test->run(new TextReporter()) ? 0 : 1);


?>