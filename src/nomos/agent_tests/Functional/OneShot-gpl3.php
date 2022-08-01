<?php
/*
 SPDX-FileCopyrightText: © 2012-2013 Hewlett-Packard Development Company, L.P.
 SPDX-FileCopyrightText: © 2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @file
 * @brief Perform a one-shot license analysis on a glpv3 license
 *
 * License returned should be FSF,GPL_v3,Public-domain
 */

require_once ('CommonCliTest.php');

/**
 * @class OneShotgpl3Test
 * @brief Perform a one-shot analysis on GPL_v3 license
 */
class OneShotgpl3Test extends CommonCliTest
{
  /**
   * @var string $gplv3
   * Path to GPL license
   */
  public $gplv3;

  /**
   * @brief Run NOMOS on GPL license
   * @test
   * -# Check if required GPL license file exists
   * -# Run nomos on the license and record the output
   * -# Check if the output says \b GPL-3.0
   */
  public function testOneShotgplv3()
  {
    /* check to see if the file exists */
    $this->gplv3 = dirname(dirname(dirname(dirname(__FILE__)))).'/testing/dataFiles/TestData/licenses/gpl-3.0.txt';
    $this->assertFileExists($this->gplv3,"OneShotgplv21Test FAILURE! $this->gplv3 not found\n");

    list($output,) = $this->runNomos("",array($this->gplv3));
    list(,$fname,,,$license) = explode(' ', $output);

    $this->assertEquals($fname, 'gpl-3.0.txt', "Error filename $fname does not equal gpl-3.0.txt");
    $this->assertEquals(trim($license), 'GPL-3.0',
      "Error license does not equal FSF,GPL_v3. $license was returned");
  }
}
