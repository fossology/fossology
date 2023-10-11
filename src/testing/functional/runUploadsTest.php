#!/usr/bin/php
<?php
/*
 SPDX-FileCopyrightText: © 2011 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * \brief Run the simpletest FOSSology upload tests
 *
 * The uploads are needed by the Verify tests that run after the uploads
 * have been processed.
 *
 * Produces an junit formated xml report to stdout (for now).
 * Assumes being run as the user jenkins by the application jenkins.  The
 * script can be run standalone by other users from the jenkins workspace area.
 *
 * @return boolean
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

class uploadsTest extends TestSuite
{
  function __construct($label=FALSE)
  {
    parent::__construct($label);
    $this->addTestFile('uplTestData.php');
    $this->addTestFile('uploadCopyrightData.php');
    // agent add data is not ready yet....due to javascript.
    //$this->addTestFile('AgentAddData.php');
    // do the uploads and output in text...
  }
}

// run the upload test data programs
$home = getcwd();

$uploadTest = new uploadsTest('Upload and Analyze Test Data');
$uploadTest->run(new JUnitXMLReporter());

// Waiting for upload jobs to finish...
$last = exec('./wait4jobs.php', $tossme, $jobsDone);

// @todo fix the message so the user runs the correct program...
if ($jobsDone != 0)
{
  $errMsg = "ERROR! jobs are not finished after two hours, not running" .
    "verify tests, please investigate and run verify tests by hand\n" .
    "Monitor the job Q and when the setup jobs are done, run:\n" .
    "$myname xxxx $logFile\n";
  $this->fail($errMsg);
  return(FALSE);
}
return(TRUE);
