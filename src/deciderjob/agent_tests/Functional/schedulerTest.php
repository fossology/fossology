<?php
/*
 SPDX-FileCopyrightText: © 2014-2015,2019 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @file schedulerTest.php
 * @brief Functional tests for DeciderJobAgent
 * @namespace Fossology::DeciderJob::Test
 * @brief Namespace for decider job test cases
 */
namespace Fossology\DeciderJob\Test;

use Fossology\Lib\BusinessRules\AgentLicenseEventProcessor;
use Fossology\Lib\BusinessRules\ClearingDecisionProcessor;
use Fossology\Lib\BusinessRules\ClearingEventProcessor;
use Fossology\Lib\Dao\AgentDao;
use Fossology\Lib\Dao\ClearingDao;
use Fossology\Lib\Dao\HighlightDao;
use Fossology\Lib\Dao\LicenseDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Dao\UploadPermissionDao;
use Fossology\Lib\Data\ClearingDecision;
use Fossology\Lib\Data\DecisionTypes;
use Fossology\Lib\Data\Tree\ItemTreeBounds;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Test\TestInstaller;
use Fossology\Lib\Test\TestPgDb;
use Mockery as M;

include_once(__DIR__.'/../../../lib/php/Test/Agent/AgentTestMockHelper.php');
include_once(__DIR__.'/../../../lib/php/Plugin/FO_Plugin.php');
include_once(__DIR__.'/SchedulerTestRunnerCli.php');
include_once(__DIR__.'/SchedulerTestRunnerMock.php');

/**
 * @class SchedulerTest
 * @brief Test interactions between scheduler and agent
 */
class SchedulerTest extends \PHPUnit\Framework\TestCase
{
  /** @var TestPgDb $testDb */
  private $testDb;
  /** @var DbManager $dbManager */
  private $dbManager;
  /** @var TestInstaller $testInstaller */
  private $testInstaller;

  /** @var LicenseDao $licenseDao */
  private $licenseDao;
  /** @var ClearingDao $clearingDao */
  private $clearingDao;
  /** @var ClearingDecisionProcessor $clearingDecisionProcessor */
  private $clearingDecisionProcessor;
  /** @var AgentLicenseEventProcessor $agentLicenseEventProcessor */
  private $agentLicenseEventProcessor;
  /** @var UploadDao $uploadDao */
  private $uploadDao;
  /** @var UploadPermissionDao $uploadPermDao */
  private $uploadPermDao;
  /** @var HighlightDao $highlightDao */
  private $highlightDao;

  /** @var SchedulerTestRunnerCli $runnerCli */
  private $runnerCli;
  /** @var SchedulerTestRunnerMock $runnerMock */
  private $runnerMock;

