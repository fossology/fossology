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
 * \brief Perform a one-shot license analysis on a glpv3 license
 *
 * License returned should be FSF,GPL_v3,Public-domain
 *
 * @version "$Id: OneShot-gpl3.php 6187 2012-09-07 21:01:03Z bobgo $"
 *
 * Created on March 1, 2012
 */

require_once ('../../../testing/lib/createRC.php');


class OneShotbsdTest extends PHPUnit_Framework_TestCase
{
  public $nomos;
  public $bsd;

  function setUp()
  {
    createRC();
    $sysconf = getenv('SYSCONFDIR');
    //echo "DB: sysconf is:$sysconf\n";
    $this->nomos = $sysconf . '/mods-enabled/nomos/agent/nomos';
    //echo "DB: nomos is:$this->nomos\n";
  }

  function testOneShotbsd()
  {
    /** test 1 */
    /* check to see if the file exists */
    $this->bsd = '../../../testing/dataFiles/TestData/licenses/BSD_style_d.txt';
    $this->assertFileExists($this->bsd,"OneShotbsdTest FAILURE! $this->bsd not found\n");
    $bsdlic = "BSD-style";
    $last = exec("$this->nomos $this->bsd 2>&1", $out, $rtn);
    list(,$fname,,,$license) = explode(' ', implode($out));
    $this->assertEquals($fname, 'BSD_style_d.txt', "Error filename $fname does not equal BSD_style_d.txt");
    $this->assertEquals($license, $bsdlic);

    /** test 2 */
    /* check to see if the file exists */
    $this->bsd = "";
    $license = "";
    $out = "";
    $this->bsd = '../../../testing/dataFiles/TestData/licenses/DNSDigest.c';
    $this->assertFileExists($this->bsd,"OneShotbsdTest FAILURE! $this->bsd not found\n");
    $bsdlic = "APSL,Apache-2.0,BSD-style,GPL";
    $last = exec("$this->nomos $this->bsd 2>&1", $out, $rtn);
    list(,$fname,,,$license) = explode(' ', implode($out));
    $this->assertEquals($fname, 'DNSDigest.c', "Error filename $fname does not equal DNSDigest.c");
    $this->assertEquals($license, $bsdlic);
  }
}
?>
