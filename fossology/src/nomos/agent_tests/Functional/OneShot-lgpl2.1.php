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
 * Perform a one-shot license analysis on a glpv2.1 license
 *
 * License returned should be LGPL_v2.1
 *
 * @version "$Id$"
 *
 * Created on March 1, 2012
 */

require_once ('../../../testing/lib/createRC.php');


class OneShotgplv21Test extends PHPUnit_Framework_TestCase
{
  public $nomos;
  public $gplv21;

  function setUp()
  {
    /* check to see if the file exists */
    $this->gplv21 = '../../../testing/dataFiles/TestData/licenses/gplv2.1';
    $this->assertFileExists($this->gplv21,"OneShotgplv21Test FAILURE! $this->gplv21 not found\n");
    createRC();
    $sysconf = getenv('SYSCONFDIR');
    //echo "DB: sysconf is:$sysconf\n";
    $this->nomos = $sysconf . '/mods-enabled/nomos/agent/nomos';
    //echo "DB: nomos is:$this->nomos\n";
  }

  function testOneShotgplv21()
  {

    //print "starting OneShotgplv21Test\n";
    $last = exec("$this->nomos $this->gplv21 2>&1", $out, $rtn);
    list(,$fname,,,$license) = explode(' ', implode($out));
    $this->assertEquals($fname, 'gplv2.1', "Error filename $fname does not equal gplv2.1");
    $this->assertEquals($license, 'LGPL_v2.1', "Error license does not equal LGPL_v2.1,
       $license was returned");
  }
}
?>
