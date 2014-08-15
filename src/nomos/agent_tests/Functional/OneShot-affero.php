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
 * Perform a one-shot license analysis on a affero 2 and 3 licenses
 *
 * License returned should be:
 *   for affero1: Affero_v1
 *   for affero3: Affero_v3,Public-domain
 *
 * @version "$Id: $"
 *
 * Created on March 1, 2012
 */

require_once (dirname(dirname(dirname(dirname(__FILE__)))).'/testing/lib/createRC.php');


class OneShotafferoTest extends PHPUnit_Framework_TestCase
{
  public $nomos;
  public $affero1;
  public $affero3;

  function setUp()
  {
    /* check to see if the file exists */
    $this->affero1 = dirname(dirname(dirname(dirname(__FILE__)))).'/testing/dataFiles/TestData/licenses/Affero-v1.0';
    $this->affero3 = dirname(dirname(dirname(dirname(__FILE__)))).'/testing/dataFiles/TestData/licenses/agpl-3.0.txt';
    $this->assertFileExists($this->affero1,"OneShotaffero1Test FAILURE! $this->affero1 not found\n");
    $this->assertFileExists($this->affero3,"OneShotaffero1Test FAILURE! $this->affero3 not found\n");
    createRC();
    $sysconf = getenv('SYSCONFDIR');
    $this->nomos = $sysconf . '/mods-enabled/nomos/agent/nomos';
  }

  function testOneShotafferos()
  {
    // could do a loop but it's more work.
    $last = exec("$this->nomos $this->affero1 2>&1", $out, $rtn);
    list(,$fname,,,$license) = explode(' ', implode($out));
    $this->assertEquals($fname, 'Affero-v1.0', "Error processed filename $fname
       does not equal Affero-v1.0");
    $this->assertEquals($license, 'AGPL-1.0', "Error license does not equal Affero_v1,
      $license was returned");
    $last = exec("$this->nomos $this->affero3 2>&1", $out3, $rtn);
    list(,$fname,,,$license) = explode(' ', implode($out3));
    $this->assertEquals($fname, 'agpl-3.0.txt', "Error processed filename $fname
       does not equal agpl-3.0.txt");
    $this->assertEquals($license, 'AGPL-3.0', "Error license
      does not equal Affero_v3, $license was returned");
  }
}
