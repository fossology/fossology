#!/usr/bin/php
<?php
/*
 SPDX-FileCopyrightText: © 2011 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * \brief Run the simpletest FOSSology verify upload tests
 *
 * Produces an junit formated xml report to stdout (for now).
 * Assumes being run as the user jenkins by the application jenkins.  The
 * script can be run standalone by other users from the jenkins workspace area.
 *
 * @version "$Id$"
 */

/* simpletest includes */
require_once '/usr/local/simpletest/unit_tester.php';
require_once '/usr/local/simpletest/collector.php';
require_once '/usr/local/simpletest/web_tester.php';
require_once '/usr/local/simpletest/reporter.php';
require_once '/usr/local/simpletest/extensions/junit_xml_reporter.php';

global $home;

class verifyUploadsTest extends TestSuite
{
  function __construct($label=FALSE)
  {
    parent::__construct($label);
    global $home;

    //echo "runVU is at:" . getcwd() . "\n";
    if(chdir( '../ui/tests') === FALSE )
    {
      echo "FATAL! Cannot cd to the ui/tests directory\n";
      exit(1);
    }
    $testPath = getcwd();
    // Verify uploads
    $this->collect($testPath . '/VerifyTests',
    new SimplePatternCollector('/Test.php/'));

    if (chdir($home) === FALSE)
    {
      $cUInoHome = "FATAL! can't cd to $home\n";
      //LogAndPrint($LF, $cUInoHome);
      echo $cUInoHome;
      exit(1);
    }
    // nomos, only 2 major tests right now, just add them.
    // @todo when there are more nomos tests, change to pattern expectation.
    $this->addTestFile('nomos/ckZendTest.php');
    $this->addTestFile('nomos/verifyRedHatTest.php');
    $this->addTestFile('copyright/verify3filesTest.php');
  }
}

$home = getcwd();

// collect the verify tests
$testRun = new verifyUploadsTest('Fossology Upload Functional Tests');

// run the collected tests
$testRun->run(new JUnitXMLReporter());

// Need to seperate the reports in the file...
