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

namespace Fossology\Decider\Test;

use Fossology\Lib\BusinessRules\AgentLicenseEventProcessor;
use Fossology\Lib\BusinessRules\ClearingDecisionProcessor;
use Fossology\Lib\BusinessRules\ClearingEventProcessor;
use Fossology\Lib\BusinessRules\LicenseMap;
use Fossology\Lib\Dao\AgentDao;
use Fossology\Lib\Dao\ClearingDao;
use Fossology\Lib\Dao\HighlightDao;
use Fossology\Lib\Dao\LicenseDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Data\DecisionTypes;
use Fossology\Lib\Data\Tree\ItemTreeBounds;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Test\TestPgDb;

use Mockery as M;

include_once(__DIR__.'/../../../lib/php/Test/Agent/AgentTestMockHelper.php');
include_once(__DIR__.'/SchedulerTestRunnerCli.php');
include_once(__DIR__.'/SchedulerTestRunnerMock.php');

class SchedulerTest extends \PHPUnit_Framework_TestCase
{
  /** @var TestPgDb */
  private $testDb;
  /** @var DbManager */
  private $dbManager;
  /** @var LicenseDao */
  private $licenseDao;
  /** @var ClearingDao */
  private $clearingDao;
  /** @var ClearingDecisionProcessor */
  private $clearingDecisionProcessor;
  /** @var AgentLicenseEventProcessor */
  private $agentLicenseEventProcessor;
  /** @var UploadDao */
  private $uploadDao;
  /** @var HighlightDao */
  private $highlightDao;
  
  /** @var SchedulerTestRunnerCli */
  private $runnerCli;
  
  /** @var SchedulerTestRunnerMock */
  private $runnerMock;

  public function setUp()
  {
    $this->testDb = new TestPgDb("deciderSched".time());
    $this->dbManager = $this->testDb->getDbManager();

    $this->licenseDao = new LicenseDao($this->dbManager);
    $this->uploadDao = new UploadDao($this->dbManager);
    $this->highlightDao = new HighlightDao($this->dbManager);
    $logger = M::mock('Monolog\Logger');
    $agentDao = new AgentDao($this->dbManager, $logger);
    $this->agentLicenseEventProcessor = new AgentLicenseEventProcessor($this->licenseDao, $agentDao);
    $clearingEventProcessor = new ClearingEventProcessor();
    $this->clearingDao = new ClearingDao($this->dbManager, $this->uploadDao);
    $this->clearingDecisionProcessor = new ClearingDecisionProcessor($this->clearingDao, $this->agentLicenseEventProcessor, $clearingEventProcessor, $this->dbManager);

    $this->runnerMock = new SchedulerTestRunnerMock($this->dbManager, $agentDao, $this->clearingDao, $this->uploadDao, $this->highlightDao, $this->clearingDecisionProcessor, $this->agentLicenseEventProcessor);
    $this->runnerCli = new SchedulerTestRunnerCli($this->testDb);
  }

  public function tearDown()
  {
    $this->testDb = null;
    $this->dbManager = null;
    $this->licenseDao = null;
    $this->highlightDao = null;
    $this->clearingDao = null;
  }

  private function setUpRepo()
  {
    $sysConf = $this->testDb->getFossSysConf();

    $confFile = $sysConf."/fossology.conf";
    $fakeInstallationDir = "$sysConf/inst";
    $libDir = dirname(dirname(dirname(__DIR__)))."/lib";

    $config = "[FOSSOLOGY]\ndepth = 0\npath = $sysConf/repo\n[DIRECTORIES]\nMODDIR = $fakeInstallationDir";
    file_put_contents($confFile, $config);
    if (!is_dir($fakeInstallationDir))
    {
      mkdir($fakeInstallationDir, 0777, true);
      system("ln -sf $libDir $fakeInstallationDir/lib");
      if (!is_dir("$fakeInstallationDir/www/ui")) {
        mkdir("$fakeInstallationDir/www/ui/", 0777, true);
        touch("$fakeInstallationDir/www/ui/ui-menus.php");
      }
    }

    $topDir = dirname(dirname(dirname(dirname(__DIR__))));
    system("install -D $topDir/VERSION $sysConf");

    $testRepoDir = "$libDir/php/Test/";
    system("cp -a $testRepoDir/repo $sysConf/");
  }

