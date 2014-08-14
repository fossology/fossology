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
 * \brief Perform a one-shot license analysis on a file with no license
 *
 * License returned should be: No_license_found
 *
 * @version "$Id$"
 *
 * Created on March 1, 2012
 */

require_once (dirname(dirname(dirname(dirname(__FILE__)))).'/testing/lib/createRC.php');


class OneShotnoneTest extends PHPUnit_Framework_TestCase
{
  public $nomos;
  public $none;

  function setUp()
  {
    /* check to see if the file exists */
    $this->none = dirname(dirname(__FILE__)).'/testdata/noLic';
    $this->assertFileExists($this->none,"OneShotnoneTest FAILURE! $this->none not found\n");
    createRC();
    $sysconf = getenv('SYSCONFDIR');
    $this->nomos = $sysconf . '/mods-enabled/nomos/agent/nomos';
  }

  function testOneShotnone()
  {
    $last = exec("$this->nomos $this->none 2>&1", $out, $rtn);
    list(,$fname,,,$license) = explode(' ', implode($out));
    $this->assertEquals($fname, 'noLic', "Error filename $fname does not equal noLic");
    $this->assertEquals($license, 'No_license_found', "Error license does not
      equal No_license_found, $license was returned");
  }
}
