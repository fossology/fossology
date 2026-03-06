<?php
/*
 SPDX-FileCopyrightText: © 2012 Hewlett-Packard Development Company, L.P.
 SPDX-FileCopyrightText: © 2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
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
    $bsdlic = "Apache-2.0,GPL,LicenseRef-BSD-style";
    list(,$fname,,,$license) = explode(' ', $output);
    $this->assertEquals($fname, 'DNSDigest.c', "Error filename $fname does not equal DNSDigest.c");
    $this->assertEquals(trim($license), $bsdlic);
  }
}