  private function rmRepo()
  {
    $sysConf = $this->testDb->getFossSysConf();
    system("rm $sysConf/repo -rf");
    system("rm $sysConf/inst -rf");
    unlink($sysConf."/VERSION");
    unlink($sysConf."/fossology.conf");
  }

  private function setUpTables()
  {
    $this->testDb->createPlainTables(array('upload','upload_reuse','uploadtree','uploadtree_a','license_ref','license_ref_bulk','clearing_decision','clearing_decision_event','clearing_event','license_file','highlight','highlight_bulk','agent','pfile','ars_master','users','group_user_member','license_map'),false);
    $this->testDb->createSequences(array('agent_agent_pk_seq','pfile_pfile_pk_seq','upload_upload_pk_seq','nomos_ars_ars_pk_seq','license_file_fl_pk_seq','license_ref_rf_pk_seq','license_ref_bulk_lrb_pk_seq','clearing_decision_clearing_id_seq','clearing_event_clearing_event_pk_seq','FileLicense_pkey'),false);
    $this->testDb->createViews(array('license_file_ref'),false);
    $this->testDb->createConstraints(array('agent_pkey','pfile_pkey','upload_pkey_idx','clearing_event_pkey'),false);
    $this->testDb->alterTables(array('agent','pfile','upload','ars_master','license_ref_bulk','clearing_event','clearing_decision','license_file','highlight'),false);
    $this->testDb->getDbManager()->queryOnce("alter table uploadtree_a inherit uploadtree");
    $this->testDb->createInheritedTables();
    $this->testDb->createInheritedArsTables(array('nomos','monk','copyright'));

    $this->testDb->insertData(array('pfile','upload','uploadtree_a','users','group_user_member','agent','license_file','nomos_ars','monk_ars','copyright_ars'), false);
    $this->testDb->insertData_license_ref();

    $this->testDb->resetSequenceAsMaxOf('agent_agent_pk_seq', 'agent', 'agent_pk');
  }

  private function getHeartCount($output)
  {
    $matches = array();
    if (preg_match("/.*HEART: ([0-9]*).*/", $output, $matches))
    {
      return intval($matches[1]);
    } else
    {
      return -1;
    }
  }

  /** @group Functional */
  public function testDeciderMockScanWithNoEventsAndOnlyNomosShouldNotMakeADecision()
  {
    $this->runnerDeciderScanWithNoEventsAndOnlyNomosShouldNotMakeADecision($this->runnerMock);
  }

  /** @group Functional */
  public function testDeciderRealScanWithNoEventsAndOnlyNomosShouldNotMakeADecision()
  {
    $this->runnerDeciderScanWithNoEventsAndOnlyNomosShouldNotMakeADecision($this->runnerCli);
  }

  private function runnerDeciderScanWithNoEventsAndOnlyNomosShouldNotMakeADecision($runner)
  {
    $this->setUpTables();
    $this->setUpRepo();

    $licenseRef1 = $this->licenseDao->getLicenseByShortName("GPL-3.0")->getRef();

    $licId1 = $licenseRef1->getId();

    $agentNomosId = 6;
    $agentMonkId = 5;
    $pfile = 4;

    $this->dbManager->queryOnce("DELETE FROM license_file");
    $this->dbManager->queryOnce("INSERT INTO license_file (fl_pk,rf_fk,pfile_fk,agent_fk) VALUES(12222,$licId1,$pfile,$agentNomosId)");
    $this->dbManager->queryOnce("INSERT INTO highlight (fl_fk,start,len) VALUES(12222,12,3)");
    $this->dbManager->queryOnce("INSERT INTO highlight (fl_fk,start,len) VALUES(12222,18,3)");

    list($success,$output,$retCode) = $runner->run($uploadId=2, $userId=6, $groupId=4, $jobId=31, $args="");

    $this->assertTrue($success, 'cannot run runner');
    $this->assertEquals($retCode, 0, 'decider failed (did you make test?): '.$output);

    assertThat($this->getHeartCount($output), equalTo(0));

    $uploadBounds = $this->uploadDao->getParentItemBounds($uploadId);
    $decisions = $this->clearingDao->getFileClearingsFolder($uploadBounds, $groupId);
    assertThat($decisions, is(arrayWithSize(0)));

    $this->rmRepo();
  }

