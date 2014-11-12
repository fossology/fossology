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
use Fossology\Lib\BusinessRules\NewestEditedLicenseSelector;

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
  /** @var LicenseDao */
  private $licenseDao;
  /** @var ClearingDao */
  private $clearingDao;
  /** @var NewestEditedLicenseSelector */
  private $newestEditedLicenseSelector;
  /** @var UploadDao */
  private $uploadDao;

  public function setUp()
  {
    $this->testDb = new TestPgDb("monkSched".time());
    $this->dbManager = $this->testDb->getDbManager();

    $this->licenseDao = new LicenseDao($this->dbManager);
    $this->uploadDao = new UploadDao($this->dbManager);
    $this->newestEditedLicenseSelector = new NewestEditedLicenseSelector();
    $this->clearingDao = new ClearingDao($this->dbManager, $this->newestEditedLicenseSelector, $this->uploadDao);
  }

  public function tearDown()
  {
    $this->testDb = null;
    $this->dbManager = null;
    $this->licenseDao = null;
    $this->clearingDao = null;
  }

  private function runMonk($uploadId, $userId=2, $groupId=2, $jobId=1, $args="")
  {
    $sysConf = $this->testDb->getFossSysConf();

    $agentName = "monk";

    $agentDir = dirname(dirname(__DIR__));
    system("install -D $agentDir/VERSION $sysConf/mods-enabled/$agentName/VERSION");

    $pipeFd = popen("echo $uploadId | ./$agentName -c $sysConf --userID=$userId --groupID=$groupId --jobId=$jobId --scheduler_start $args", "r");
    $this->assertTrue($pipeFd !== false, 'running monk failed');

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
    $this->testDb->createPlainTables(array('upload','uploadtree','uploadtree_a','license_ref','license_ref_bulk','clearing_event','license_file','highlight','highlight_bulk','agent','pfile','ars_master','group_user_member'),false);
    $this->testDb->createSequences(array('agent_agent_pk_seq','pfile_pfile_pk_seq','upload_upload_pk_seq','nomos_ars_ars_pk_seq','license_file_fl_pk_seq','license_ref_rf_pk_seq','license_ref_bulk_lrb_pk_seq','clearing_event_clearing_event_pk_seq'),false);
    $this->testDb->createViews(array('license_file_ref'),false);
    $this->testDb->createConstraints(array('agent_pkey','pfile_pkey','upload_pkey_idx','FileLicense_pkey','clearing_event_pkey'),false);
    $this->testDb->alterTables(array('agent','pfile','upload','ars_master','license_ref_bulk','clearing_event','license_file','highlight'),false);
    $this->testDb->getDbManager()->queryOnce("alter table uploadtree_a inherit uploadtree");

    $this->testDb->insertData(array('pfile','upload','uploadtree_a','group_user_member'), false);
    $this->testDb->insertData_license_ref();
  }

  public function testRunMonkScan()
  {
    $this->setUpTables();
    $this->setUpRepo();

    list($output,$retCode) = $this->runMonk($uploadId=1);

    $this->rmRepo();

    $this->assertEquals($retCode, 0, 'monk failed: '.$output);

    $bounds = $this->uploadDao->getParentItemBounds($uploadId);
    $matches = $this->licenseDao->getAgentFileLicenseMatches($bounds);

    $this->assertEquals($expected=1, count($matches));

    /** @var LicenseMatch */
    $licenseMatch = $matches[0];

    $this->assertEquals($expected=4, $licenseMatch->getFileId());

    /** @var LicenseRef */
    $matchedLicense = $licenseMatch->getLicenseRef();
    $this->assertEquals($matchedLicense->getShortName(), "GPL-3.0");

    /** @var AgentRef */
    $agentRef = $licenseMatch->getAgentRef();

    $this->assertEquals($agentRef->getAgentName(), "monk");
  }

  public function testRunMonkBulkScan()
  {
    $this->setUpTables();
    $this->setUpRepo();

    $userId = 2;
    $groupId = 2;
    $uploadTreeId = 1;

    $licenseId = 225;
    $removing = "f";
    $refText = "The GNU General Public License is a free, copyleft license for software and other kinds of works.";

    $jobId = 64;

    $bulkId = $this->licenseDao->insertBulkLicense($userId, $groupId, $uploadTreeId, $licenseId, $removing, $refText);

    $this->assertGreaterThan($expected=0, $bulkId);

    $bulkFlag = "-B"; // TODO agent_fomonkbulk::BULKFLAG
    $args = $bulkFlag.$bulkId;

    list($output,$retCode) = $this->runMonk($uploadId=1, $userId, $groupId, $jobId, $args);

    $this->rmRepo();

    $this->assertEquals($retCode, 0, 'monk failed: '.$output);

    $relevantDecisionsItem6 = $this->clearingDao->getRelevantClearingEvents($userId, 6);
    $relevantDecisionsItem7 = $this->clearingDao->getRelevantClearingEvents($userId, 7);

    $this->assertEquals($expected=1, count($relevantDecisionsItem6));
    $this->assertEquals($expected=1, count($relevantDecisionsItem7));
  }

  public function testRunMonkBulkScanWithBadSearchForDiff()
  {
    $this->setUpTables();
    $this->setUpRepo();

    $userId = 2;
    $groupId = 2;
    $uploadTreeId = 1;

    $licenseId = 225;
    $removing = "f";
    $refText = "The GNU General Public License is copyleft license for software and other kinds of works.";

    $jobId = 64;

    $bulkId = $this->licenseDao->insertBulkLicense($userId, $groupId, $uploadTreeId, $licenseId, $removing, $refText);

    $this->assertGreaterThan($expected=0, $bulkId);

    $bulkFlag = "-B"; // TODO agent_fomonkbulk::BULKFLAG
    $args = $bulkFlag.$bulkId;

    list($output,$retCode) = $this->runMonk($uploadId=1, $userId, $groupId, $jobId, $args);

    $this->rmRepo();

    $this->assertEquals($retCode, 0, 'monk failed: '.$output);

    $relevantDecisionsItem6 = $this->clearingDao->getRelevantClearingEvents($userId, 6);
    $relevantDecisionsItem7 = $this->clearingDao->getRelevantClearingEvents($userId, 7);

    $this->assertEquals($expected=0, count($relevantDecisionsItem6));
    $this->assertEquals($expected=0, count($relevantDecisionsItem7));
  }
}