<?php
/*
 SPDX-FileCopyrightText: © 2014-2019 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @namespace Fossology::Decider::Test
 * Namespace for Decider test cases
 */
namespace Fossology\Decider\Test;

use Fossology\Lib\BusinessRules\AgentLicenseEventProcessor;
use Fossology\Lib\BusinessRules\ClearingDecisionProcessor;
use Fossology\Lib\BusinessRules\ClearingEventProcessor;
use Fossology\Lib\BusinessRules\LicenseMap;
use Fossology\Lib\Dao\AgentDao;
use Fossology\Lib\Dao\ClearingDao;
use Fossology\Lib\Dao\CompatibilityDao;
use Fossology\Lib\Dao\CopyrightDao;
use Fossology\Lib\Dao\HighlightDao;
use Fossology\Lib\Dao\LicenseDao;
use Fossology\Lib\Dao\ShowJobsDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Dao\UploadPermissionDao;
use Fossology\Lib\Data\DecisionScopes;
use Fossology\Lib\Data\DecisionTypes;
use Fossology\Lib\Data\Tree\ItemTreeBounds;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Test\TestInstaller;
use Fossology\Lib\Test\TestPgDb;
use Mockery as M;

include_once(__DIR__.'/../../../lib/php/Test/Agent/AgentTestMockHelper.php');
include_once(__DIR__.'/SchedulerTestRunnerCli.php');
include_once(__DIR__.'/SchedulerTestRunnerMock.php');

/**
 * @class SchedulerTest
 * @brief Test cases for interaction between decider and scheduler
 */
class SchedulerTest extends \PHPUnit\Framework\TestCase
{
  /** @var TestPgDb */
  private $testDb;
  /** @var DbManager */
  private $dbManager;
  /** @var TestInstaller */
  private $testInstaller;

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
  /** @var UploadPermissionDao */
  private $uploadPermDao;
  /** @var HighlightDao */
  private $highlightDao;
  /** @var ShowJobsDao */
  private $showJobsDao;
  /** @var CopyrightDao $copyrightDao */
  private $copyrightDao;
  /** @var CompatibilityDao */
  private $compatibilityDao;
  /** @var SchedulerTestRunnerCli */
  private $runnerCli;
  /** @var SchedulerTestRunnerMock */
  private $runnerMock;

  /**
   * @brief Setup the objects, database and repository
   */
  protected function setUp() : void
  {
    $this->testDb = new TestPgDb("deciderSched");
    $this->dbManager = $this->testDb->getDbManager();
    $this->testDb->setupSysconfig();

    $this->licenseDao = new LicenseDao($this->dbManager);
    $logger = M::mock('Monolog\Logger');
    $this->uploadPermDao = \Mockery::mock(UploadPermissionDao::class);
    $this->uploadDao = new UploadDao($this->dbManager, $logger, $this->uploadPermDao);
    $this->copyrightDao = new CopyrightDao($this->dbManager, $this->uploadDao);
    $this->highlightDao = new HighlightDao($this->dbManager);
    $agentDao = new AgentDao($this->dbManager, $logger);
    $this->agentLicenseEventProcessor = new AgentLicenseEventProcessor($this->licenseDao, $agentDao);
    $clearingEventProcessor = new ClearingEventProcessor();
    $this->clearingDao = new ClearingDao($this->dbManager, $this->uploadDao);
    $this->showJobsDao = new ShowJobsDao($this->dbManager, $this->uploadDao);
    $this->clearingDecisionProcessor = new ClearingDecisionProcessor($this->clearingDao, $this->agentLicenseEventProcessor, $clearingEventProcessor, $this->dbManager);
    $this->copyrightDao = M::mock(CopyrightDao::class);
    $this->compatibilityDao = M::mock(CompatibilityDao::class);

    $this->compatibilityDao->shouldReceive('getCompatibilityForFile')->andReturn(true);

    global $container;
    $container = M::mock('ContainerBuilder');
    $container->shouldReceive('get')->withArgs(array('db.manager'))->andReturn($this->dbManager);

    $this->runnerMock = new SchedulerTestRunnerMock($this->dbManager, $agentDao,
      $this->clearingDao, $this->uploadDao, $this->highlightDao,
      $this->showJobsDao, $this->copyrightDao, $this->compatibilityDao,
      $this->licenseDao, $this->clearingDecisionProcessor,
      $this->agentLicenseEventProcessor);
    $this->runnerCli = new SchedulerTestRunnerCli($this->testDb);
  }

  /**
   * @brief Destroy objects, database and repository
   */
  protected function tearDown() : void
  {
    $this->testDb->fullDestruct();
    $this->testDb = null;
    $this->dbManager = null;
    $this->licenseDao = null;
    $this->highlightDao = null;
    $this->showJobsDao = null;
    $this->clearingDao = null;
    $this->copyrightDao = null;
    M::close();
  }

