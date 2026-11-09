<?php
/*
 SPDX-FileCopyrightText: © 2012 Hewlett-Packard Development Company, L.P.
 SPDX-FileCopyrightText: © 2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @file
 * @brief Run one-shot license analysis on JSON license
 */
require_once ('CommonCliTest.php');

/**
 * @class OneShotJSON
 * @brief Run one-shot license analysis on JSON license
 */
class OneShotJSON extends CommonCliTest
{
  /**
   * @var string $testd_file
   * Path to the file
   */
  public $tested_file;

  /**
   * @brief Run NOMOS on JSON file
   * @test
   * -# Check if required file exists
   * -# Run nomos on the file and record the output
   * -# Check if the output says \b JSON
   */
  function testOneShot_JSON()
  {
    $this->tested_file = dirname(dirname(dirname(__DIR__))).'/testing/dataFiles/TestData/licenses/jslint.js';
    $license_report = "JSON";

    list($output,) = $this->runNomos("",array($this->tested_file));
    list(,,,,$license) = explode(' ', $output);
    $this->assertEquals(trim($license), $license_report);
  }
}