  /** @group Functional */
  public function testDeciderMockScanWithNoEventsAndNomosContainedInMonkShouldMakeADecision()
  {
    $this->runnerDeciderScanWithNoEventsAndNomosContainedInMonkShouldMakeADecision($this->runnerMock);
  }

  /** @group Functional */
  public function testDeciderRealScanWithNoEventsAndNomosContainedInMonkShouldMakeADecision()
  {
    $this->runnerDeciderScanWithNoEventsAndNomosContainedInMonkShouldMakeADecision($this->runnerCli);
  }

  private function runnerDeciderScanWithNoEventsAndNomosContainedInMonkShouldMakeADecision($runner)
  {
    $this->setUpTables();
    $this->setUpRepo();

    $licenseRef1 = $this->licenseDao->getLicenseByShortName("GPL-3.0")->getRef();

    $licId1 = $licenseRef1->getId();

    $agentNomosId = 6;
    $agentMonkId = 5;
    $pfile = 4;

    $this->dbManager->queryOnce("DELETE FROM license_file");
    $this->dbManager->queryOnce("INSERT INTO license_file (fl_pk,rf_fk,pfile_fk,agent_fk) VALUES(12222,$licId1,$pfile,$agentNomosId)");
    $this->dbManager->queryOnce("INSERT INTO highlight (fl_fk,start,len) VALUES(12222,12,3)");
    $this->dbManager->queryOnce("INSERT INTO highlight (fl_fk,start,len) VALUES(12222,18,3)");

    $this->dbManager->queryOnce("INSERT INTO license_file (fl_pk,rf_fk,pfile_fk,agent_fk) VALUES(12223,$licId1,$pfile,$agentMonkId)");
    $this->dbManager->queryOnce("INSERT INTO highlight (fl_fk,start,len) VALUES(12223,11,2)");
    $this->dbManager->queryOnce("INSERT INTO highlight (fl_fk,start,len) VALUES(12223,13,19)");

    list($success,$output,$retCode) = $runner->run($uploadId=2, $userId=6, $groupId=4, $jobId=31, $args="");

    $this->assertTrue($success, 'cannot run runner');
    $this->assertEquals($retCode, 0, 'decider failed (did you make test?): '.$output);

    assertThat($this->getHeartCount($output), equalTo(1));

    $uploadBounds = $this->uploadDao->getParentItemBounds($uploadId);
    $decisions = $this->clearingDao->getFileClearingsFolder($uploadBounds, $groupId);
    assertThat($decisions, is(arrayWithSize(1)));

    $this->rmRepo();
  }

  /** @group Functional */
  public function testDeciderMockScanWithNoEventsAndNomosNotContainedInMonkShouldNotMakeADecision()
  {
    $this->runnerDeciderScanWithNoEventsAndNomosNotContainedInMonkShouldNotMakeADecision($this->runnerMock);
  }

  /** @group Functional */
  public function testDeciderRealScanWithNoEventsAndNomosNotContainedInMonkShouldNotMakeADecision()
  {
    $this->runnerDeciderScanWithNoEventsAndNomosNotContainedInMonkShouldNotMakeADecision($this->runnerCli);
  }

