<?php
/***********************************************************
 Copyright (C) 2012 Hewlett-Packard Development Company, L.P.

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

require_once (dirname(dirname(dirname(dirname(__FILE__)))).'/testing/lib/createRC.php');


class OneShotbsdTest extends PHPUnit_Framework_TestCase
{
  public $nomos;
  public $bsd;

  function setUp()
  {
    createRC();
    $sysconf = getenv('SYSCONFDIR');
    $this->nomos = $sysconf . '/mods-enabled/nomos/agent/nomos';
  }

  function testOneShotBsd()
  {
    $this->bsd = dirname(dirname(dirname(dirname(__FILE__)))).'/testing/dataFiles/TestData/licenses/BSD_style_d.txt';
    $this->assertFileExists($this->bsd,"OneShotbsdTest FAILURE! $this->bsd not found\n");
    $bsdlic = "BSD-3-Clause";
    $last = exec("$this->nomos $this->bsd 2>&1", $out, $rtn);
    list(,$fname,,,$license) = explode(' ', implode($out));
    $this->assertEquals($fname, 'BSD_style_d.txt', "Error filename $fname does not equal BSD_style_d.txt");
    $this->assertEquals($license, $bsdlic);
  }

  function testOneShotDnsdigest()
  {
    $this->bsd = "";
    $license = "";
    $out = "";
    $this->bsd = dirname(dirname(dirname(dirname(__FILE__)))).'/testing/dataFiles/TestData/licenses/DNSDigest.c';
    $this->assertFileExists($this->bsd,"OneShotbsdTest FAILURE! $this->bsd not found\n");
    $bsdlic = "Apache-2.0,BSD-style,GPL";
    $last = exec("$this->nomos $this->bsd 2>&1", $out, $rtn);
    list(,$fname,,,$license) = explode(' ', implode($out));
    $this->assertEquals($fname, 'DNSDigest.c', "Error filename $fname does not equal DNSDigest.c");
    $this->assertEquals($license, $bsdlic);
  }
}
