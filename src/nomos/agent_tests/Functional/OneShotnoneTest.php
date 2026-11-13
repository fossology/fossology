<?php
/*
 SPDX-FileCopyrightText: © 2012 Hewlett-Packard Development Company, L.P.
 SPDX-FileCopyrightText: © 2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/
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
