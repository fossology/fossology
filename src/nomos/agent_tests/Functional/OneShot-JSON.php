<?php
/***********************************************************
 Copyright (C) 2012 Hewlett-Packard Development Company, L.P.
 Copyright (C) 2015 Siemens AG

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
