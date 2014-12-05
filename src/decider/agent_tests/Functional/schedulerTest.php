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
    $this->testDb = new TestPgDb("reuserSched".time());
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
        
    $this->runnerMock = new SchedulerTestRunnerMock($this->dbManager, $this->clearingDao, $this->uploadDao, $this->clearingDecisionProcessor);
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
    $this->testDb->createPlainTables(array('upload','upload_reuse','uploadtree','uploadtree_a','license_ref','license_ref_bulk','clearing_decision','clearing_decision_event','clearing_event','license_file','highlight','highlight_bulk','agent','pfile','ars_master','users','group_user_member'),false);
    $this->testDb->createSequences(array('agent_agent_pk_seq','pfile_pfile_pk_seq','upload_upload_pk_seq','nomos_ars_ars_pk_seq','license_file_fl_pk_seq','license_ref_rf_pk_seq','license_ref_bulk_lrb_pk_seq','clearing_decision_clearing_id_seq','clearing_event_clearing_event_pk_seq','FileLicense_pkey'),false);
    $this->testDb->createViews(array('license_file_ref'),false);
    $this->testDb->createConstraints(array('agent_pkey','pfile_pkey','upload_pkey_idx','clearing_event_pkey'),false);
    $this->testDb->alterTables(array('agent','pfile','upload','ars_master','license_ref_bulk','clearing_event','clearing_decision','license_file','highlight'),false);
    $this->testDb->getDbManager()->queryOnce("alter table uploadtree_a inherit uploadtree");
    $this->testDb->createInheritedTables();
    $this->testDb->createInheritedArsTables(array('nomos','monk'));

    $this->testDb->insertData(array('pfile','upload','uploadtree_a','users','group_user_member','agent','license_file','nomos_ars','monk_ars'), false);
    $this->testDb->insertData_license_ref();

    $this->testDb->resetSequenceAsMaxOf('agent_agent_pk_seq', 'agent', 'agent_pk');
    //$this->testDb->resetSequenceAsMaxOf('FileLicense_pkey', 'license_file', 'fl_pk');
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

    $licenseRef1 = $this->licenseDao->getLicenseByShortName("GPL-3.0")->getRef();
    $licenseRef2 = $this->licenseDao->getLicenseByShortName("3DFX")->getRef();
    
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
    
    /* @var ClearingDecision $deciderMadeDecision*/
    $deciderMadeDecision = $decisions[0];
    
    foreach ($deciderMadeDecision->getClearingEvents() as $event) {
      assertThat($event->getEventId(), is(anyOf($addedEventIds)));
    }

    $this->rmRepo();
  }

  public function testDeciderScanWithTwoEventAndNoAgentShouldMakeADecision()
  {
    $this->setUpTables();
    $this->setUpRepo();
    
    $dbManager = M::mock(DbManager::classname());
    $clearingDao = M::mock(ClearingDao::classname());
    $uploadDao = M::mock(UploadDao::classname());
    $decisionProcessor = M::mock(ClearingDecisionProcessor::classname());

    
    $uploadId = 13243;
    
    /*
     * mock for Agent class
     **/
    $dbManager->shouldReceive('getSingleRow')->with(
            startsWith("SELECT agent_pk FROM agent"),
            array("decider"), anything())->andReturn(array('agent_pk' => $agentId=232));
    
    $dbManager->shouldReceive('existsTable')->andReturn(true);
    
    $dbManager->shouldReceive('getSingleRow')
            ->with(startsWith("INSERT INTO decider_ars"), arrayContaining($agentId, $uploadId), anything())
            ->andReturn(array('ars_pk' => $arsId=2));

    $dbManager->shouldReceive('booleanToDb')->with(true)->andReturn($t="pg thinks this is true");
    
    $dbManager->shouldReceive('getSingleRow')
            ->with(startsWith("UPDATE decider_ars"), arrayContaining($t, $arsId), anything())
            ->andReturn(array());
    
    $jobId = 42;
    $groupId = 6;
    $userId = 2;
    
    $itemIds = array(4343, 43);
    
    $bounds0 = M::mock(ItemTreeBounds::classname());
    $bounds0->shouldReceive('getItemId')->andReturn($itemIds[0]);
    $bounds1 = M::mock(ItemTreeBounds::classname());
    $bounds1->shouldReceive('getItemId')->andReturn($itemIds[1]);
    $bounds = array($bounds0, $bounds1);
    
    $uploadDao->shouldReceive('getItemTreeBounds')->with($itemIds[0])->andReturn($bounds[0]);
    $uploadDao->shouldReceive('getItemTreeBounds')->with($itemIds[1])->andReturn($bounds[1]);
    
    $clearingDao->shouldReceive('getItemsChangedBy')->with($jobId)->andReturn($itemIds);
    
    $dbManager->shouldReceive('begin')->times(count($itemIds));
    $dbManager->shouldReceive('commit')->times(count($itemIds));
    
    $decisionProcessor->shouldReceive('hasUnhandledScannerDetectedLicenses')
            ->with($bounds0, $groupId)->andReturn(true);
    $clearingDao->shouldReceive('markDecisionAsWip')
            ->with($itemIds[0], $userId, $groupId);
    
    $decisionProcessor->shouldReceive('hasUnhandledScannerDetectedLicenses')
            ->with($bounds1, $groupId)->andReturn(false);
    $decisionProcessor->shouldReceive('makeDecisionFromLastEvents')
            ->with($bounds1, $userId, $groupId, DecisionTypes::IDENTIFIED, false);
    
    $runner = new SchedulerTestRunnerMock($dbManager, $clearingDao, $uploadDao, $decisionProcessor);
    
    list($success,$output,$retCode) = $runner->run($uploadId, $userId, $groupId, $jobId, $args="");

    $this->assertTrue($success, 'cannot run runner');
    $this->assertEquals($retCode, 0, 'reuser failed: '.$output);

    assertThat($this->getHeartCount($output), equalTo(count($itemIds)));
    
    $this->rmRepo();
  }
  
  /** @group Functional */
  public function testDeciderMockedScanWithForceDecision()
  {
    $this->runnerDeciderScanWithForceDecision($this->runnerMock);
  }

  /** @group Functional */
  public function testDeciderRealScanWithForceDecision()
  {
    $this->runnerDeciderScanWithForceDecision($this->runnerCli);
  }

  private function runnerDeciderScanWithForceDecision($runner)
  {
    $this->setUpTables();
    $this->setUpRepo();
    
    $jobId = 42;

    $licenseRef1 = $this->licenseDao->getLicenseByShortName("GPL-3.0")->getRef();
    $licenseRef2 = $this->licenseDao->getLicenseByShortName("3DFX")->getRef();
    
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
    $this->assertEquals($retCode, 0, 'reuser failed: '.$output);

    assertThat($this->getHeartCount($output), equalTo(1));

    $uploadBounds = $this->uploadDao->getParentItemBounds($uploadId);
    $decisions = $this->clearingDao->getFileClearingsFolder($uploadBounds, $groupId);
    assertThat($decisions, is(arrayWithSize(1)));
    
    /* @var ClearingDecision $deciderMadeDecision*/
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
