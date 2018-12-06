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
 * @brief Perform a one-shot license analysis on a file with no license
 *
 * License returned should be: No_license_found
 */
require_once ('CommonCliTest.php');

/**
 * @class OneShotnoneTest
 * @brief Perform a one-shot license analysis on a file with no license
 */
class OneShotnoneTest extends CommonCliTest
{
  /**
   * @var string $none
   * Path to the file without license
   */
  public $none;

  /**
   * @brief Run NOMOS on file without license information
   * @test
   * -# Get the location of test file
   * -# Run NOMOS on the test file and record the output
   * -# Check if the output says \b No_license_found
   */
  public function testOneShotnone()
  {
    /* check to see if the file exists */
    $this->none = dirname(dirname(__FILE__)).'/testdata/noLic';
    $this->assertFileExists($this->none,"OneShotnoneTest FAILURE! $this->none not found\n");

    list($output,) = $this->runNomos("",array($this->none));
    list(,$fname,,,$license) = explode(' ', $output);

    $this->assertEquals($fname, 'noLic', "Error filename $fname does not equal noLic");
    $this->assertEquals(trim($license), 'No_license_found', "Error license does not
      equal No_license_found, $license was returned");
  }
}
