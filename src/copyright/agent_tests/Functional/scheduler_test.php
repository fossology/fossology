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

use Fossology\Lib\Dao\CopyrightDao;
use Fossology\Lib\Dao\LicenseDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Test\TestPgDb;

if (!function_exists('Traceback_uri'))
{
  function Traceback_uri(){
    return 'Traceback_uri_if_desired';
  }
}

class CopyrightScheduledTest extends \PHPUnit_Framework_TestCase
{
  /** @var TestPgDb */
  private $testDb;
  /** @var DbManager */
  private $dbManager;
  /** @var LicenseDao */
  private $licenseDao;
  /** @var UploadDao */
  private $uploadDao;
  /** @var CopyrightDao */
  private $copyrightDao;

  public function setUp()
  {
    $this->testDb = new TestPgDb("copyrightSched".time());
    $this->dbManager = $this->testDb->getDbManager();

    $this->licenseDao = new LicenseDao($this->dbManager);
    $this->uploadDao = new UploadDao($this->dbManager);
    $this->copyrightDao = new CopyrightDao($this->dbManager, $this->uploadDao);
  }

  public function tearDown()
  {
    $this->testDb = null;
    $this->dbManager = null;
    $this->licenseDao = null;
  }

  private function runCopyright($uploadId)
  {
    $sysConf = $this->testDb->getFossSysConf();

    $agentName = "copyright";

    $agentDir = dirname(dirname(__DIR__));
    system("install -D $agentDir/VERSION-copyright $sysConf/mods-enabled/$agentName/VERSION");

    $pipeFd = popen("echo $uploadId | ./$agentName -c $sysConf --scheduler_start", "r");
    $this->assertTrue($pipeFd !== false, 'running copyright failed');

    $output = "";
    while (($buffer = fgets($pipeFd, 4096)) !== false) {
      $output .= $buffer;
    }
    $retCode = pclose($pipeFd);

    unlink("$sysConf/mods-enabled/$agentName/VERSION");
    rmdir("$sysConf/mods-enabled/$agentName");
    rmdir("$sysConf/mods-enabled");
    unlink($sysConf."/fossology.conf");

    return array($output,$retCode);
  }

  private function setUpRepo()
  {
    $sysConf = $this->testDb->getFossSysConf();

    $confFile = $sysConf."/fossology.conf";
    system("touch ".$confFile);
    $config = "[FOSSOLOGY]\ndepth = 0\npath = $sysConf/repo\n";
    file_put_contents($confFile, $config);

    $testRepoDir = dirname(dirname(dirname(__DIR__)))."/lib/php/Test/";
    system("cp -a $testRepoDir/repo $sysConf/");
  }

  private function rmRepo()
  {
    $sysConf = $this->testDb->getFossSysConf();
    system("rm $sysConf/repo -rf");
  }

  private function setUpTables()
  {
    $this->testDb->createPlainTables(array('agent','uploadtree','upload','uploadtree_a','pfile','users','bucketpool','mimetype','clearing_decision_type','ars_master'));
    $this->testDb->createSequences(array('agent_agent_pk_seq','upload_upload_pk_seq','pfile_pfile_pk_seq','users_user_pk_seq','clearing_decision_type_type_seq','nomos_ars_ars_pk_seq'));
    $this->testDb->createConstraints(array('agent_pkey','upload_pkey_idx','pfile_pkey','user_pkey','clearing_decision_type_pkey'));
    $this->testDb->alterTables(array('agent','pfile','upload','ars_master','users'));

    $this->testDb->insertData(array('upload','pfile','uploadtree_a','bucketpool','mimetype','users'), false);
  }

  public function testRun()
  {
    $this->setUpTables();
    $this->setUpRepo();

    list($output,$retCode) = $this->runCopyright($uploadId=1);

    $this->rmRepo();

    $this->assertEquals($retCode, 0, 'copyright failed: '.$output);

    $uploadTreeTableName = $this->uploadDao->getUploadtreeTableName($uploadId);

    $matches = $this->copyrightDao->getAllEntries("copyright", $uploadId, $uploadTreeTableName);
    $this->assertGreaterThan($expected=5, count($matches), $output);
  }

}
