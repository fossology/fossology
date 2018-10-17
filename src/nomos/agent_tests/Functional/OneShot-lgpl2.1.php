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
 * @brief Run one-shot license analysis on LGPL_v2.1 license
 */
require_once ('CommonCliTest.php');

/**
 * @class OneShotgplv21Test
 * @brief Run one-shot license analysis on LGPL_v2.1 license
 */
class OneShotgplv21Test extends CommonCliTest
{
  /**
   * @brief Run NOMOS on GPL_v2.1 license
   * @test
   * -# Check if required license file exists
   * -# Run nomos on the file and record the output
   * -# Check if the output says \b LGPL-2.1
   */
  public function testOneShotgplv21()
  {
    $gplv21 = dirname(dirname(dirname(dirname(__FILE__)))).'/testing/dataFiles/TestData/licenses/gplv2.1';
    $this->assertFileExists($gplv21,"OneShotgplv21Test FAILURE! $gplv21 not found\n");

    list($output,) = $this->runNomos("",array($gplv21));
    list(,$fname,,,$license) = explode(' ', $output);
    $this->assertEquals($fname, 'gplv2.1', "Error filename $fname does not equal gplv2.1");
    $this->assertEquals(trim($license), 'LGPL-2.1', "Error license does not equal LGPL_v2.1,
       $license was returned");
  }
}