  private function runnerDeciderScanWithNoEventsAndNomosNotContainedInMonkShouldNotMakeADecision($runner)
  {
    $this->setUpTables();
    $this->setUpRepo();

    $licenseRef1 = $this->licenseDao->getLicenseByShortName("GPL-3.0")->getRef();

    $licId1 = $licenseRef1->getId();

    $agentNomosId = 6;
    $agentMonkId = 5;
    $pfile = 4;

    $this->dbManager->queryOnce("DELETE FROM license_file");
    $this->dbManager->queryOnce("INSERT INTO license_file (fl_pk,rf_fk,pfile_fk,agent_fk) VALUES(12222,$licId1,$pfile,$agentNomosId)");
    $this->dbManager->queryOnce("INSERT INTO highlight (fl_fk,start,len) VALUES(12222,10,3)");
    $this->dbManager->queryOnce("INSERT INTO highlight (fl_fk,start,len) VALUES(12222,18,3)");

    $this->dbManager->queryOnce("INSERT INTO license_file (fl_pk,rf_fk,pfile_fk,agent_fk) VALUES(12223,$licId1,$pfile,$agentMonkId)");
    $this->dbManager->queryOnce("INSERT INTO highlight (fl_fk,start,len) VALUES(12223,11,2)");
    $this->dbManager->queryOnce("INSERT INTO highlight (fl_fk,start,len) VALUES(12223,13,19)");

    list($success,$output,$retCode) = $runner->run($uploadId=2, $userId=6, $groupId=4, $jobId=31, $args="");

    $this->assertTrue($success, 'cannot run runner');
    $this->assertEquals($retCode, 0, 'decider failed (did you make test?): '.$output);

    assertThat($this->getHeartCount($output), equalTo(0));

    $uploadBounds = $this->uploadDao->getParentItemBounds($uploadId);
    $decisions = $this->clearingDao->getFileClearingsFolder($uploadBounds, $groupId);
    assertThat($decisions, is(arrayWithSize(0)));

    $this->rmRepo();
  }

  /** @group Functional */
  public function testDeciderMockScanWithNoEventsAndNomosContainedInOneOfTwoEqualsMonkShouldMakeADecision()
  {
    $this->runnerDeciderScanWithNoEventsAndNomosContainedInOneOfTwoEqualsMonkShouldMakeADecision($this->runnerMock);
  }

  /** @group Functional */
  public function testDeciderRealScanWithNoEventsAndNomosContainedInOneOfTwoEqualsMonkShouldMakeADecision()
  {
    $this->runnerDeciderScanWithNoEventsAndNomosContainedInOneOfTwoEqualsMonkShouldMakeADecision($this->runnerCli);
  }

  private function runnerDeciderScanWithNoEventsAndNomosContainedInOneOfTwoEqualsMonkShouldMakeADecision($runner)
  {
    $this->setUpTables();
    $this->setUpRepo();

    $licenseRef1 = $this->licenseDao->getLicenseByShortName("GPL-3.0")->getRef();

    $licId1 = $licenseRef1->getId();

    $agentNomosId = 6;
    $agentMonkId = 5;
    $pfile = 4;

    $this->dbManager->queryOnce("DELETE FROM license_file");
    $this->dbManager->queryOnce("INSERT INTO license_file (fl_pk,rf_fk,pfile_fk,agent_fk) VALUES(12222,$licId1,$pfile,$agentNomosId)");
    $this->dbManager->queryOnce("INSERT INTO highlight (fl_fk,start,len) VALUES(12222,10,3)");
    $this->dbManager->queryOnce("INSERT INTO highlight (fl_fk,start,len) VALUES(12222,18,3)");

    $this->dbManager->queryOnce("INSERT INTO license_file (fl_pk,rf_fk,pfile_fk,agent_fk) VALUES(12223,$licId1,$pfile,$agentMonkId)");
    $this->dbManager->queryOnce("INSERT INTO highlight (fl_fk,start,len) VALUES(12223,11,2)");
    $this->dbManager->queryOnce("INSERT INTO highlight (fl_fk,start,len) VALUES(12223,13,19)");

    $this->dbManager->queryOnce("INSERT INTO license_file (fl_pk,rf_fk,pfile_fk,agent_fk) VALUES(12224,$licId1,$pfile,$agentMonkId)");
    $this->dbManager->queryOnce("INSERT INTO highlight (fl_fk,start,len) VALUES(12224,9,2)");
    $this->dbManager->queryOnce("INSERT INTO highlight (fl_fk,start,len) VALUES(12224,18,19)");

    list($success,$output,$retCode) = $runner->run($uploadId=2, $userId=6, $groupId=4, $jobId=31, $args="");

    $this->assertTrue($success, 'cannot run runner');
    $this->assertEquals($retCode, 0, 'decider failed (did you make test?): '.$output);

    assertThat($this->getHeartCount($output), equalTo(1));

    $uploadBounds = $this->uploadDao->getParentItemBounds($uploadId);
    $decisions = $this->clearingDao->getFileClearingsFolder($uploadBounds, $groupId);
    assertThat($decisions, is(arrayWithSize(1)));

    $this->rmRepo();
  }

