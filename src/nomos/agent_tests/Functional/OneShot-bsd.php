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
 * \brief Perform a one-shot license analysis on a file (include bsd license)
 */
require_once ('CommonCliTest.php');

class OneShotbsdTest extends CommonCliTest
{
  public $bsd;

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
