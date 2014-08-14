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
 * \brief Perform a one-shot license analysis on files (include Oracle-Berkeley-DB, Sleepycat)
 *
 */

require_once (dirname(dirname(dirname(dirname(__FILE__)))).'/testing/lib/createRC.php');


class OneShot_Oracle_Berkeley_DB extends PHPUnit_Framework_TestCase
{
  public $nomos;
  public $tested_file;

  function setUp()
  {
    createRC();
    $sysconf = getenv('SYSCONFDIR');
    //echo "DB: sysconf is:$sysconf\n";
    $this->nomos = $sysconf . '/mods-enabled/nomos/agent/nomos';
    //echo "DB: nomos is:$this->nomos\n";
  }

  function testOneShotOracle_Berkeley_DB()
  {
    /** Oracle-Berkeley-DB */
    $this->tested_file = dirname(dirname(dirname(dirname(__FILE__)))).'/testing/dataFiles/TestData/licenses/Oracle-Berkeley-DB.java';
    $license_report = "Oracle-Berkeley-DB";
    $last = exec("$this->nomos $this->tested_file 2>&1", $out, $rtn);
    list(,$fname,,,$license) = explode(' ', implode($out));
    $this->assertEquals($license, $license_report);


    $out = "";
    /** sleepycat */
    $this->tested_file = dirname(dirname(dirname(dirname(__FILE__)))).'/testing/dataFiles/TestData/licenses/sleepycat.php';
    $license_report = "Sleepycat";
    $last = exec("$this->nomos $this->tested_file 2>&1", $out, $rtn);
    list(,$fname,,,$license) = explode(' ', implode($out));
    $this->assertEquals($license, $license_report);
  }
}
