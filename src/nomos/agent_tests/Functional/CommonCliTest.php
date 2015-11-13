<?php
/*
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
 */

use Fossology\Lib\Test\TestInstaller;
use Fossology\Lib\Test\TestPgDb;

class CommonCliTest extends PHPUnit_Framework_TestCase
{
  /** @var TestPgDb */
  protected $testDb;
  /** @var TestInstaller */
  protected $testInstaller;
  /** @var string */
  protected $agentDir;
  
  protected function setUp()
  {
    $this->testDb = new TestPgDb("nomosfun".time());
    $this->agentDir = dirname(dirname(__DIR__));
    $this->testdir = dirname(dirname(__DIR__))."/agent_tests/testdata/NomosTestfiles/";

    $sysConf = $this->testDb->getFossSysConf();
    $this->testInstaller = new TestInstaller($sysConf);
    $this->testInstaller->init();
    $this->testInstaller->install($this->agentDir);

    $this->testDb->createSequences(array('license_ref_rf_pk_seq'), false);
    $this->testDb->createPlainTables(array('agent','license_ref'), false);
    $this->testDb->alterTables(array('license_ref'), false);
  }

  protected function tearDown()
  {
    $this->testInstaller->uninstall($this->agentDir);
    $this->testInstaller->clear();
    $this->testInstaller->rmRepo();
    $this->testDb = null;
  }
  
  protected function runNomos($args="", $files=array())
  {
    $sysConf = $this->testDb->getFossSysConf();

    $confFile = $sysConf."/fossology.conf";
    system("touch ".$confFile);
    $config = "[FOSSOLOGY]\ndepth = 0\npath = $sysConf/repo\n";
    file_put_contents($confFile, $config);

    $execDir = $this->agentDir.'/agent';
    system("install -D $this->agentDir/VERSION $sysConf/mods-enabled/nomos/VERSION");

    foreach ($files as $file) {
      $args .= " ".escapeshellarg($file);
    }

    $pipeFd = popen("$execDir/nomos -c $sysConf $args", "r");
    $this->assertTrue($pipeFd !== false, 'running nomos failed');

    $output = "";
    while (($buffer = fgets($pipeFd, 4096)) !== false) {
      $output .= $buffer;
    }
    $retCode = pclose($pipeFd);

    unlink("$sysConf/mods-enabled/nomos/VERSION");
    //unlink("$sysConf/mods-enabled/nomos");
    //rmdir("$sysConf/mods-enabled");
    unlink($confFile);

    return array($output,$retCode);
  }
  
  public function testHelp()
  {
    $nomos = dirname(dirname(__DIR__)) . '/agent/nomos';
    list($output,) = $this->runNomos($args="-h"); // exec("$nomos -h 2>&1", $out, $rtn);
    $out = explode("\n", $output);
    $usage = "Usage: $nomos [options] [file [file [...]]";
    $this->assertEquals($usage, $out[0]);
  }
}