  /** @group Functional */
  public function testDeciderMockScanWithNoEventsAndNomosContainedInMonkWithMappedLicenseShouldMakeADecision()
  {
    $this->runnerDeciderScanWithNoEventsAndNomosContainedInMonkWithMappedLicenseShouldMakeADecision($this->runnerMock);
  }

  /** @group Functional */
  public function testDeciderRealScanWithNoEventsAndNomosContainedInMonkWithMappedLicenseShouldMakeADecision()
  {
    $this->runnerDeciderScanWithNoEventsAndNomosContainedInMonkWithMappedLicenseShouldMakeADecision($this->runnerCli);
  }

  private function runnerDeciderScanWithNoEventsAndNomosContainedInMonkWithMappedLicenseShouldMakeADecision($runner)
  {
    $this->setUpTables();
    $this->setUpRepo();

    $licenseRef1 = $this->licenseDao->getLicenseByShortName("GPL-3.0")->getRef();
    $licenseRef2 = $this->licenseDao->getLicenseByShortName("GPL-1.0")->getRef();
    $licenseRef3 = $this->licenseDao->getLicenseByShortName("APL-1.0")->getRef();

    $licId1 = $licenseRef1->getId();
    $licId2 = $licenseRef2->getId();
    $licId3 = $licenseRef3->getId();

    $agentNomosId = 6;
    $agentMonkId = 5;
    $pfile = 4;

    $this->dbManager->queryOnce("INSERT INTO license_map(rf_fk, rf_parent, usage) VALUES ($licId2, $licId1, ".LicenseMap::CONCLUSION.")");
    $this->dbManager->queryOnce("INSERT INTO license_map(rf_fk, rf_parent, usage) VALUES ($licId3, $licId1, ".LicenseMap::CONCLUSION.")");

    $this->dbManager->queryOnce("DELETE FROM license_file");
    $this->dbManager->queryOnce("INSERT INTO license_file (fl_pk,rf_fk,pfile_fk,agent_fk) VALUES(12222,$licId1,$pfile,$agentNomosId)");
    $this->dbManager->queryOnce("INSERT INTO highlight (fl_fk,start,len) VALUES(12222,10,3)");
    $this->dbManager->queryOnce("INSERT INTO highlight (fl_fk,start,len) VALUES(12222,18,3)");

    $this->dbManager->queryOnce("INSERT INTO license_file (fl_pk,rf_fk,pfile_fk,agent_fk) VALUES(12223,$licId2,$pfile,$agentMonkId)");
    $this->dbManager->queryOnce("INSERT INTO highlight (fl_fk,start,len) VALUES(12223,6,2)");
    $this->dbManager->queryOnce("INSERT INTO highlight (fl_fk,start,len) VALUES(12223,13,19)");

    $this->dbManager->queryOnce("INSERT INTO license_file (fl_pk,rf_fk,pfile_fk,agent_fk) VALUES(12224,$licId3,$pfile,$agentMonkId)");
    $this->dbManager->queryOnce("INSERT INTO highlight (fl_fk,start,len) VALUES(12224,9,2)");
    $this->dbManager->queryOnce("INSERT INTO highlight (fl_fk,start,len) VALUES(12224,13,19)");

    list($success,$output,$retCode) = $runner->run($uploadId=2, $userId=6, $groupId=4, $jobId=31, $args="");

    $this->assertTrue($success, 'cannot run runner');
    $this->assertEquals($retCode, 0, 'decider failed (did you make test?): '.$output);

    assertThat($this->getHeartCount($output), equalTo(1));

    $uploadBounds = $this->uploadDao->getParentItemBounds($uploadId);
    $decisions = $this->clearingDao->getFileClearingsFolder($uploadBounds, $groupId);
    assertThat($decisions, is(arrayWithSize(1)));

    $this->rmRepo();
  }

