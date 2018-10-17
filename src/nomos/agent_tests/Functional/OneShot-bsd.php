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
 * @brief Perform a one-shot license analysis on a file (include bsd license)
 */
require_once ('CommonCliTest.php');

/**
 * @class OneShotbsdTest
 * @brief Perform a one-shot license analysis on a file (include bsd license)
 */
class OneShotbsdTest extends CommonCliTest
{
  /**
   * @var string $bsd
   * File location of BSD license
   */
  public $bsd;

  /**
   * @brief Run NOMOS on BSD style license
   * @test
   * -# Check if required license files exists
   * -# Run nomos on the license files and record the output
   * -# Check if the correct license is matched on the intended file
   */
  function testOneShotBsd()
  {
    $this->bsd = dirname(dirname(dirname(dirname(__FILE__)))).'/testing/dataFiles/TestData/licenses/BSD_style_d.txt';
    $this->assertFileExists($this->bsd,"OneShotbsdTest FAILURE! $this->bsd not found\n");
    list($output,) = $this->runNomos("",array($this->bsd));
    $bsdlic = "BSD-3-Clause";
    list(,$fname,,,$license) = explode(' ', $output);
    $this->assertEquals($fname, 'BSD_style_d.txt', "Error filename $fname does not equal BSD_style_d.txt");
    $this->assertEquals(trim($license), $bsdlic);
  }

  /**
   * @brief Run NOMOS on DNS digest
   * @test
   * -# Check if required license files exists
   * -# Run nomos on the license files and record the output
   * -# Check if the correct license is matched on the intended file
   */
  function testOneShotDnsdigest()
  {
    $this->bsd = dirname(dirname(dirname(dirname(__FILE__)))).'/testing/dataFiles/TestData/licenses/DNSDigest.c';
    $this->assertFileExists($this->bsd,"OneShotbsdTest FAILURE! $this->bsd not found\n");
    list($output,) = $this->runNomos("",array($this->bsd));
    $bsdlic = "Apache-2.0,BSD-style,GPL";
    list(,$fname,,,$license) = explode(' ', $output);
    $this->assertEquals($fname, 'DNSDigest.c', "Error filename $fname does not equal DNSDigest.c");
    $this->assertEquals(trim($license), $bsdlic);
  }
}
