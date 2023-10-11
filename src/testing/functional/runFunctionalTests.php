#!/usr/bin/php
<?php
/*
 SPDX-FileCopyrightText: © 2011 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * \brief Run the simpletest FOSSology functional tests
 *
 * Produces an junit formated xml report to stdout.
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

class groupFuncTests extends TestSuite
{
  function __construct($label=FALSE)
  {
    parent::__construct($label);
    // run createUIUsers first
    $this->addTestFile('createUIUsers.php');
    if(chdir( '../ui/tests') === FALSE )
    {
      echo "FATAL! Cannot cd to the ui/tests directory\n";
    }
    $testPath = getcwd();
    //echo "RFTCLASS: testPath is:$testPath\n";

    $this->collect($testPath . '/SiteTests',
    new SimplePatternCollector('/Test.php/'));
    // BasicSetup Must be run before any of the BasicTests, they depend on it.
    $this->addTestFile('BasicTests/BasicSetup.php');
    $this->collect($testPath . '/BasicTests',
    new SimplePatternCollector('/Test.php/'));
    $this->collect($testPath . '/Users',
    new SimplePatternCollector('/Test.php/'));
    $this->collect($testPath . '/EmailNotification',
    new SimplePatternCollector('/Test.php/'));
    if(chdir( '../../tests') === FALSE )
    {
      echo "FATAL! Cannot cd to the ../../tests directory\n";
    }
  }
}

// collect the tests
$testRun = new groupFuncTests('Fossology UI Functional Tests');

// run the collected tests
$testRun->run(new JUnitXMLReporter());
