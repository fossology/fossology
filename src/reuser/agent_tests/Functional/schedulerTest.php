<?php
/*
 SPDX-FileCopyrightText: © 2014-2015, 2019 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @dir
 * @brief Functional test cases for Reuser agent
 * @file
 * @brief Functional test cases for Reuser agent and scheduler interaction
 */
/**
 * @namespace Fossology::Reuser::Test
 * @brief Namespace to hold test cases for Reuser agent
 */
namespace Fossology\Reuser\Test;

use Fossology\Lib\BusinessRules\ClearingDecisionFilter;
use Fossology\Lib\Dao\AgentDao;
use Fossology\Lib\Dao\ClearingDao;
use Fossology\Lib\Dao\CopyrightDao;
use Fossology\Lib\Dao\HighlightDao;
use Fossology\Lib\Dao\LicenseDao;
use Fossology\Lib\Dao\TreeDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Dao\UploadPermissionDao;
use Fossology\Lib\Data\Clearing\ClearingEvent;
use Fossology\Lib\Data\Clearing\ClearingEventTypes;
use Fossology\Lib\Data\Clearing\ClearingLicense;
use Fossology\Lib\Data\ClearingDecision;
use Fossology\Lib\Data\DecisionScopes;
use Fossology\Lib\Data\DecisionTypes;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Test\TestInstaller;
use Fossology\Lib\Test\TestPgDb;
use Monolog\Logger;

include_once(__DIR__.'/../../../lib/php/Test/Agent/AgentTestMockHelper.php');
include_once(__DIR__.'/SchedulerTestRunnerCli.php');
include_once(__DIR__.'/SchedulerTestRunnerMock.php');

/**
 * @class SchedulerTest
 * @brief Tests for Reuser agent and scheduler interaction
 */
class SchedulerTest extends \PHPUnit\Framework\TestCase
{
  /** @var int $groupId
   * Group id to use
   */
  private $groupId = 3;
  /** @var int $userId
   * User id to use
   */
  private $userId = 2;
  /** @var TestPgDb $testDb
   * Test db
   */
  private $testDb;
  /** @var DbManager $dbManager
   * DBManager to use
   */
  private $dbManager;
  /** @var TestInstaller $testInstaller
   * TestInstaller object
   */
  private $testInstaller;
  /** @var LicenseDao $licenseDao
   * LicenseDao object
   */
  private $licenseDao;
  /** @var ClearingDao $clearingDao
   * ClearingDao object
   */
  private $clearingDao;
  /** @var CopyrightDao $copyrightDao
   * CopyrightDao object
   */
  private $copyrightDao;
  /** @var ClearingDecisionFilter $clearingDecisionFilter
   * ClearingDecisionFilter object
   */
  private $clearingDecisionFilter;
  /** @var UploadDao $uploadDao
   * Upload Dao
   */
  private $uploadDao;
  /** @var UploadPermissionDao $uploadPermDao
   * Upload permission
   */
  private $uploadPermDao;
  /** @var HighlightDao $highlightDao
   * Highlight Dao
   */
  private $highlightDao;
  /** @var Mock|TreeDao $treeDao
   * Tree dao
   */
  private $treeDao;

  /** @var SchedulerTestRunnerCli $runnerCli
   * Scheduler interface
   */
  private $runnerCli;

  /** @var SchedulerTestRunnerMock $runnerMock
   * Test runner
   */
  private $runnerMock;

  /**
   * @brief Setup test env
   */
  protected function setUp() : void
  {
    $this->testDb = new TestPgDb("reuserSched");
    $this->dbManager = $this->testDb->getDbManager();

    $this->licenseDao = new LicenseDao($this->dbManager);
    $logger = new Logger("ReuserSchedulerTest");
    $this->uploadPermDao = \Mockery::mock(UploadPermissionDao::class);
    $this->uploadDao = new UploadDao($this->dbManager, $logger, $this->uploadPermDao);
    $this->highlightDao = new HighlightDao($this->dbManager);
    $this->clearingDecisionFilter = new ClearingDecisionFilter();
    $this->clearingDao = new ClearingDao($this->dbManager, $this->uploadDao);
    $this->copyrightDao = new CopyrightDao($this->dbManager, $this->uploadDao);
    $this->treeDao = \Mockery::mock(TreeDao::class);

    $agentDao = new AgentDao($this->dbManager, $logger);

    $this->runnerMock = new SchedulerTestRunnerMock($this->dbManager, $agentDao,
                        $this->clearingDao, $this->uploadDao, $this->clearingDecisionFilter,
                        $this->treeDao, $this->copyrightDao);
    $this->runnerCli = new SchedulerTestRunnerCli($this->testDb);
  }

