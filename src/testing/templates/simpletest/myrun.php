#!/usr/bin/php
<?php
/*
 SPDX-FileCopyrightText: © 2008 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * Simpletest run script template
 *
 * Run a test using simpletest, useful when working on a new test.
 *
 * @version "$Id: myrun.php 2017 2009-04-25 03:02:01Z rrando $"
 *
 * Created on Aug 27, 2008
 */

$path = '/usr/local/simpletest' . PATH_SEPARATOR;
set_include_path(get_include_path() . PATH_SEPARATOR . $path);
if (!defined('SIMPLE_TEST'))
  define('SIMPLE_TEST', '/usr/local/simpletest/');

/* simpletest includes */
require_once SIMPLE_TEST . 'unit_tester.php';
require_once SIMPLE_TEST . 'reporter.php';
require_once SIMPLE_TEST . 'web_tester.php';

require_once ('TestEnvironment.php');

$test = new TestSuite("Sample Fossology test");
$test->addTestFile('mytest.php');
/*
 * leave the code below alone, it allows the tests to be run either by
 * the cli or in a web browser
 */
if (TextReporter :: inCli())
{
  exit ($test->run(new TextReporter()) ? 0 : 1);
}
$test->run(new HtmlReporter());