  /** @group Functional */
  public function testDeciderMockScanWithNoEventsAndNomosContainedInMonkWithButWithOtherAgentMatchShouldNotMakeADecision()
  {
    $this->runnerDeciderScanWithNoEventsAndNomosContainedInMonkWithButWithOtherAgentMatchShouldNotMakeADecision($this->runnerMock);
  }

  /** @group Functional */
  public function testDeciderRealScanWithNoEventsAndNomosContainedInMonkWithButWithOtherAgentMatchShouldNotMakeADecision()
  {
    $this->runnerDeciderScanWithNoEventsAndNomosContainedInMonkWithButWithOtherAgentMatchShouldNotMakeADecision($this->runnerCli);
  }

  private function runnerDeciderScanWithNoEventsAndNomosContainedInMonkWithButWithOtherAgentMatchShouldNotMakeADecision($runner)
  {
    $this->setUpTables();
    $this->setUpRepo();

    $licenseRef1 = $this->licenseDao->getLicenseByShortName("GPL-3.0")->getRef();
    $licenseRef2 = $this->licenseDao->getLicenseByShortName("GPL-1.0")->getRef();
    $licenseRef3 = $this->licenseDao->getLicenseByShortName("APL-1.0")->getRef();

    $licId1 = $licenseRef1->getId();
    $licId2 = $licenseRef2->getId();
    $licId3 = $licenseRef3->getId();

    $agentNomosId = 6;
    $agentMonkId = 5;
    $agentOther = 8;
    $pfile = 4;

    $this->dbManager->queryOnce("INSERT INTO license_map(rf_fk, rf_parent, usage) VALUES ($licId2, $licId1, ".LicenseMap::CONCLUSION.")");

    $this->dbManager->queryOnce("DELETE FROM license_file");
    $this->dbManager->queryOnce("INSERT INTO license_file (fl_pk,rf_fk,pfile_fk,agent_fk) VALUES(12222,$licId1,$pfile,$agentNomosId)");
    $this->dbManager->queryOnce("INSERT INTO highlight (fl_fk,start,len) VALUES(12222,10,3)");
    $this->dbManager->queryOnce("INSERT INTO highlight (fl_fk,start,len) VALUES(12222,18,3)");

    $this->dbManager->queryOnce("INSERT INTO license_file (fl_pk,rf_fk,pfile_fk,agent_fk) VALUES(12223,$licId2,$pfile,$agentMonkId)");
    $this->dbManager->queryOnce("INSERT INTO highlight (fl_fk,start,len) VALUES(12223,6,2)");
    $this->dbManager->queryOnce("INSERT INTO highlight (fl_fk,start,len) VALUES(12223,13,19)");

    $this->dbManager->queryOnce("INSERT INTO license_file (fl_pk,rf_fk,pfile_fk,agent_fk) VALUES(12224,$licId3,$pfile,$agentOther)");
    $this->dbManager->queryOnce("INSERT INTO highlight (fl_fk,start,len) VALUES(12224,9,2)");
    $this->dbManager->queryOnce("INSERT INTO highlight (fl_fk,start,len) VALUES(12224,13,19)");

    list($success,$output,$retCode) = $runner->run($uploadId=2, $userId=6, $groupId=4, $jobId=31, $args="");

    $this->assertTrue($success, 'cannot run runner');
    $this->assertEquals($retCode, 0, 'decider failed (did you make test?): '.$output);

    assertThat($this->getHeartCount($output), equalTo(0));

    $uploadBounds = $this->uploadDao->getParentItemBounds($uploadId);
    $decisions = $this->clearingDao->getFileClearingsFolder($uploadBounds, $groupId);
    assertThat($decisions, is(arrayWithSize(0)));

    $this->rmRepo();
  }

