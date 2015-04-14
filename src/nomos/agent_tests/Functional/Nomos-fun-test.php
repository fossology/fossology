<?php
/***********************************************************
 Copyright (C) 2013 Hewlett-Packard Development Company, L.P.

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
 * \brief Perform nomos functional test
 */

use Fossology\Lib\Test\TestPgDb;
use Fossology\Lib\Test\TestInstaller;

class NomosFunTest extends PHPUnit_Framework_TestCase
{
  private $testdir;
  private $agentDir;

  /** @var TestPgDb */
  private $testDb;
  /** @var TestInstaller */
  private $testInstaller;

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
  }

  public function tearDown()
  {
    $this->testInstaller->uninstall($this->agentDir);
    $this->testInstaller->clear();
    $this->testInstaller->rmRepo();
    $this->testDb = null;
  }

  function testDiffNomos()
  {
    $sysConf = $this->testDb->getFossSysConf();
    $nomos = $this->agentDir . "/agent/nomos";

    $last = exec("find $this->testdir -type f -not \( -wholename \"*svn*\" \) -exec $nomos -c $sysConf -l '{}' + > scan.out", $out, $rtn);

    $file_correct = dirname(dirname(__FILE__))."/testdata/LastGoodNomosTestfilesScan";
    $last = exec("wc -l < $file_correct");
    $regtest_msg = "Right now, we have $last nomos regression tests\n";
    print $regtest_msg;
    $regtest_cmd = "echo '$regtest_msg' >./nomos-regression-test.html";
    $last = exec($regtest_cmd);
    $old = str_replace('/','\/',dirname(dirname(__FILE__))."/testdata/");
    $last = exec("sed 's/ $old/ /g' ./scan.out > ./scan.out.r");
    $last = exec("sort $file_correct >./LastGoodNomosTestfilesScan.s");
    $last = exec("sort ./scan.out.r >./scan.out.s");
    $last = exec("diff ./LastGoodNomosTestfilesScan.s ./scan.out.s >./report.d", $out, $rtn);
    $count = exec("cat report.d|wc -l", $out, $ret);
    $this->assertEquals($count,'0', "some lines of licenses are different, please view ./report.d for the details!");
  }
}