  /**
   * @brief Setup the objects, database and repository
   */
  protected function setUp() : void
  {
    $this->testDb = new TestPgDb("deciderJobSched".time());
    $this->dbManager = $this->testDb->getDbManager();
    $logger = M::mock('Monolog\Logger');
    $this->testDb->setupSysconfig();

    $this->licenseDao = new LicenseDao($this->dbManager);
    $this->uploadPermDao = \Mockery::mock(UploadPermissionDao::class);
    $this->uploadDao = new UploadDao($this->dbManager, $logger, $this->uploadPermDao);
    $this->highlightDao = new HighlightDao($this->dbManager);
    $agentDao = new AgentDao($this->dbManager, $logger);
    $this->agentLicenseEventProcessor = new AgentLicenseEventProcessor($this->licenseDao, $agentDao);
    $clearingEventProcessor = new ClearingEventProcessor();
    $this->clearingDao = new ClearingDao($this->dbManager, $this->uploadDao);
    $this->clearingDecisionProcessor = new ClearingDecisionProcessor($this->clearingDao, $this->agentLicenseEventProcessor, $clearingEventProcessor, $this->dbManager);

    $this->runnerMock = new SchedulerTestRunnerMock($this->dbManager, $agentDao, $this->clearingDao, $this->uploadDao, $this->highlightDao, $this->clearingDecisionProcessor, $this->agentLicenseEventProcessor);
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
    $this->clearingDao = null;
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
    $this->testDb->createPlainTables(array('upload','upload_reuse','uploadtree',
      'uploadtree_a','license_ref','license_ref_bulk','clearing_decision',
      'clearing_decision_event','clearing_event','license_file','highlight',
      'highlight_bulk','agent','pfile','ars_master','users','group_user_member',
      'license_map','report_info'),false);
    $this->testDb->createSequences(array('agent_agent_pk_seq','pfile_pfile_pk_seq','upload_upload_pk_seq','nomos_ars_ars_pk_seq','license_file_fl_pk_seq','license_ref_rf_pk_seq','license_ref_bulk_lrb_pk_seq','clearing_decision_clearing_decision_pk_seq','clearing_event_clearing_event_pk_seq','FileLicense_pkey'),false);
    $this->testDb->createViews(array('license_file_ref'),false);
    $this->testDb->createConstraints(array('agent_pkey','pfile_pkey','upload_pkey_idx','clearing_event_pkey'),false);
    $this->testDb->alterTables(array('agent','pfile','upload','ars_master','license_ref_bulk','license_ref','clearing_event','clearing_decision','license_file','highlight'),false);
    $this->testDb->createInheritedTables();
    $this->testDb->createInheritedArsTables(array('nomos','monk'));

    $this->testDb->insertData(array('pfile','upload','uploadtree_a','users','group_user_member','agent','license_file','nomos_ars','monk_ars'), false);
    $this->testDb->insertData_license_ref();

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


  /**
   * @group Functional
   * @test
   * -# Insert few clearing events
   * -# Run DeciderJobAgent Mock
   * -# Check for decisions (should exist)
   * -# Check if events still exists
   */
  public function testDeciderMockedScanWithTwoEventAndNoAgentShouldMakeADecision()
  {
    $this->runnerDeciderScanWithTwoEventAndNoAgentShouldMakeADecision($this->runnerMock);
  }

  /**
   * @group Functional
   * @test
   * -# Insert few clearing events
   * -# Run DeciderJobAgent Cli
   * -# Check for decisions (should exist)
   * -# Check if events still exists
   */
  public function testDeciderRealScanWithTwoEventAndNoAgentShouldMakeADecision()
  {
    $this->runnerDeciderScanWithTwoEventAndNoAgentShouldMakeADecision($this->runnerCli);
  }

  /**
   * @brief run decider with two events
   */
  private function runnerDeciderScanWithTwoEventAndNoAgentShouldMakeADecision($runner)
  {
    $this->setUpTables();
    $this->setUpRepo();

    $jobId = 42;

    $licenseRef1 = $this->licenseDao->getLicenseByShortName("SPL-1.0")->getRef();
    $licenseRef2 = $this->licenseDao->getLicenseByShortName("Glide")->getRef();

    $addedLicenses = array($licenseRef1, $licenseRef2);

    assertThat($addedLicenses, not(arrayContaining(null)));

    $eventId1 = $this->clearingDao->insertClearingEvent($originallyClearedItemId=23, $userId=2, $groupId=3, $licenseRef1->getId(), false);
    $eventId2 = $this->clearingDao->insertClearingEvent($originallyClearedItemId, 5, $groupId, $licenseRef2->getId(), true);

    $this->dbManager->queryOnce("UPDATE clearing_event SET job_fk=$jobId");

    $addedEventIds = array($eventId1, $eventId2);

    list($success,$output,$retCode) = $runner->run($uploadId=2, $userId, $groupId, $jobId, $args="");

    $this->assertTrue($success, 'cannot run runner');
    $this->assertEquals($retCode, 0, 'decider failed (did you make test?): '.$output);

    assertThat($this->getHeartCount($output), equalTo(1));

    $uploadBounds = $this->uploadDao->getParentItemBounds($uploadId);
    $decisions = $this->clearingDao->getFileClearingsFolder($uploadBounds, $groupId);
    assertThat($decisions, is(arrayWithSize(1)));

    /** @var ClearingDecision $deciderMadeDecision*/
    $deciderMadeDecision = $decisions[0];

    foreach ($deciderMadeDecision->getClearingEvents() as $event) {
      assertThat($event->getEventId(), is(anyOf($addedEventIds)));
    }

    $this->rmRepo();
  }

  /**
   * @group Functional
   * @test
   * -# Create findings with nomos
   * -# Run DeciderJobAgent Mock
   * -# Check for decisions (should not be empty)
   */
  public function testDeciderMockScanWithNoEventsAndOnlyNomosShouldNotMakeADecision()
  {
    $this->runnerDeciderScanWithNoEventsAndOnlyNomosShouldNotMakeADecision($this->runnerMock);
  }

  /**
   * @group Functional
   * @test
   * -# Create findings with nomos
   * -# Run DeciderJobAgent Cli
   * -# Check for decisions (should not be empty)
   */
  public function testDeciderRealScanWithNoEventsAndOnlyNomosShouldNotMakeADecision()
  {
    $this->runnerDeciderScanWithNoEventsAndOnlyNomosShouldNotMakeADecision($this->runnerCli);
  }

  /**
   * @brief run decider with no events
   */
  private function runnerDeciderScanWithNoEventsAndOnlyNomosShouldNotMakeADecision($runner)
  {
    $this->setUpTables();
    $this->setUpRepo();

    $licenseRef1 = $this->licenseDao->getLicenseByShortName("SPL-1.0")->getRef();

    $licId1 = $licenseRef1->getId();

    $agentNomosId = 6;
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

  /**
   * @test
   * -# Insert two clearing events
   * -# Run DeciderJobAgent
   */
  public function testDeciderScanWithTwoEventAndNoAgentShouldMakeADecision()
  {
    $this->setUpTables();
    $this->setUpRepo();

    $dbManager = M::mock(DbManager::class);
    $agentDao = M::mock(AgentDao::class);
    $clearingDao = M::mock(ClearingDao::class);
    $uploadDao = M::mock(UploadDao::class);
    $highlightDao = M::mock(HighlightDao::class);
    $decisionProcessor = M::mock(ClearingDecisionProcessor::class);
    $agentLicenseEventProcessor = M::mock(AgentLicenseEventProcessor::class);

    $uploadId = 13243;

    /*mock for Agent class **/
    $agentDao->shouldReceive('arsTableExists')->andReturn(true);
    $agentDao->shouldReceive('getCurrentAgentId')->andReturn($agentId=24);
    $agentDao->shouldReceive('writeArsRecord')->with(anything(), $agentId, $uploadId)->andReturn($arsId=2);
    $agentDao->shouldReceive('writeArsRecord')->with(anything(), $agentId, $uploadId, $arsId, anything())->andReturn(0);

    $jobId = 42;
    $groupId = 6;
    $userId = 2;

    $itemIds = array(4343, 43);

    $bounds0 = M::mock(ItemTreeBounds::class);
    $bounds0->shouldReceive('getItemId')->andReturn($itemIds[0]);
    $bounds0->shouldReceive('containsFiles')->andReturn(false);
    $bounds1 = M::mock(ItemTreeBounds::class);
    $bounds1->shouldReceive('getItemId')->andReturn($itemIds[1]);
    $bounds1->shouldReceive('containsFiles')->andReturn(false);
    $bounds = array($bounds0, $bounds1);

    $uploadDao->shouldReceive('getItemTreeBounds')->with($itemIds[0])->andReturn($bounds[0]);
    $uploadDao->shouldReceive('getItemTreeBounds')->with($itemIds[1])->andReturn($bounds[1]);

    $clearingDao->shouldReceive('getEventIdsOfJob')->with($jobId)
            ->andReturn(array($itemIds[0] => array(), $itemIds[1] => array()));

    $dbManager->shouldReceive('begin')->times(count($itemIds));
    $dbManager->shouldReceive('commit')->times(count($itemIds));

    /* dummy expectations needed for unmockable LicenseMap constructor */
    $dbManager->shouldReceive('prepare');
    $res = M::Mock(DbManager::class);
    $dbManager->shouldReceive('execute')->andReturn($res);
    $row1 = array('rf_fk' => 2334, 'parent_fk' => 1);
    $row2 = array('rf_fk' => 2333, 'parent_fk' => 1);
    $dbManager->shouldReceive('fetchArray')->with($res)->andReturn($row1, $row2, false);
    $dbManager->shouldReceive('freeResult')->with($res);
    /* /expectations for LicenseMap */

    $decisionProcessor->shouldReceive('hasUnhandledScannerDetectedLicenses')
            ->with($bounds0, $groupId, array(), anything())->andReturn(true);
    $clearingDao->shouldReceive('markDecisionAsWip')
            ->with($itemIds[0], $userId, $groupId);

    $decisionProcessor->shouldReceive('hasUnhandledScannerDetectedLicenses')
            ->with($bounds1, $groupId, array(), anything())->andReturn(false);
    $decisionProcessor->shouldReceive('makeDecisionFromLastEvents')
            ->with($bounds1, $userId, $groupId, DecisionTypes::IDENTIFIED, false, array());

    $runner = new SchedulerTestRunnerMock($dbManager, $agentDao, $clearingDao, $uploadDao, $highlightDao, $decisionProcessor, $agentLicenseEventProcessor);

    list($success,$output,$retCode) = $runner->run($uploadId, $userId, $groupId, $jobId, $args="");

    $this->assertTrue($success, 'cannot run decider');
    $this->assertEquals($retCode, 0, 'decider failed: '.$output);
    assertThat($this->getHeartCount($output), equalTo(count($itemIds)));

    $this->rmRepo();
  }

  /**
   * @group Functional
   * @test
   * -# Insert two clearing events
   * -# Run DeciderJobAgent with force rule Mock
   * -# Check for decisions (should exist)
   * -# Check if events still exists
   * -# Check if new event is created
   */
  public function testDeciderMockedScanWithForceDecision()
  {
    $this->runnerDeciderScanWithForceDecision($this->runnerMock);
  }

  /**
   * @group Functional
   * @test
   * -# Insert two clearing events
   * -# Run DeciderJobAgent with force rule Cli
   * -# Check for decisions (should exist)
   * -# Check if events still exists
   * -# Check if new event is created
   */
  public function testDeciderRealScanWithForceDecision()
  {
    $this->runnerDeciderScanWithForceDecision($this->runnerCli);
  }

  /**
   * @brief run decider with force decision
   */
  private function runnerDeciderScanWithForceDecision($runner)
  {
    $this->setUpTables();
    $this->setUpRepo();

    $jobId = 42;

    $licenseRef1 = $this->licenseDao->getLicenseByShortName("SPL-1.0")->getRef();
    $licenseRef2 = $this->licenseDao->getLicenseByShortName("Glide")->getRef();

    $agentLicId = $this->licenseDao->getLicenseByShortName("Adaptec")->getRef()->getId();

    $addedLicenses = array($licenseRef1, $licenseRef2);

    assertThat($addedLicenses, not(arrayContaining(null)));

    $agentId = 5;
    $pfile = 4;

    $this->dbManager->queryOnce("INSERT INTO license_file (fl_pk,rf_fk,pfile_fk,agent_fk) VALUES(12222,$agentLicId,$pfile,$agentId)");

    $itemTreeBounds = $this->uploadDao->getItemTreeBounds($itemId=23);
    assertThat($this->agentLicenseEventProcessor->getScannerEvents($itemTreeBounds), is(not(emptyArray())));

    $eventId1 = $this->clearingDao->insertClearingEvent($itemId, $userId=2, $groupId=3, $licenseRef1->getId(), false);
    $eventId2 = $this->clearingDao->insertClearingEvent($itemId, 5, $groupId, $licenseRef2->getId(), true);

    $this->dbManager->queryOnce("UPDATE clearing_event SET job_fk=$jobId");

    $addedEventIds = array($eventId1, $eventId2);

    list($success,$output,$retCode) = $runner->run($uploadId=2, $userId, $groupId, $jobId, $args="-k1");

    $this->assertTrue($success, 'cannot run runner');
    $this->assertEquals($retCode, 0, 'decider failed: '.$output);
    assertThat($this->getHeartCount($output), equalTo(1));

    $uploadBounds = $this->uploadDao->getParentItemBounds($uploadId);
    $decisions = $this->clearingDao->getFileClearingsFolder($uploadBounds, $groupId);
    assertThat($decisions, is(arrayWithSize(1)));

    /** @var ClearingDecision $deciderMadeDecision */
    $deciderMadeDecision = $decisions[0];

    $eventIds = array();
    foreach ($deciderMadeDecision->getClearingEvents() as $event) {
      $eventIds[] = $event->getEventId();
    }

    assertThat($eventIds, arrayValue($addedEventIds[0]));
    assertThat($eventIds, arrayValue($addedEventIds[1]));
    assertThat($eventIds, arrayWithSize(1+count($addedEventIds)));

    $this->rmRepo();
  }
}
