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
 * run a test
 *
 * Run a test using simpletest, useful when working on a new test.
 *
 * @version "$Id: $"
 *
 * Created on March 18, 2009
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

$Usage = "runx -l 'list of tests space seperated'\n";

$options = getopt("l:");
if (empty($options)) {
  print $Usage;
  exit(1);
}
if (array_key_exists("l",$options)) {
  $RunList = preg_split('/\s|\n/',$options['l']);
  //print "runx: runlist is:\n"; print_r($RunList) . "\n";
}
else {
  print $Usage;
  exit(1);
}
$Runtest = & new TestSuite("Fossology tests");
/*
 * will this work?  in the loop all the tests get scheduled at once.... do they run
 * all at the same time or serially?
 */
foreach($RunList as $ptest) {
  if(preg_match('/^.*?\.php/',$ptest)) {
    $test = $ptest;
  }
  else {
    $test = $ptest . ".php";
  }
  //print "runx: test is:$test\n";
  $Runtest->addTestFile("$test");
}

/*
 * leave the code below alone, it allows the tests to be run either by
 * the cli or in a web browser
 */
if (TextReporter :: inCli())
{
  exit ($Runtest->run(new TextReporter()) ? 0 : 1);
}
$Runtest->run(new HtmlReporter());
?>