  /**
   * @brief Tear down test env
   */
  protected function tearDown() : void
  {
    $this->testDb->fullDestruct();
    $this->testDb = null;
    $this->dbManager = null;
    $this->licenseDao = null;
    $this->highlightDao = null;
    $this->clearingDao = null;
    $this->copyrightDao = null;
  }

  /**
   * @brief Setup test repo
   */
  private function setUpRepo()
  {
    $sysConf = $this->testDb->getFossSysConf();
    $this->testInstaller = new TestInstaller($sysConf);
    $this->testInstaller->init();
    $this->testInstaller->cpRepo();
  }

  /**
   * @brief Tear down test repo
   */
  private function rmRepo()
  {
    $this->testInstaller->rmRepo();
    $this->testInstaller->clear();
  }

  /**
   * @brief Setup tables required by the agent
   */
  private function setUpTables()
  {
    $this->testDb->createPlainTables(array('upload','upload_reuse','uploadtree',
      'uploadtree_a','license_ref','license_ref_bulk','clearing_decision',
      'clearing_decision_event','clearing_event','license_file','highlight',
      'highlight_bulk','agent','pfile','ars_master','users','group_user_member',
      'upload_clearing_license','report_info', 'license_expression'),false);
    $this->testDb->createSequences(array('agent_agent_pk_seq','pfile_pfile_pk_seq',
      'upload_upload_pk_seq','nomos_ars_ars_pk_seq','license_file_fl_pk_seq',
      'license_ref_rf_pk_seq','license_ref_bulk_lrb_pk_seq',
      'clearing_decision_clearing_decision_pk_seq',
      'clearing_event_clearing_event_pk_seq','report_info_pk_seq'),false);
    $this->testDb->createViews(array('license_file_ref'),false);
    $this->testDb->createConstraints(array('agent_pkey','pfile_pkey',
      'upload_pkey_idx','FileLicense_pkey','clearing_event_pkey'),false);
    $this->testDb->alterTables(array('agent','pfile','upload','ars_master',
      'license_ref_bulk','license_ref','clearing_event','clearing_decision','license_file','highlight', 'license_expression'),false);
    $this->testDb->createInheritedTables();
    $this->testDb->createInheritedArsTables(array('monk'));

    $this->testDb->insertData(array('pfile','upload','uploadtree_a','users',
      'group_user_member','agent','license_file','monk_ars','report_info'),
      false);
    $this->testDb->insertData_license_ref(80);

    $this->testDb->resetSequenceAsMaxOf('agent_agent_pk_seq', 'agent', 'agent_pk');

    $this->testDb->setupSysconfig();
  }

  /**
   * @brief Get the heart count from agent
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
   * @brief Get clearings for a given upload id
   * @param int $uploadId
   * @param int $groupId
   * @return ClearingDecision[]
   */
  private function getFilteredClearings($uploadId, $groupId)
  {
    $bounds = $this->uploadDao->getParentItemBounds($uploadId);
    return $this->clearingDao->getFileClearingsFolder($bounds, $groupId);
  }

  /**
   * @brief Call runnerReuserScanWithoutAnyUploadToCopyAndNoClearing()
   * @test
   * -# Setup an upload with no clearing decisions
   * -# Run reuser on the empty upload with mock agent
   * -# Check that no clearing decisions added by reuser
   */
  public function testReuserMockedScanWithoutAnyUploadToCopyAndNoClearing()
  {
    $this->runnerReuserScanWithoutAnyUploadToCopyAndNoClearing($this->runnerMock);
  }