  /**
   * @brief Setup test repository
   */
  private function setUpRepo()
  {
    $sysConf = $this->testDb->getFossSysConf();
    $this->testInstaller = new TestInstaller($sysConf);
    $this->testInstaller->init();
    $this->testInstaller->cpRepo();
  }

  /**
   * @brief Destroy test repository
   */
  private function rmRepo()
  {
    $this->testInstaller->rmRepo();
    $this->testInstaller->clear();
  }

  /**
   * @brief Create test tables required by agent
   */
  private function setUpTables()
  {
    $this->testDb->createPlainTables(array('upload','upload_reuse','uploadtree','uploadtree_a','license_ref','license_ref_bulk',
      'license_set_bulk','clearing_decision','clearing_decision_event','clearing_event','license_file','highlight',
      'highlight_keyword','agent','pfile','ars_master','users','group_user_member','license_map','jobqueue','job',
      'report_info','license_rules', 'license_expression'), false);
    $this->testDb->createSequences(array('agent_agent_pk_seq','pfile_pfile_pk_seq','upload_upload_pk_seq','nomos_ars_ars_pk_seq',
      'license_file_fl_pk_seq','license_ref_rf_pk_seq','license_ref_bulk_lrb_pk_seq',
      'clearing_decision_clearing_decision_pk_seq','clearing_event_clearing_event_pk_seq','FileLicense_pkey',
      'jobqueue_jq_pk_seq','job_job_pk_seq','license_rules_lr_pk_seq'),false);
    $this->testDb->createViews(array('license_file_ref'),false);
    $this->testDb->createConstraints(array('agent_pkey','pfile_pkey','upload_pkey_idx','clearing_event_pkey','jobqueue_pkey','license_rules_pkey'),false);
    $this->testDb->alterTables(array('agent','pfile','upload','ars_master','license_ref_bulk','license_set_bulk','clearing_event',
      'clearing_decision','license_file', 'license_ref','highlight','jobqueue','job','license_rules', 'license_expression'),false);
    $this->testDb->createInheritedTables();
    $this->testDb->createInheritedArsTables(array('nomos','monk','copyright'));

    $this->testDb->insertData(array('pfile','upload','uploadtree_a','users','group_user_member','agent','license_file',
      'nomos_ars','monk_ars','copyright_ars'), false);
    $this->testDb->insertData_license_ref(80);

    $this->testDb->resetSequenceAsMaxOf('agent_agent_pk_seq', 'agent', 'agent_pk');
  }

