<?php
/*
 SPDX-FileCopyrightText: © 2012 Hewlett-Packard Development Company, L.P.
 SPDX-FileCopyrightText: © 2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @file
 * @brief Perform a one-shot license analysis on Oracle-Berkeley-DB and
 * sleepycat license
 */
require_once ('CommonCliTest.php');

/**
 * @class OneShotOracleBerkeleyDB
 * @brief Perform a one-shot license analysis on Oracle-Berkeley-DB and
 * sleepycat license
 */
class OneShotOracleBerkeleyDB extends CommonCliTest
{
  /**
   * @var string $tested_file
   * Path to license file
   */
  public $tested_file;

  /**
   * @brief Run NOMOS on license files
   * @test
   * -# Get the location of test files
   * -# Run NOMOS on the test files and record the output
   * -# Check if the nomos records Oracle-Berkeley-DB and Sleepycat license
   */
  public function testOneShotOracle_Berkeley_DB()
  {
    /* Oracle-Berkeley-DB */
    $oracleBDB_tested_file = dirname(dirname(dirname(__DIR__))).'/testing/dataFiles/TestData/licenses/Oracle-Berkeley-DB.java';
    list($output,) = $this->runNomos("",array($oracleBDB_tested_file));
    list(,,,,$licenseOBDB) = explode(' ', $output);
    $this->assertEquals(trim($licenseOBDB), "Oracle-Berkeley-DB");

    /* sleepycat */
    $sleepycatTested_file = dirname(dirname(dirname(__DIR__))).'/testing/dataFiles/TestData/licenses/sleepycat.php';
    list($outputSc,) = $this->runNomos("",array($sleepycatTested_file));
    list(,,,,$licenseSc) = explode(' ', $outputSc);
    $this->assertEquals(trim($licenseSc), "Sleepycat");
  }
}
