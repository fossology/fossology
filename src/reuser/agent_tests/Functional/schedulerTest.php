<?php
/*
Copyright (C) 2014-2015, Siemens AG

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

namespace Fossology\Reuser\Test;

use Fossology\Lib\BusinessRules\ClearingDecisionFilter;
use Fossology\Lib\Dao\AgentDao;
use Fossology\Lib\Dao\ClearingDao;
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

class SchedulerTest extends \PHPUnit_Framework_TestCase
{
  private $groupId = 3;
  private $userId = 2;
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
  /** @var ClearingDecisionFilter */
  private $clearingDecisionFilter;
  /** @var UploadDao */
  private $uploadDao;
  /** @var UploadPermissionDao */
  private $uploadPermDao;
  /** @var HighlightDao */
  private $highlightDao;
  /** @var Mock|TreeDao */
  private $treeDao;
  
  /** @var SchedulerTestRunnerCli */
  private $runnerCli;

  /** @var SchedulerTestRunnerMock */
  private $runnerMock;

  protected function setUp()
  {
    $this->testDb = new TestPgDb("reuserSched");
    $this->dbManager = $this->testDb->getDbManager();

    $this->licenseDao = new LicenseDao($this->dbManager);
    $logger = new Logger("ReuserSchedulerTest");
    $this->uploadPermDao = \Mockery::mock(UploadPermissionDao::classname());
    $this->uploadDao = new UploadDao($this->dbManager, $logger, $this->uploadPermDao);
    $this->highlightDao = new HighlightDao($this->dbManager);
    $this->clearingDecisionFilter = new ClearingDecisionFilter();
    $this->clearingDao = new ClearingDao($this->dbManager, $this->uploadDao);
    $this->treeDao = \Mockery::mock(TreeDao::classname());

    $agentDao = new AgentDao($this->dbManager, $logger);

    $this->runnerMock = new SchedulerTestRunnerMock($this->dbManager, $agentDao, $this->clearingDao, $this->uploadDao, $this->clearingDecisionFilter, $this->treeDao);
    $this->runnerCli = new SchedulerTestRunnerCli($this->testDb);
  }

  protected function tearDown()
  {
    $this->testDb->fullDestruct();
    $this->testDb = null;
    $this->dbManager = null;
    $this->licenseDao = null;
    $this->highlightDao = null;
    $this->clearingDao = null;
  }

  private function setUpRepo()
  {
    $sysConf = $this->testDb->getFossSysConf();
    $this->testInstaller = new TestInstaller($sysConf);
    $this->testInstaller->init();
    $this->testInstaller->cpRepo();
  }

  private function rmRepo()
  {
    $this->testInstaller->rmRepo();
    $this->testInstaller->clear();
  }

  private function setUpTables()
  {
    $this->testDb->createPlainTables(array('upload','upload_reuse','uploadtree','uploadtree_a','license_ref','license_ref_bulk','clearing_decision','clearing_decision_event','clearing_event','license_file','highlight','highlight_bulk','agent','pfile','ars_master','users','group_user_member'),false);
    $this->testDb->createSequences(array('agent_agent_pk_seq','pfile_pfile_pk_seq','upload_upload_pk_seq','nomos_ars_ars_pk_seq','license_file_fl_pk_seq','license_ref_rf_pk_seq','license_ref_bulk_lrb_pk_seq','clearing_decision_clearing_decision_pk_seq','clearing_event_clearing_event_pk_seq'),false);
    $this->testDb->createViews(array('license_file_ref'),false);
    $this->testDb->createConstraints(array('agent_pkey','pfile_pkey','upload_pkey_idx','FileLicense_pkey','clearing_event_pkey'),false);
    $this->testDb->alterTables(array('agent','pfile','upload','ars_master','license_ref_bulk','clearing_event','clearing_decision','license_file','highlight'),false);
    $this->testDb->createInheritedTables();
    $this->testDb->createInheritedArsTables(array('monk'));

    $this->testDb->insertData(array('pfile','upload','uploadtree_a','users','group_user_member','agent','license_file','monk_ars'), false);
    $this->testDb->insertData_license_ref(80);

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

  private function getFilteredClearings($uploadId, $groupId)
  {
    $bounds = $this->uploadDao->getParentItemBounds($uploadId);
    return $this->clearingDao->getFileClearingsFolder($bounds, $groupId);
  }

  /** @group Functional */
  public function testReuserMockedScanWithoutAnyUploadToCopyAndNoClearing()
  {
    $this->runnerReuserScanWithoutAnyUploadToCopyAndNoClearing($this->runnerMock);
  }

  /** @group Functional */
  public function testReuserRealScanWithoutAnyUploadToCopyAndNoClearing()
  {
    $this->runnerReuserScanWithoutAnyUploadToCopyAndNoClearing($this->runnerCli);
  }

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

  
  protected function insertDecisionFromTwoEvents($scope=DecisionScopes::ITEM,$originallyClearedItemId=23)
  {
    $licenseRef1 = $this->licenseDao->getLicenseByShortName("GPL-3.0")->getRef();
    $licenseRef2 = $this->licenseDao->getLicenseByShortName("3DFX")->getRef();
    
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
    
    $this->clearingDao->createDecisionFromEvents($originallyClearedItemId, $this->userId, $this->groupId, DecisionTypes::IDENTIFIED, $scope, $addedEventIds);
    
    return array($clearingLicense1, $clearingLicense2, $addedEventIds);
  }
  
  
  
  /** @group Functional */
  public function testReuserMockedScanWithoutAnyUploadToCopyAndAClearing()
  {
    $this->runnerReuserScanWithoutAnyUploadToCopyAndAClearing($this->runnerMock);
  }

  /** @group Functional */
  public function testReuserRealScanWithoutAnyUploadToCopyAndAClearing()
  {
    $this->runnerReuserScanWithoutAnyUploadToCopyAndAClearing($this->runnerCli);
  }

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

  /** @group Functional */
  public function testReuserMockedScanWithALocalClearing()
  {
    $this->runnerReuserScanWithALocalClearing($this->runnerMock);
  }

  /** @group Functional */
  public function testReuserRealScanWithALocalClearing()
  {
    $this->runnerReuserScanWithALocalClearing($this->runnerCli);
  }

  private function runnerReuserScanWithALocalClearing($runner)
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

    assertThat($this->getHeartCount($output), equalTo(1));

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

    assertThat($newClearing->getUploadTreeId(), equalTo($potentiallyReusableClearing->getUploadTreeId() + $reusingUploadItemShift));

    $this->rmRepo();
  }

  /** @group Functional */
  public function testReuserMockedScanWithARepoClearing()
  {
    $this->runnerReuserScanWithARepoClearing($this->runnerMock);
  }

  /** @group Functional */
  public function testReuserRealScanWithARepoClearing()
  {
    $this->runnerReuserScanWithARepoClearing($this->runnerCli);
  }

  private function runnerReuserScanWithARepoClearing($runner)
  {
    $this->setUpTables();
    $this->setUpRepo();

    $this->uploadDao->addReusedUpload($uploadId=3,$reusedUpload=2,$this->groupId,$this->groupId);
    
    list($clearingLicense1, $clearingLicense2, $addedEventIds) = $this->insertDecisionFromTwoEvents(DecisionScopes::REPO,$originallyClearedItemId=23);
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
    foreach($newEvents as $newEvent)
    {
      assertThat($newEvent->getEventId(), anyOf($addedEventIds));
      assertThat($newEvent->getClearingLicense(), anyOf($clearingLicenses));
    }

    $this->rmRepo();
  }
  
  
  
  /** @group Functional */
  public function testReuserRealScanWithARepoClearingEnhanced()
  {
    $this->runnerReuserScanWithARepoClearingEnhanced($this->runnerMock);
  }
  
  private function runnerReuserScanWithARepoClearingEnhanced($runner)
  {
    $this->setUpTables();
    $this->setUpRepo();
    
    $originallyClearedItemId = 23;
    /* upload 3 in the test db is the same as upload 2 -> items 13-24 in upload 2 correspond to 33-44 */
    $reusingUploadItemShift = 20;
    
    $this->dbManager->queryOnce("UPDATE uploadtree_a SET pfile_fk=351 WHERE uploadtree_pk=$originallyClearedItemId+$reusingUploadItemShift",
            __METHOD__.'.minorChange');

    $this->uploadDao->addReusedUpload($uploadId=3,$reusedUpload=2,$this->groupId,$this->groupId,$reuseMode=1);

    $repoPath = $this->testDb->getFossSysConf().'/repo/files/';
    $this->treeDao->shouldReceive('getRepoPathOfPfile')->with(4)->andReturn($repoPath.'04621571bcbabce75c4dd1c6445b87dec0995734.59cacdfce5051cd8a1d8a1f2dcce40a5.12320');
    $this->treeDao->shouldReceive('getRepoPathOfPfile')->with(351)->andReturn($repoPath.'c518ce1658140b65fa0132ad1130cb91512416bf.8e913e594d24ff3aeabe350107d97815.35829');
    
    list($clearingLicense1, $clearingLicense2, $addedEventIds) = $this->insertDecisionFromTwoEvents(DecisionScopes::REPO,$originallyClearedItemId);
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
    foreach($newEvents as $newEvent)
    {
      assertThat($newEvent->getEventId(), anyOf($addedEventIds));
      assertThat($newEvent->getClearingLicense(), anyOf($clearingLicenses));
    }

    $this->rmRepo();
  }
  
}