  /**
   * @brief Call runnerReuserScanWithoutAnyUploadToCopyAndNoClearing()
   * @test
   * -# Setup an upload with no clearing decisions
   * -# Run reuser on the empty upload with scheduler cli
   * -# Check that no clearing decisions added by reuser
   */
  public function testReuserRealScanWithoutAnyUploadToCopyAndNoClearing()
  {
    $this->runnerReuserScanWithoutAnyUploadToCopyAndNoClearing($this->runnerCli);
  }

  /**
   * @brief Test on an upload with no clearing decisions
   * @param SchedulerTestRunner $runner
   */
  private function runnerReuserScanWithoutAnyUploadToCopyAndNoClearing(SchedulerTestRunner $runner)
  {
    $this->setUpTables();
    $this->setUpRepo();

    list($success, $output,$retCode) = $runner->run($uploadId=1, $this->userId);

    $this->assertTrue($success, 'cannot run runner');
    $this->assertEquals($retCode, 0, 'reuser failed: '.$output);

    assertThat($this->getHeartCount($output), equalTo(0));

    $bounds = $this->uploadDao->getParentItemBounds($uploadId);
    assertThat($this->clearingDao->getFileClearingsFolder($bounds, $groupId=5), is(emptyArray()));

    $this->rmRepo();
  }

  /**
   * @brief Creates two clearing decisions
   * @param int $scope
   * @param int $originallyClearedItemId
   * @return ClearingLicense[]
   */
  protected function insertDecisionFromTwoEvents($scope=DecisionScopes::ITEM,$originallyClearedItemId=23)
  {
    $licenseRef1 = $this->licenseDao->getLicenseByShortName("SPL-1.0")->getRef();
    $licenseRef2 = $this->licenseDao->getLicenseByShortName("Glide")->getRef();

    $addedLicenses = array($licenseRef1, $licenseRef2);
    assertThat($addedLicenses, not(arrayContaining(null)));

    $clearingLicense1 = new ClearingLicense($licenseRef1, false, ClearingEventTypes::USER, "42", "44");
    $clearingLicense2 = new ClearingLicense($licenseRef2, true, ClearingEventTypes::USER, "-42", "-44");

    $eventId1 = $this->clearingDao->insertClearingEvent($originallyClearedItemId, $this->userId, $this->groupId,
            $licenseRef1->getId(), $clearingLicense1->isRemoved(),
            $clearingLicense1->getType(), $clearingLicense1->getReportinfo(), $clearingLicense1->getComment());
    $eventId2 = $this->clearingDao->insertClearingEvent($originallyClearedItemId, 5, $this->groupId,
            $licenseRef2->getId(), $clearingLicense2->isRemoved(),
            $clearingLicense2->getType(), $clearingLicense2->getReportinfo(), $clearingLicense2->getComment());

    $addedEventIds = array($eventId1, $eventId2);

    $this->clearingDao->createDecisionFromEvents($originallyClearedItemId, $this->userId,
      $this->groupId, DecisionTypes::IDENTIFIED, $scope, $addedEventIds);

    return array($clearingLicense1, $clearingLicense2, $addedEventIds);
  }

  /**
   * @brief Call runnerReuserScanWithoutAnyUploadToCopyAndAClearing()
   * @test
   * -# Run reuser on the empty upload with agent mock
   * -# Check that no clearing decisions added by reuser
   */
  public function testReuserMockedScanWithoutAnyUploadToCopyAndAClearing()
  {
    $this->runnerReuserScanWithoutAnyUploadToCopyAndAClearing($this->runnerMock);
  }

  /**
   * @brief Call runnerReuserScanWithoutAnyUploadToCopyAndAClearing()
   * @test
   * -# Run reuser on the empty upload with scheduler cli
   * -# Check that no clearing decisions added by reuser
   */
  public function testReuserRealScanWithoutAnyUploadToCopyAndAClearing()
  {
    $this->runnerReuserScanWithoutAnyUploadToCopyAndAClearing($this->runnerCli);
  }

