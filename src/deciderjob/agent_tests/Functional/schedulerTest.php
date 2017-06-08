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
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Test\TestInstaller;
use Fossology\Lib\Test\TestPgDb;
use Mockery as M;

include_once(__DIR__.'/../../../lib/php/Test/Agent/AgentTestMockHelper.php');
include_once(__DIR__.'/../../../lib/php/Plugin/FO_Plugin.php');
include_once(__DIR__.'/SchedulerTestRunnerCli.php');
include_once(__DIR__.'/SchedulerTestRunnerMock.php');

class SchedulerTest extends \PHPUnit_Framework_TestCase
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

  /** @var SchedulerTestRunnerCli */
  private $runnerCli;
  /** @var SchedulerTestRunnerMock */
  private $runnerMock;

  protected function setUp()
  {
    $this->testDb = new TestPgDb("deciderJobSched".time());
    $this->dbManager = $this->testDb->getDbManager();
    $logger = M::mock('Monolog\Logger');

    $this->licenseDao = new LicenseDao($this->dbManager);
    $this->uploadPermDao = \Mockery::mock(UploadPermissionDao::classname());
    $this->uploadDao = new UploadDao($this->dbManager, $logger, $this->uploadPermDao);
    $this->highlightDao = new HighlightDao($this->dbManager);
    $agentDao = new AgentDao($this->dbManager, $logger);
    $this->agentLicenseEventProcessor = new AgentLicenseEventProcessor($this->licenseDao, $agentDao);
    $clearingEventProcessor = new ClearingEventProcessor();
    $this->clearingDao = new ClearingDao($this->dbManager, $this->uploadDao);
    $this->clearingDecisionProcessor = new ClearingDecisionProcessor($this->clearingDao, $this->agentLicenseEventProcessor, $clearingEventProcessor, $this->dbManager);

    $this->runnerMock = new SchedulerTestRunnerMock($this->dbManager, $agentDao, $this->clearingDao, $this->uploadDao, $this->highlightDao, $this->licenseDao, $this->clearingDecisionProcessor, $this->agentLicenseEventProcessor);
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
    $this->testDb->createPlainTables(array('upload','upload_reuse','uploadtree','uploadtree_a','license_ref','license_ref_bulk','clearing_decision','clearing_decision_event','clearing_event','license_file','highlight','highlight_bulk','agent','pfile','ars_master','users','group_user_member','license_map'),false);
    $this->testDb->createSequences(array('agent_agent_pk_seq','pfile_pfile_pk_seq','upload_upload_pk_seq','nomos_ars_ars_pk_seq','license_file_fl_pk_seq','license_ref_rf_pk_seq','license_ref_bulk_lrb_pk_seq','clearing_decision_clearing_decision_pk_seq','clearing_event_clearing_event_pk_seq','FileLicense_pkey'),false);
    $this->testDb->createViews(array('license_file_ref'),false);
    $this->testDb->createConstraints(array('agent_pkey','pfile_pkey','upload_pkey_idx','clearing_event_pkey'),false);
    $this->testDb->alterTables(array('agent','pfile','upload','ars_master','license_ref_bulk','clearing_event','clearing_decision','license_file','highlight'),false);
    $this->testDb->createInheritedTables();
    $this->testDb->createInheritedArsTables(array('nomos','monk'));

    $this->testDb->insertData(array('pfile','upload','uploadtree_a','users','group_user_member','agent','license_file','nomos_ars','monk_ars'), false);
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
  public function testDeciderMockedScanWithTwoEventAndNoAgentShouldMakeADecision()
  {
    $this->runnerDeciderScanWithTwoEventAndNoAgentShouldMakeADecision($this->runnerMock);
  }

  /** @group Functional */
  public function testDeciderRealScanWithTwoEventAndNoAgentShouldMakeADecision()
  {
    $this->runnerDeciderScanWithTwoEventAndNoAgentShouldMakeADecision($this->runnerCli);
  }

  private function runnerDeciderScanWithTwoEventAndNoAgentShouldMakeADecision($runner)
  {
    $this->setUpTables();
    $this->setUpRepo();

    $jobId = 42;

    $licenseRef1 = $this->licenseDao->getLicenseByShortName("GPL")->getRef();
    $licenseRef2 = $this->licenseDao->getLicenseByShortName("3DFX")->getRef();

    $addedLicenses = array($licenseRef1, $licenseRef2);

    assertThat($addedLicenses, not(arrayContaining(null)));

    /* uploadtree_pk 23 refers to 3DFX_B file in upload 2 (B.zip) in B/1b folder*/
    $eventId1 = $this->clearingDao->insertClearingEvent($originallyClearedItemId=23, $userId=2, $groupId=2, $licenseRef1->getId(), false);
    $eventId2 = $this->clearingDao->insertClearingEvent($originallyClearedItemId, $userId, $groupId, $licenseRef2->getId(), true);

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

    /* Ensure that 3DFX license is removed and GPL is concluded */
    foreach ($deciderMadeDecision->getClearingEvents() as $event) {
      assertThat($event->getEventId(), is(anyOf($addedEventIds)));
    }

    $this->rmRepo();
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
    /* pfile 4 refers to 3DFX file in upload 1 (A.zip) and folder A/1 */
    $pfile = 4;

    $this->dbManager->queryOnce("DELETE FROM license_file");
    $this->dbManager->queryOnce("INSERT INTO license_file (fl_pk,rf_fk,pfile_fk,agent_fk) VALUES(12222,$licId1,$pfile,$agentNomosId)");
    $this->dbManager->queryOnce("INSERT INTO highlight (fl_fk,start,len) VALUES(12222,12,3)");
    $this->dbManager->queryOnce("INSERT INTO highlight (fl_fk,start,len) VALUES(12222,18,3)");

    /* uploadId 1 includes pfile 4 */
    list($success,$output,$retCode) = $runner->run($uploadId=1, $userId=2, $groupId=2, $jobId=31, $args="");

    $this->assertTrue($success, 'cannot run runner');
    $this->assertEquals($retCode, 0, 'decider failed (did you make test?): '.$output);

    assertThat($this->getHeartCount($output), equalTo(0));

    $uploadBounds = $this->uploadDao->getParentItemBounds($uploadId);
    $decisions = $this->clearingDao->getFileClearingsFolder($uploadBounds, $groupId);
    assertThat($decisions, is(arrayWithSize(0)));

    $this->rmRepo();
  }


  /** @group Functional */
  public function testDeciderMockedScanWithForceDecision()
  {
    $this->runnerDeciderScanWithoutDecision($this->runnerMock);
  }

  /** @group Functional */
  public function testDeciderRealScanWithForceDecision()
  {
    $this->runnerDeciderScanWithoutDecision($this->runnerCli);
  }

  private function runnerDeciderScanWithoutDecision($runner)
  {
    $this->setUpTables();
    $this->setUpRepo();

    $jobId = 42;

    $licenseRef1 = $this->licenseDao->getLicenseByShortName("GPL")->getRef();
    $licenseRef2 = $this->licenseDao->getLicenseByShortName("3DFX")->getRef();

    $agentLicId = $this->licenseDao->getLicenseByShortName("Adaptec")->getRef()->getId();

    $addedLicenses = array($licenseRef1, $licenseRef2);

    assertThat($addedLicenses, not(arrayContaining(null)));

    $agentId = 5; // Monk
    /* pfile_pk 4 refers to 3DFX file in upload 1 (A.zip) and 3DFX_B file in upload 2 (B.zip), it has GPL and 3DFX licenses */
    $pfile = 4;
		/* Adaptec license is added to a file */
    $this->dbManager->queryOnce("INSERT INTO license_file (fl_pk,rf_fk,pfile_fk,agent_fk) VALUES(12222,$agentLicId,$pfile,$agentId)");

    /* $itemId 23 (uploadtree_pk) refers to 3DFX_B file in upload 2 (B.zip) in B/1b folder 
     * uploadtree_pk 7 in upload 1 refers to same pfile_pk 4 */
    $itemTreeBounds = $this->uploadDao->getItemTreeBounds($itemId=23);
    assertThat($this->agentLicenseEventProcessor->getScannerEvents($itemTreeBounds), is(not(emptyArray())));

    $eventId1 = $this->clearingDao->insertClearingEvent($itemId, $userId=2, $groupId=2, $licenseRef1->getId(), false);
    /* There is no user 5, replaced with 2 */
    $eventId2 = $this->clearingDao->insertClearingEvent($itemId, $userId, $groupId, $licenseRef2->getId(), true);

    $this->dbManager->queryOnce("UPDATE clearing_event SET job_fk=$jobId");

    $addedEventIds = array($eventId1, $eventId2);

    list($success,$output,$retCode) = $runner->run($uploadId=2, $userId, $groupId, $jobId, $args="");

    $this->assertTrue($success, 'cannot run runner');
    $this->assertEquals($retCode, 0, 'decider failed: '.$output);
    assertThat($this->getHeartCount($output), equalTo(1));

    $uploadBounds = $this->uploadDao->getParentItemBounds($uploadId);
    $decisions = $this->clearingDao->getFileClearingsFolder($uploadBounds, $groupId);
		// No decision is made because there is no conclusion for Adaptec license
    assertThat($decisions, is(arrayWithSize(0)));
    
    $this->rmRepo();
  }

  public function testRunnerDeciderScanWithDecision()
  {
    $this->setUpTables();
    $this->setUpRepo();

    $jobId = 42;

    $licenseRef1 = $this->licenseDao->getLicenseByShortName("GPL")->getRef();
    $licenseRef2 = $this->licenseDao->getLicenseByShortName("3DFX")->getRef();

    $licenseId3 = $this->licenseDao->getLicenseByShortName("Adaptec")->getRef()->getId();
    $licenseRef3 = $this->licenseDao->getLicenseByShortName("Adaptec")->getRef();
    
    $addedLicenses = array($licenseRef1, $licenseRef2);

    assertThat($addedLicenses, not(arrayContaining(null)));

    $agentId = 5; // Monk
    /* pfile_pk 4 refers to 3DFX file in upload 1 (A.zip) and 3DFX_B file in upload 2 (B.zip), it has GPL and 3DFX licenses */
    $pfile = 4;
		/* Adaptec license is added to a file */
    $this->dbManager->queryOnce("INSERT INTO license_file (fl_pk,rf_fk,pfile_fk,agent_fk) VALUES(12222, $licenseId3, $pfile,$agentId)");

    /* $itemId 23 (uploadtree_pk) refers to 3DFX_B file in upload 2 (B.zip) in B/1b folder 
     * uploadtree_pk 7 in upload 1 refers to same pfile_pk 4 */
    $itemTreeBounds = $this->uploadDao->getItemTreeBounds($itemId=23);
    assertThat($this->agentLicenseEventProcessor->getScannerEvents($itemTreeBounds), is(not(emptyArray())));

    /* Conclude GPL */
    $eventId1 = $this->clearingDao->insertClearingEvent($itemId, $userId=2, $groupId=2, $licenseRef1->getId(), false);
    /* Remove 3DFX */
    $eventId2 = $this->clearingDao->insertClearingEvent($itemId, $userId, $groupId, $licenseRef2->getId(), true);
    /* Conclude Adaptec */
    $eventId3 = $this->clearingDao->insertClearingEvent($itemId, $userId, $groupId, $licenseRef3->getId(), false);
    
    $this->dbManager->queryOnce("UPDATE clearing_event SET job_fk=$jobId");

    $addedEventIds = array($eventId1, $eventId2);

    list($success,$output,$retCode) = $this->runnerMock->run($uploadId=2, $userId, $groupId, $jobId, $args="");

    $this->assertTrue($success, 'cannot run runner');
    $this->assertEquals($retCode, 0, 'decider failed: '.$output);
    assertThat($this->getHeartCount($output), equalTo(1));

    $uploadBounds = $this->uploadDao->getParentItemBounds($uploadId);
    $decisions = $this->clearingDao->getFileClearingsFolder($uploadBounds, $groupId);
		// GPL and Adaptec licenses concluded
    assertThat($decisions, is(arrayWithSize(1)));
    
    $this->rmRepo();
  }

}
