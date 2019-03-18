<?php
/***********************************************************
 Copyright (C) 2012-2013 Hewlett-Packard Development Company, L.P.
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
