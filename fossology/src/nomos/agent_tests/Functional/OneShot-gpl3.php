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
 * @version "$Id$"
 *
 * Created on March 1, 2012
 */

require_once ('../../../testing/lib/createRC.php');


class OneShotgpl3Test extends PHPUnit_Framework_TestCase
{
  public $nomos;
  public $gplv3;

  function setUp()
  {
    /* check to see if the file exists */
    $this->gplv3 = '../../../testing/dataFiles/TestData/licenses/gpl-3.0.txt';
    $this->assertFileExists($this->gplv3,"OneShotgplv21Test FAILURE! $this->gplv3 not found\n");
    createRC();
    $sysconf = getenv('SYSCONFDIR');
    //echo "DB: sysconf is:$sysconf\n";
    $this->nomos = $sysconf . '/mods-enabled/nomos/agent/nomos';
    //echo "DB: nomos is:$this->nomos\n";
  }

  function testOneShotgplv3()
  {
    $last = exec("$this->nomos $this->gplv3 2>&1", $out, $rtn);
    list(,$fname,,,$license) = explode(' ', implode($out));
    $this->assertEquals($fname, 'gpl-3.0.txt', "Error filename $fname does not equal gpl-3.0.txt");
    $this->assertEquals($license, 'FSF,GPL_v3,Public-domain',
      "Error license does not equal FSF,GPL_v3,Public-domain, $license was returned");
  }
}
?>
