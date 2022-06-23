<?php
/*
 SPDX-FileCopyrightText: © 2012 Hewlett-Packard Development Company, L.P.
 SPDX-FileCopyrightText: © 2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/
require_once ('CommonCliTest.php');
/**
 * @file
 * @brief Perform a one-shot license analysis on a affero 2 and 3 licenses
 */

/**
 * @class OneShotafferoTest
 * @brief Perform a one-shot license analysis on a affero 2 and 3 licenses
 *
 * License returned should be:
 *   for affero1: Affero_v1
 *   for affero3: Affero_v3,Public-domain
 */
class OneShotafferoTest extends CommonCliTest
{
  /**
   * @var string $affero1
   * File location of Affero_v1 license
   */
  public $affero1;
  /**
   * @var string $affero3
   * File location for Affero_v3 license
   */
  public $affero3;

  /**
   * @brief Run NOMOS on affero1 and affero3
   * @test
   * -# Check if required license files exists
   * -# Run nomos on the license files and record the output
   * -# Check if the correct license is matched for a given affero license
   */
  public function testOneShotafferos()
  {
    /* check to see if the file exists */
    $this->affero1 = dirname(dirname(dirname(dirname(__FILE__)))).'/testing/dataFiles/TestData/licenses/Affero-v1.0';
    $this->affero3 = dirname(dirname(dirname(dirname(__FILE__)))).'/testing/dataFiles/TestData/licenses/agpl-3.0.txt';
    $this->assertFileExists($this->affero1,"OneShotaffero1Test FAILURE! $this->affero1 not found\n");
    $this->assertFileExists($this->affero3,"OneShotaffero1Test FAILURE! $this->affero3 not found\n");

    list($output,) = $this->runNomos("",array($this->affero1));
    list(,$fname1,,,$license1) = explode(' ', $output);
    $this->assertEquals($fname1, 'Affero-v1.0', "Error processed filename $fname1
       does not equal Affero-v1.0");
    $this->assertEquals(trim($license1), 'AGPL-1.0', "Error license does not equal Affero_v1,
      $license1 was returned");

    list($out3,) = $this->runNomos("",array($this->affero3));
    list(,$fname,,,$license) = explode(' ', $out3);
    $this->assertEquals($fname, 'agpl-3.0.txt', "Error processed filename $fname
       does not equal agpl-3.0.txt");
    $this->assertEquals(trim($license), 'AGPL-3.0', "Error license
      does not equal Affero_v3, $license was returned");
  }
}