  /**
   * @brief Run reuser agent with no upload to copy decisions from
   * @param SchedulerTestRunner $runner
   */
  private function runnerReuserScanWithoutAnyUploadToCopyAndAClearing($runner)
  {
    $this->setUpTables();
    $this->setUpRepo();

    $this->insertDecisionFromTwoEvents();

    list($success,$output,$retCode) = $runner->run($uploadId=3);

    $this->assertTrue($success, 'cannot run runner');
    $this->assertEquals($retCode, 0, 'reuser failed: '.$output);

    assertThat($this->getHeartCount($output), equalTo(0));

    $decisions = $this->getFilteredClearings($uploadId, $this->groupId);
    assertThat($decisions, is(emptyArray()));

    $this->rmRepo();
  }

  /**
   * @brief Call runnerReuserScanWithALocalClearing()
   * @test
   * -# Create an upload with clearing decisions on files
   * -# Run reuser on the upload new upload with mock agent
   * -# Check if clearing decisions are added
   * -# Check if the clearing decisions have new ids
   * -# Check the clearing type and scope are retained
   * -# Check the upload tree id of the clearing decision
   */
  public function testReuserMockedScanWithALocalClearing()
  {
    $this->runnerReuserScanWithALocalClearing($this->runnerMock,1);
  }

  /**
   * @brief Call runnerReuserScanWithALocalClearing()
   * @test
   * -# Create an upload with clearing decisions on files
   * -# Run reuser on the upload new upload with scheduler cli
   * -# Check if clearing decisions are added
   * -# Check if the clearing decisions have new ids
   * -# Check the clearing type and scope are retained
   * -# Check the upload tree id of the clearing decision
   */
  public function testReuserRealScanWithALocalClearing()
  {
    $this->runnerReuserScanWithALocalClearing($this->runnerCli,1);
  }

  /**
   * @brief Check reuser with local clearing decisions (file level)
   * @param SchedulerTestRunner $runner
   * @param int $heartBeat
   */
  private function runnerReuserScanWithALocalClearing($runner, $heartBeat=0)
  {
    $this->setUpTables();
    $this->setUpRepo();

    $this->uploadDao->addReusedUpload($uploadId=3,$reusedUpload=2,$this->groupId,$this->groupId);

    list($clearingLicense1, $clearingLicense2, $addedEventIds) = $this->insertDecisionFromTwoEvents();

    /* upload 3 in the test db is the same as upload 2
     * items 13-24 in upload 2 correspond to 33-44 */
    $reusingUploadItemShift = 20;

    list($success,$output,$retCode) = $runner->run($uploadId, $this->userId, $this->groupId);

    $this->assertTrue($success, 'cannot run runner');
    $this->assertEquals($retCode, 0, 'reuser failed: '.$output);
    assertThat($this->getHeartCount($output), equalTo($heartBeat));

    $newUploadClearings = $this->getFilteredClearings($uploadId, $this->groupId);
    $potentiallyReusableClearings = $this->getFilteredClearings($reusedUpload, $this->groupId);

    assertThat($newUploadClearings, is(arrayWithSize(1)));

    assertThat($potentiallyReusableClearings, is(arrayWithSize(1)));
    /** @var ClearingDecision */
    $potentiallyReusableClearing = $potentiallyReusableClearings[0];
    /** @var ClearingDecision */
    $newClearing = $newUploadClearings[0];

    assertThat($newClearing, not(equalTo($potentiallyReusableClearing)));
    assertThat($newClearing->getClearingId(), not(equalTo($potentiallyReusableClearing->getClearingId())));

    assertThat($newClearing->getClearingLicenses(), arrayContainingInAnyOrder($clearingLicense1, $clearingLicense2));

    assertThat($newClearing->getType(), equalTo($potentiallyReusableClearing->getType()));
    assertThat($newClearing->getScope(), equalTo($potentiallyReusableClearing->getScope()));

    assertThat($newClearing->getUploadTreeId(),
      equalTo($potentiallyReusableClearing->getUploadTreeId() + $reusingUploadItemShift));

    $this->rmRepo();
  }

  /**
   * @brief Call runnerReuserScanWithARepoClearing()
   * @test
   * -# Create an upload with license clearing done
   * -# Run reuser with mock agent
   * -# Check if new upload has clearings
   * -# Reuser should have not created a new clearing decision and reuse them
   * -# Decision types and scopes are same
   * -# Reuser should have not created a correct local event history
   */
  public function testReuserMockedScanWithARepoClearing()
  {
    $this->runnerReuserScanWithARepoClearing($this->runnerMock);
  }