  /** @group Functional */
  public function testDeciderMockScanWithNoEventsAndNomosContainedInMonkWithButWithOtherAgentMatchForSameLicenseShouldMakeADecision()
  {
    $this->runnerDeciderScanWithNoEventsAndNomosContainedInMonkWithButWithOtherAgentMatchForSameLicenseShouldMakeADecision($this->runnerMock);
  }

  /** @group Functional */
  public function testDeciderRealScanWithNoEventsAndNomosContainedInMonkWithButWithOtherAgentMatchForSameLicenseShouldMakeADecision()
  {
    $this->runnerDeciderScanWithNoEventsAndNomosContainedInMonkWithButWithOtherAgentMatchForSameLicenseShouldMakeADecision($this->runnerCli);
  }

  private function runnerDeciderScanWithNoEventsAndNomosContainedInMonkWithButWithOtherAgentMatchForSameLicenseShouldMakeADecision($runner)
  {
    $this->setUpTables();
    $this->setUpRepo();

    $licenseRef1 = $this->licenseDao->getLicenseByShortName("GPL-3.0")->getRef();
    $licenseRef2 = $this->licenseDao->getLicenseByShortName("GPL-1.0")->getRef();
    $licenseRef3 = $this->licenseDao->getLicenseByShortName("APL-1.0")->getRef();

    $licId1 = $licenseRef1->getId();
    $licId2 = $licenseRef2->getId();
    $licId3 = $licenseRef3->getId();

    $agentNomosId = 6;
    $agentMonkId = 5;
    $agentOther = 8;
    $pfile = 4;

    $this->dbManager->queryOnce("INSERT INTO license_map(rf_fk, rf_parent, usage) VALUES ($licId2, $licId1, ".LicenseMap::CONCLUSION.")");
    $this->dbManager->queryOnce("INSERT INTO license_map(rf_fk, rf_parent, usage) VALUES ($licId3, $licId1, ".LicenseMap::CONCLUSION.")");

    $this->dbManager->queryOnce("DELETE FROM license_file");
    $this->dbManager->queryOnce("INSERT INTO license_file (fl_pk,rf_fk,pfile_fk,agent_fk) VALUES(12222,$licId1,$pfile,$agentNomosId)");
    $this->dbManager->queryOnce("INSERT INTO highlight (fl_fk,start,len) VALUES(12222,10,3)");
    $this->dbManager->queryOnce("INSERT INTO highlight (fl_fk,start,len) VALUES(12222,18,3)");

    $this->dbManager->queryOnce("INSERT INTO license_file (fl_pk,rf_fk,pfile_fk,agent_fk) VALUES(12223,$licId2,$pfile,$agentMonkId)");
    $this->dbManager->queryOnce("INSERT INTO highlight (fl_fk,start,len) VALUES(12223,6,2)");
    $this->dbManager->queryOnce("INSERT INTO highlight (fl_fk,start,len) VALUES(12223,13,19)");

    $this->dbManager->queryOnce("INSERT INTO license_file (fl_pk,rf_fk,pfile_fk,agent_fk) VALUES(12224,$licId3,$pfile,$agentOther)");
    $this->dbManager->queryOnce("INSERT INTO highlight (fl_fk,start,len) VALUES(12224,9,2)");
    $this->dbManager->queryOnce("INSERT INTO highlight (fl_fk,start,len) VALUES(12224,13,19)");

    list($success,$output,$retCode) = $runner->run($uploadId=2, $userId=6, $groupId=4, $jobId=31, $args="");

    $this->assertTrue($success, 'cannot run runner');
    $this->assertEquals($retCode, 0, 'decider failed (did you make test?): '.$output);

    assertThat($this->getHeartCount($output), equalTo(1));

    $uploadBounds = $this->uploadDao->getParentItemBounds($uploadId);
    $decisions = $this->clearingDao->getFileClearingsFolder($uploadBounds, $groupId);
    assertThat($decisions, is(arrayWithSize(1)));

    $this->rmRepo();
  }
}
