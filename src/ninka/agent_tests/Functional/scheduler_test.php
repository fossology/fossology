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

use Fossology\Lib\Dao\LicenseDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Data\AgentRef;
use Fossology\Lib\Data\LicenseRef;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Test\TestPgDb;

if (!function_exists('Traceback_uri'))
{
  function Traceback_uri(){
    return 'Traceback_uri_if_desired';
  }
}

class NinkaScheduledTest extends \PHPUnit_Framework_TestCase
{
  /** @var TestPgDb */
  private $testDb;
  /** @var DbManager */
  private $dbManager;
  /** @var LicenseDao */
  private $licenseDao;
  /** @var UploadDao */
  private $uploadDao;

  public function setUp()
  {
    $this->testDb = new TestPgDb("ninkaSched".time());
    $this->dbManager = $this->testDb->getDbManager();

    $this->licenseDao = new LicenseDao($this->dbManager);
    $this->uploadDao = new UploadDao($this->dbManager);
  }
  
  public function tearDown()
  {
    $this->testDb = null;
    $this->dbManager = null;
    $this->licenseDao = null;
  }

  private function runNinka($uploadId)
  {
    $sysConf = $this->testDb->getFossSysConf();

    $agentName = "ninka";

    $agentDir = dirname(dirname(__DIR__));
    system("install -D $agentDir/VERSION $sysConf/mods-enabled/$agentName/VERSION");

    $pipeFd = popen("echo $uploadId | ./$agentName -c $sysConf --scheduler_start", "r");
    $this->assertTrue($pipeFd !== false, 'running ninka failed');

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
    $this->testDb->createPlainTables(array('upload','uploadtree','uploadtree_a','license_ref','license_file','highlight','agent','pfile','ars_master'),false);
    $this->testDb->createSequences(array('agent_agent_pk_seq','pfile_pfile_pk_seq','upload_upload_pk_seq','nomos_ars_ars_pk_seq','license_file_fl_pk_seq','license_ref_rf_pk_seq'),false);
    $this->testDb->createViews(array('license_file_ref'),false);
    $this->testDb->createConstraints(array('agent_pkey','pfile_pkey','upload_pkey_idx','FileLicense_pkey','rf_pkpk'),false);
    $this->testDb->alterTables(array('agent','pfile','upload','ars_master','license_file','highlight','license_ref'),false);

    $this->testDb->insertData(array('pfile','upload','uploadtree_a'), false);
    $this->testDb->insertData_license_ref();
  }

  public function testRun()
  {
    $this->setUpTables();
    $this->setUpRepo();

    list($output,$retCode) = $this->runNinka($uploadId=1);

    $this->rmRepo();

    $this->assertEquals($retCode, 0, 'ninka failed: '.$output);

    $bounds = $this->uploadDao->getParentItemBounds($uploadId);
    $matches = $this->licenseDao->getAgentFileLicenseMatches($bounds);

    $this->assertEquals($expected=6, count($matches));

    foreach($matches as $licenseMatch) {
      /** @var LicenseRef */
      $matchedLicense = $licenseMatch->getLicenseRef();
      if ($licenseMatch->getFileId() == 4)
      {
        $this->assertEquals($matchedLicense->getShortName(), "GPLv3+");
      }
      else if ($licenseMatch->getFileId() == 3)
      {
        $this->assertEquals($matchedLicense->getShortName(), "UnclassifiedLicense");
      }
      else
      {
        $this->assertEquals($matchedLicense->getShortName(), "No_license_found");
      }

      /** @var AgentRef */
      $agentRef = $licenseMatch->getAgentRef();
      $this->assertEquals($agentRef->getAgentName(), "ninka");
    }
  }

}