  /**
   * @brief Call runnerReuserScanWithARepoClearing()
   * @test
   * -# Create an upload with license clearing done
   * -# Run reuser with scheduler cli
   * -# Check if new upload has clearings
   * -# Reuser should have not created a new clearing decision and reuse them
   * -# Decision types and scopes are same
   * -# Reuser should have not created a correct local event history
   */
  public function testReuserRealScanWithARepoClearing()
  {
    $this->runnerReuserScanWithARepoClearing($this->runnerCli);
  }

  /**
   * @brief Run reuser on upload with clearing
   * @param SchedulerTestRunner $runner
   */
  private function runnerReuserScanWithARepoClearing($runner)
  {
    $this->setUpTables();
    $this->setUpRepo();

    $this->uploadDao->addReusedUpload($uploadId=3,$reusedUpload=2,$this->groupId,$this->groupId);

    list($clearingLicense1, $clearingLicense2, $addedEventIds) = $this->insertDecisionFromTwoEvents(
      DecisionScopes::REPO,$originallyClearedItemId=23);
    $clearingLicenses = array($clearingLicense1, $clearingLicense2);

    /* upload 3 in the test db is the same as upload 2
     * items 13-24 in upload 2 correspond to 33-44 */
    $reusingUploadItemShift = 20;

    list($success,$output,$retCode) = $runner->run($uploadId, $this->userId, $this->groupId);

    $this->assertTrue($success, 'cannot run runner');
    $this->assertEquals($retCode, 0, 'reuser failed: '.$output);

    assertThat($this->getHeartCount($output), equalTo(0));

    $newUploadClearings = $this->getFilteredClearings($uploadId, $this->groupId);
    $potentiallyReusableClearings = $this->getFilteredClearings($reusedUpload, $this->groupId);

    assertThat($newUploadClearings, is(arrayWithSize(1)));

    assertThat($potentiallyReusableClearings, is(arrayWithSize(1)));
    /** @var ClearingDecision */
    $potentiallyReusableClearing = $potentiallyReusableClearings[0];
    /** @var ClearingDecision */
    $newClearing = $newUploadClearings[0];

    /* they are actually the same ClearingDecision
     * only sameFolder and sameUpload are different */
    assertThat($newClearing, not(equalTo($potentiallyReusableClearing)));

    /* reuser should have not created a new clearing decision */
    assertThat($newClearing->getClearingId(), equalTo($potentiallyReusableClearing->getClearingId()));

    assertThat($newClearing->getClearingLicenses(), arrayContainingInAnyOrder($clearingLicenses));

    assertThat($newClearing->getType(), equalTo($potentiallyReusableClearing->getType()));
    assertThat($newClearing->getScope(), equalTo($potentiallyReusableClearing->getScope()));

    assertThat($newClearing->getUploadTreeId(),
            equalTo($potentiallyReusableClearing->getUploadTreeId() + $reusingUploadItemShift));

    /* reuser should have not created a correct local event history */
    $bounds = $this->uploadDao->getItemTreeBounds($originallyClearedItemId + $reusingUploadItemShift);
    $newEvents = $this->clearingDao->getRelevantClearingEvents($bounds, $this->groupId);

    assertThat($newEvents, is(arrayWithSize(count($clearingLicenses))));

    /** @var ClearingEvent $newEvent */
    foreach ($newEvents as $newEvent) {
      assertThat($newEvent->getEventId(), anyOf($addedEventIds));
      assertThat($newEvent->getClearingLicense(), anyOf($clearingLicenses));
    }

    $this->rmRepo();
  }

  /**
   * @brief Call runnerReuserScanWithARepoClearingEnhanced()
   * @test
   * -# Create an upload with license clearing done
   * -# Create an upload with files with small difference
   * -# Run reuser with mock agent
   * -# Check if new upload has clearings
   * -# Reuser should have not created a new clearing decision and reuse them
   * -# Decision types and scopes are same
   * -# Reuser should have not created a correct local event history
   */
  public function testReuserRealScanWithARepoClearingEnhanced()
  {
    $this->runnerReuserScanWithARepoClearingEnhanced($this->runnerMock);
  }

