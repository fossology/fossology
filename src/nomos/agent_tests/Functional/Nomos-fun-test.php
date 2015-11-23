<?php
/***********************************************************
 Copyright (C) 2013 Hewlett-Packard Development Company, L.P.
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

class NomosFunTest extends CommonCliTest
{
  /** @var string */
  protected $testdir;

  protected function setUp()
  {
    parent::setUp();

    $this->testdir = dirname(dirname(__DIR__))."/agent_tests/testdata/NomosTestfiles/";
  }

  public function testDiffNomos()
  {
    $sysConf = $this->testDb->getFossSysConf();
    $nomos = $this->agentDir . "/agent/nomos";

    exec("find $this->testdir -type f -not \( -wholename \"*svn*\" \) -exec $nomos -c $sysConf -l '{}' + > scan.out", $out, $rtn);

    $file_correct = dirname(dirname(__FILE__))."/testdata/LastGoodNomosTestfilesScan";
    $last = exec("wc -l < $file_correct");
    $regtest_msg = "Right now, we have $last nomos regression tests\n";
    print $regtest_msg;
    $regtest_cmd = "echo '$regtest_msg' >./nomos-regression-test.html";
    exec($regtest_cmd);
    $old = str_replace('/','\/',dirname(dirname(__FILE__))."/testdata/");
    exec("sed 's/ $old/ /g' ./scan.out > ./scan.out.r");
    exec("sort $file_correct >./LastGoodNomosTestfilesScan.s");
    exec("sort ./scan.out.r >./scan.out.s");
    exec("diff ./LastGoodNomosTestfilesScan.s ./scan.out.s >./report.d", $out, $rtn);
    $count = exec("cat report.d|wc -l", $out, $ret);
    $this->assertEquals($count,'0', "some lines of licenses are different, please view ./report.d for the details!");
  }
}
