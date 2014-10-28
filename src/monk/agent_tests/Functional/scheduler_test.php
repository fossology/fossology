<?php
/*
Copyright (C) 2014, Siemens AG

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

namespace Fossology\Lib\Dao;

use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Test\TestPgDb;

if (!function_exists('Traceback_uri'))
{
  function Traceback_uri(){
    return 'Traceback_uri_if_desired';
  }
}

class MonkScheduledTest extends \PHPUnit_Framework_TestCase
{
  /** @var TestPgDb */
  private $testDb;
  /** @var DbManager */
  private $dbManager;

  public function setUp()
  {
    $this->testDb = new TestPgDb("monkSched".time());
    $this->dbManager = $this->testDb->getDbManager();
  }
  
  public function tearDown()
  {
    $this->testDb = null;
    $this->dbManager = null;
  }

  private function runMonk($uploadId)
  {
    $sysConf = $this->testDb->getFossSysConf();
    $confFile = $sysConf."/fossology.conf";
    system("touch ".$confFile);
    $config = "[FOSSOLOGY]\ndepth = 0\npath = $sysConf/repo\n";
    file_put_contents($confFile, $config);
    $agentDir = dirname(dirname(__DIR__));
    system("install -D $agentDir/VERSION $sysConf/mods-enabled/monk/VERSION");

    $testRepoDir = dirname(dirname(dirname(__DIR__)))."/lib/php/Test/";
    system("cp -a $testRepoDir/repo $sysConf/");
    $pipeFd = popen("echo $uploadId | ".$agentDir."/agent/monk -c ".$sysConf." --scheduler_start", "r");
    $this->assertTrue($pipeFd !== false, 'runnig monk failed');

    $output = "";
    while (($buffer = fgets($pipeFd, 4096)) !== false) {
      $output .= $buffer;
    }
    $this->assertTrue($output !== false, 'monk failed');
    $retCode = pclose($pipeFd);

    $this->assertEquals($retCode, 0, 'running monk failed: '.$output);

    unlink("$sysConf/mods-enabled/monk/VERSION");
    rmdir("$sysConf/mods-enabled/monk");
    rmdir("$sysConf/mods-enabled");
    unlink($sysConf."/fossology.conf");

    return $output;
  }

  private function setUpTables()
  {
    $this->testDb->createPlainTables(array('upload','uploadtree','uploadtree_a','license_ref','license_file','highlight','agent','pfile','ars_master'),false);
    $this->testDb->createSequences(array('agent_agent_pk_seq','pfile_pfile_pk_seq','upload_upload_pk_seq','nomos_ars_ars_pk_seq','license_file_fl_pk_seq'),false);
    $this->testDb->createConstraints(array('agent_pkey','pfile_pkey','upload_pkey_idx','FileLicense_pkey'),false);
    $this->testDb->alterTables(array('agent','pfile','upload','ars_master','license_file','highlight'),false);

    $this->testDb->insertData(array('pfile','upload','uploadtree_a'), false);
    $this->testDb->insertData_license_ref();
  }


  public function testRun()
  {
    $this->setUpTables();

    echo $this->runMonk(1);


  }


}