  /**
   * @brief Run reuser with enhanced flag on upload with clearing
   * @param SchedulerTestRunner $runner
   */
  private function runnerReuserScanWithARepoClearingEnhanced($runner)
  {
    $this->setUpTables();
    $this->setUpRepo();

    $originallyClearedItemId = 23;
    /* upload 3 in the test db is the same as upload 2 -> items 13-24 in upload 2 correspond to 33-44 */
    $reusingUploadItemShift = 20;

    $this->uploadDao->addReusedUpload($uploadId=3,$reusedUpload=2,$this->groupId,$this->groupId,$reuseMode=2);

    $repoPath = $this->testDb->getFossSysConf().'/repo/files/';
    $this->treeDao->shouldReceive('getRepoPathOfPfile')->with(4)->andReturn($repoPath
      .'04621571bcbabce75c4dd1c6445b87dec0995734.59cacdfce5051cd8a1d8a1f2dcce40a5.12320');
    $this->treeDao->shouldReceive('getRepoPathOfPfile')->with(351)->andReturn($repoPath
      .'c518ce1658140b65fa0132ad1130cb91512416bf.8e913e594d24ff3aeabe350107d97815.35829');

    list($clearingLicense1, $clearingLicense2, $addedEventIds) = $this->insertDecisionFromTwoEvents(
      DecisionScopes::REPO,$originallyClearedItemId);
    $clearingLicenses = array($clearingLicense1, $clearingLicense2);

    list($success,$output,$retCode) = $runner->run($uploadId, $this->userId, $this->groupId);

    $this->assertTrue($success, 'cannot run runner');
    $this->assertEquals($retCode, 0, 'reuser failed: '.$output);

    $newUploadClearings = $this->getFilteredClearings($uploadId, $this->groupId);
    $potentiallyReusableClearings = $this->getFilteredClearings($reusedUpload, $this->groupId);

    assertThat($newUploadClearings, is(arrayWithSize(1)));

    assertThat($potentiallyReusableClearings, is(arrayWithSize(1)));
    /** @var ClearingDecision */
    $potentiallyReusableClearing = $potentiallyReusableClearings[0];
    /** @var ClearingDecision */
    $newClearing = $newUploadClearings[0];

    /* they are actually the same ClearingDecision
     * only sameFolder and sameUpload are different */
    assertThat($newClearing, not(equalTo($potentiallyReusableClearing)));

    assertThat($newClearing->getClearingLicenses(), arrayContainingInAnyOrder($clearingLicenses));

    assertThat($newClearing->getType(), equalTo($potentiallyReusableClearing->getType()));
    assertThat($newClearing->getScope(), equalTo($potentiallyReusableClearing->getScope()));

    assertThat($newClearing->getUploadTreeId(),
            equalTo($potentiallyReusableClearing->getUploadTreeId() + $reusingUploadItemShift));

    /* reuser should have not created a correct local event history */
    $bounds = $this->uploadDao->getItemTreeBounds($originallyClearedItemId + $reusingUploadItemShift);
    $newEvents = $this->clearingDao->getRelevantClearingEvents($bounds, $this->groupId);

    assertThat($newEvents, is(arrayWithSize(count($clearingLicenses))));

    /** @var ClearingEvent $newEvent */
    foreach ($newEvents as $newEvent) {
      assertThat($newEvent->getEventId(), anyOf($addedEventIds));
      assertThat($newEvent->getClearingLicense(), anyOf($clearingLicenses));
    }
    /*reuse main license*/
    $this->clearingDao->makeMainLicense($uploadId=2, $this->groupId, $mainLicenseId=402);
    $mainLicenseIdForReuse = $this->clearingDao->getMainLicenseIds($reusedUploadId=2, $this->groupId);
    $mainLicenseIdForReuseSingle = array_values($mainLicenseIdForReuse);
    $this->clearingDao->makeMainLicense($uploadId=3, $this->groupId, $mainLicenseIdForReuseSingle[0]);
    $mainLicense=$this->clearingDao->getMainLicenseIds($uploadId=3, $this->groupId);
    $mainLicenseSingle = array_values($mainLicense);
    $this->assertEquals($mainLicenseIdForReuseSingle, $mainLicenseSingle);
    $this->rmRepo();
  }
}
