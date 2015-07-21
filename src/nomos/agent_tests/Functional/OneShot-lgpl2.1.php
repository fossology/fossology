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

use Fossology\Lib\Test\TestInstaller;
use Fossology\Lib\Test\TestPgDb;

require_once (dirname(dirname(dirname(dirname(__FILE__)))).'/testing/lib/createRC.php');


class OneShotgplv21Test extends PHPUnit_Framework_TestCase
{
  public $nomos;
  public $gplv21;

  
  function setUp()
  {
    $this->testDb = new TestPgDb("nomosfun".time());
    $this->agentDir = dirname(dirname(__DIR__))."/";
    $this->testdir = dirname(dirname(__DIR__))."/agent_tests/testdata/NomosTestfiles/";

    $sysConf = $this->testDb->getFossSysConf();
    $this->testInstaller = new TestInstaller($sysConf);
    $this->testInstaller->init();
    $this->testInstaller->install($this->agentDir);

    $this->testDb->createSequences(array(), true);
    $this->testDb->createPlainTables(array(), true);
    $this->testDb->alterTables(array(), true);
    
    $this->gplv21 = dirname(dirname(dirname(dirname(__FILE__)))).'/testing/dataFiles/TestData/licenses/gplv2.1';
    $this->assertFileExists($this->gplv21,"OneShotgplv21Test FAILURE! $this->gplv21 not found\n");
    // createRC();
    // $sysconf = getenv('SYSCONFDIR');
    $this->nomos = $sysConf . '/mods-enabled/nomos/agent/nomos';
  }

  public function tearDown()
  {
    $this->testInstaller->uninstall($this->agentDir);
    $this->testInstaller->clear();
    $this->testInstaller->rmRepo();
    $this->testDb = null;
  }
  

  function testOneShotgplv21()
  {
    $last = exec("$this->nomos $this->gplv21 2>&1", $out, $rtn);
    list(,$fname,,,$license) = explode(' ', implode($out));
    $this->assertEquals($fname, 'gplv2.1', "Error filename $fname does not equal gplv2.1");
    $this->assertEquals($license, 'LGPL-2.1', "Error license does not equal LGPL_v2.1,
       $license was returned");
  }
}