  /**
   * @brief Get the heart count value from the agent output
   * @param string $output Output from agent
   * @return int Heart count value, -1 on failure
   */
  private function getHeartCount($output)
  {
    $matches = array();
    if (preg_match("/.*HEART: ([0-9]*).*/", $output, $matches)) {
      return intval($matches[1]);
    } else {
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

    $licenseRef1 = $this->licenseDao->getLicenseByShortName("SPL-1.0")->getRef();

    $licId1 = $licenseRef1->getId();

    $agentNomosId = 6;
    $pfile = 4;
    $jobId = 15;

    $this->dbManager->queryOnce("DELETE FROM license_file");
    $this->dbManager->queryOnce("INSERT INTO license_file (fl_pk,rf_fk,pfile_fk,agent_fk) VALUES(12222,$licId1,$pfile,$agentNomosId)");
    $this->dbManager->queryOnce("INSERT INTO highlight (fl_fk,start,len) VALUES(12222,12,3)");
    $this->dbManager->queryOnce("INSERT INTO highlight (fl_fk,start,len) VALUES(12222,18,3)");
    $this->dbManager->queryOnce("INSERT INTO jobqueue (jq_pk, jq_job_fk, jq_type, jq_args, jq_starttime, jq_endtime, jq_endtext, jq_end_bits, jq_schedinfo, jq_itemsprocessed, jq_log, jq_runonpfile, jq_host, jq_cmd_args)"
            . " VALUES ($jobId, 2, 'decider', '2', '2014-08-07 09:57:27.718312+00', NULL, '', 0, NULL, 6, NULL, NULL, NULL, NULL)");

    list($success,$output,$retCode) = $runner->run($uploadId=2, $userId=6, $groupId=4, $jobId, $args='');

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

    $licenseRef1 = $this->licenseDao->getLicenseByShortName("SPL-1.0")->getRef();

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

    $licenseRef1 = $this->licenseDao->getLicenseByShortName("SPL-1.0")->getRef();

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

    $licenseRef1 = $this->licenseDao->getLicenseByShortName("SPL-1.0")->getRef();

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

    $licenseRef1 = $this->licenseDao->getLicenseByShortName("SPL-1.0")->getRef();
    $licenseRef2 = $this->licenseDao->getLicenseByShortName("BSL-1.0")->getRef();
    $licenseRef3 = $this->licenseDao->getLicenseByShortName("ZPL-1.1")->getRef();

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

    $licenseRef1 = $this->licenseDao->getLicenseByShortName("SPL-1.0")->getRef();
    $licenseRef2 = $this->licenseDao->getLicenseByShortName("BSL-1.0")->getRef();
    $licenseRef3 = $this->licenseDao->getLicenseByShortName("ZPL-1.1")->getRef();

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

    $licenseRef1 = $this->licenseDao->getLicenseByShortName("SPL-1.0")->getRef();
    $licenseRef2 = $this->licenseDao->getLicenseByShortName("BSL-1.0")->getRef();
    $licenseRef3 = $this->licenseDao->getLicenseByShortName("ZPL-1.1")->getRef();

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


  /** @group Functional */
  public function testDeciderRealBulkReuseShouldScheduleMonkBulk()
  {
    $this->runnerBulkReuseShouldScheduleMonkBulk($this->runnerMock);
  }

  private function  runnerBulkReuseShouldScheduleMonkBulk($runner)
  {
    $this->setUpTables();
    $this->setUpRepo();

    $licenseRef1 = $this->licenseDao->getLicenseByShortName("SPL-1.0")->getRef();
    $licId1 = $licenseRef1->getId();

    $agentBulk = 6;
    $pfile = 4;
    $jobId = 16;
    $otherJob = 333;
    $groupId = 2;

    $this->dbManager->queryOnce("DELETE FROM license_file");
    $this->dbManager->queryOnce("INSERT INTO license_file (fl_pk,rf_fk,pfile_fk,agent_fk) VALUES(12222,$licId1,$pfile,$agentBulk)");
    $this->dbManager->queryOnce("INSERT INTO highlight (fl_fk,start,len) VALUES(12222,12,3)");
    $this->dbManager->queryOnce("INSERT INTO jobqueue (jq_pk, jq_job_fk, jq_type, jq_args, jq_starttime, jq_endtime, jq_endtext, jq_end_bits, jq_schedinfo, jq_itemsprocessed, jq_log, jq_runonpfile, jq_host, jq_cmd_args)"
            . " VALUES ($jobId, 2, 'decider', '123456', '2014-08-07 09:57:27.718312+00', NULL, '', 0, NULL, 6, NULL, NULL, NULL, NULL)");

    $this->dbManager->queryOnce("INSERT INTO jobqueue (jq_pk, jq_job_fk, jq_type, jq_args, jq_starttime, jq_endtime, jq_endtext, jq_end_bits, jq_schedinfo, jq_itemsprocessed, jq_log, jq_runonpfile, jq_host, jq_cmd_args)"
            . " VALUES ($jobId-1, $otherJob, 'monkbulk', '123456', '2014-08-07 09:22:22.718312+00', NULL, '', 0, NULL, 6, NULL, NULL, NULL, NULL)");
    $this->dbManager->queryOnce("INSERT INTO job (job_pk, job_queued, job_priority, job_upload_fk, job_user_fk, job_group_fk)"
            . " VALUES ($otherJob, '2014-08-07 09:22:22.718312+00', 0, 1, 2, $groupId)");

    $this->dbManager->queryOnce("INSERT INTO license_ref_bulk (lrb_pk, user_fk, group_fk, rf_text, upload_fk, uploadtree_fk) VALUES (123456, 2, $groupId, 'foo bar', 1, 1)");
    $this->dbManager->queryOnce("INSERT INTO license_set_bulk (lrb_fk, rf_fk, removing) VALUES (123456, $licId1, 'f')");

    $this->dbManager->queryOnce("INSERT INTO upload_reuse (upload_fk, reused_upload_fk, group_fk, reused_group_fk, reuse_mode)"
            . " VALUES (2, 1, $groupId, $groupId, 0)");

    require_once 'HelperPluginMock.php';
    list($success,$output,$retCode) = $runner->run($uploadId=2, $userId=2, $groupId, $jobId, $args='-r4');

    $this->assertTrue($success, 'cannot run runner');
    $this->assertEquals($retCode, 0, 'decider failed (did you make test?): '.$output);

    $this->rmRepo();
  }

  /** @group Functional */
  public function testDeciderRealShouldMakeDecisionAsWipIfUnhandledScannerEvent()
  {
    $this->runnerShouldMakeDecisionAsWipIfUnhandledScannerEvent($this->runnerMock);
  }

  private function runnerShouldMakeDecisionAsWipIfUnhandledScannerEvent($runner)
  {
    $this->setUpTables();
    $this->setUpRepo();
    $monkAgentId = 5;

    $licenseRef1 = $this->licenseDao->getLicenseByShortName("SPL-1.0")->getRef();
    $licId1 = $licenseRef1->getId();

    $pfile = 4;
    $jobId = 16;
    $groupId = 2;
    $userId = 2;
    $itemId = 7;

    $this->dbManager->queryOnce("DELETE FROM license_file");

    /* insert NoLicenseKnown decisions */
    $this->dbManager->queryOnce("INSERT INTO clearing_decision (uploadtree_fk, pfile_fk, user_fk, group_fk, decision_type, scope, date_added)"
            . " VALUES ($itemId, $pfile, $userId, $groupId, ".DecisionTypes::IDENTIFIED.", ".DecisionScopes::ITEM.", '2015-05-04 11:43:18.276425+02')");
    $isWipBeforeDecider = $this->clearingDao->isDecisionCheck($itemId, $groupId, DecisionTypes::WIP);
    assertThat($isWipBeforeDecider, equalTo(false));

    $this->dbManager->queryOnce("INSERT INTO license_file (fl_pk,rf_fk,pfile_fk,agent_fk) VALUES(12222,$licId1,$pfile,$monkAgentId)");
    $this->dbManager->queryOnce("INSERT INTO jobqueue (jq_pk, jq_job_fk, jq_type, jq_args, jq_starttime, jq_endtime, jq_endtext, jq_end_bits, jq_schedinfo, jq_itemsprocessed, jq_log, jq_runonpfile, jq_host, jq_cmd_args)"
            . " VALUES ($jobId, 2, 'decider', '2', '2015-06-07 09:57:27.718312+00', NULL, '', 0, NULL, 6, NULL, NULL, NULL, '-r8')");

    list($success,$output,$retCode) = $runner->run($uploadId=1, $userId, $groupId, $jobId, $args='-r8');

    $this->assertTrue($success, 'cannot run runner');
    $this->assertEquals($retCode, 0, 'decider failed (did you make test?): '.$output);

    $isWip = $this->clearingDao->isDecisionCheck($itemId, $groupId, DecisionTypes::WIP);
    assertThat($isWip, equalTo(true));

    $this->rmRepo();
  }

  /** @group xFunctional */
  public function testDeciderRealShouldMakeNoDecisionForIrrelevantFiles()
  {
    $this->runnerDeciderRealShouldMakeNoDecisionForIrrelevantFiles($this->runnerMock);
  }

  private function runnerDeciderRealShouldMakeNoDecisionForIrrelevantFiles($runner)
  {
    $this->setUpTables();
    $this->setUpRepo();
    $monkAgentId = 5;

    $licenseRef1 = $this->licenseDao->getLicenseByShortName("SPL-1.0")->getRef();
    $licId1 = $licenseRef1->getId();

    $pfile = 4;
    $jobId = 16;
    $groupId = 2;
    $userId = 2;
    $itemId = 7;
    $itemTreeBounds = new ItemTreeBounds($itemId, 'uploadtree_a', $uploadId=1, 15, 16);

    $this->dbManager->queryOnce("DELETE FROM license_file");

    /* insert NoLicenseKnown decisions */
    $this->dbManager->queryOnce("INSERT INTO clearing_decision (clearing_decision_pk, uploadtree_fk, pfile_fk, user_fk, group_fk, decision_type, scope, date_added)"
            . " VALUES (2, $itemId, $pfile, $userId, $groupId, ".DecisionTypes::IRRELEVANT.", ".DecisionScopes::ITEM.", '2015-05-04 11:43:18.276425+02')");
    $lastDecision = $this->clearingDao->getRelevantClearingDecision($itemTreeBounds, $groupId);
    $lastClearingId = $lastDecision->getClearingId();

    $this->dbManager->queryOnce("INSERT INTO license_file (fl_pk,rf_fk,pfile_fk,agent_fk) VALUES(12222,$licId1,$pfile,$monkAgentId)");
    $this->dbManager->queryOnce("INSERT INTO jobqueue (jq_pk, jq_job_fk, jq_type, jq_args, jq_starttime, jq_endtime, jq_endtext, jq_end_bits, jq_schedinfo, jq_itemsprocessed, jq_log, jq_runonpfile, jq_host, jq_cmd_args)"
            . " VALUES ($jobId, 2, 'decider', '2', '2015-06-07 09:57:27.718312+00', NULL, '', 0, NULL, 6, NULL, NULL, NULL, '-r8')");

    list($success,$output,$retCode) = $runner->run($uploadId, $userId, $groupId, $jobId, '');

    $this->assertTrue($success, 'cannot run runner');
    $this->assertEquals($retCode, 0, 'decider failed (did you make test?): '.$output);

    $newDecision = $this->clearingDao->getRelevantClearingDecision($itemTreeBounds, $groupId);
    assertThat($newDecision->getClearingId(), equalTo($lastClearingId));

    $this->rmRepo();
  }
}
