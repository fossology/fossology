#!/usr/bin/php
<?php
/***********************************************************
 Copyright (C) 2011 Hewlett-Packard Development Company, L.P.

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
?>