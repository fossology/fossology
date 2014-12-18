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

namespace Fossology\Reuser\Test;

use Fossology\Lib\BusinessRules\ClearingDecisionFilter;
use Fossology\Lib\BusinessRules\ClearingDecisionProcessor;
use Fossology\Lib\Dao\ClearingDao;
use Fossology\Lib\Dao\LicenseDao;
use Fossology\Lib\Dao\AgentDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Dao\HighlightDao;
use Fossology\Lib\Data\ClearingDecision;
use Fossology\Lib\Data\DecisionTypes;
use Fossology\Lib\Data\DecisionScopes;
use Fossology\Lib\Data\Clearing\ClearingEvent;
use Fossology\Lib\Data\Clearing\ClearingLicense;
use Fossology\Lib\Data\Clearing\ClearingEventTypes;
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
  /** @var ClearingDecisionFilter */
  private $clearingDecisionFilter;
  /** @var ClearingDecisionProcessor */
  private $clearingDecisionProcessor;
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
    $this->clearingDecisionFilter = new ClearingDecisionFilter();
    $this->clearingDao = new ClearingDao($this->dbManager, $this->uploadDao);

    $logger = M::mock('Monolog\Logger');
    $agentDao = new AgentDao($this->dbManager, $logger);

    $this->runnerMock = new SchedulerTestRunnerMock($this->dbManager, $agentDao, $this->clearingDao, $this->uploadDao, $this->clearingDecisionFilter);
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
    $this->testDb->createSequences(array('agent_agent_pk_seq','pfile_pfile_pk_seq','upload_upload_pk_seq','nomos_ars_ars_pk_seq','license_file_fl_pk_seq','license_ref_rf_pk_seq','license_ref_bulk_lrb_pk_seq','clearing_decision_clearing_id_seq','clearing_event_clearing_event_pk_seq'),false);
    $this->testDb->createViews(array('license_file_ref'),false);
    $this->testDb->createConstraints(array('agent_pkey','pfile_pkey','upload_pkey_idx','FileLicense_pkey','clearing_event_pkey'),false);
    $this->testDb->alterTables(array('agent','pfile','upload','ars_master','license_ref_bulk','clearing_event','clearing_decision','license_file','highlight'),false);
    $this->testDb->getDbManager()->queryOnce("alter table uploadtree_a inherit uploadtree");
    $this->testDb->getDbManager()->queryOnce("create table nomos_ars() inherits(ars_master)");
    $this->testDb->getDbManager()->queryOnce("create table monk_ars() inherits(ars_master)");
    $this->testDb->createInheritedTables();

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

    list($success, $output,$retCode) = $runner->run($uploadId=1, $userId=2);

    $this->assertTrue($success, 'cannot run runner');
    $this->assertEquals($retCode, 0, 'reuser failed: '.$output);

    assertThat($this->getHeartCount($output), equalTo(0));

    $bounds = $this->uploadDao->getParentItemBounds($uploadId);
    assertThat($this->clearingDao->getFileClearingsFolder($bounds, $groupId=5), is(emptyArray()));

    $this->rmRepo();
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

    $licenseRef1 = $this->licenseDao->getLicenseByShortName("GPL-3.0")->getRef();
    $licenseRef2 = $this->licenseDao->getLicenseByShortName("3DFX")->getRef();
    
    $addedLicenses = array($licenseRef1, $licenseRef2);

    assertThat($addedLicenses, not(arrayContaining(null)));

    $eventId1 = $this->clearingDao->insertClearingEvent($originallyClearedItemId=23, $userId=2, $groupId=3, $licenseRef1->getId(), false);
    $eventId2 = $this->clearingDao->insertClearingEvent($originallyClearedItemId, 5, $groupId, $licenseRef2->getId(), true);
    
    $addedEventIds = array($eventId1, $eventId2);

    $this->clearingDao->createDecisionFromEvents($originallyClearedItemId, $userId, $groupId, DecisionTypes::IDENTIFIED, DecisionScopes::ITEM, $addedEventIds);

    list($success,$output,$retCode) = $runner->run($uploadId=3);

    $this->assertTrue($success, 'cannot run runner');
    $this->assertEquals($retCode, 0, 'reuser failed: '.$output);

    assertThat($this->getHeartCount($output), equalTo(0));

    $decisions = $this->getFilteredClearings($uploadId, $groupId);
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

    $this->uploadDao->addReusedUpload($uploadId=3,$reusedUpload=2);
    
    $licenseRef1 = $this->licenseDao->getLicenseByShortName("GPL-3.0")->getRef();
    $licenseRef2 = $this->licenseDao->getLicenseByShortName("3DFX")->getRef();
    
    $addedLicenses = array($licenseRef1, $licenseRef2);

    assertThat($addedLicenses, not(arrayContaining(null)));

    $clearingLicense1 = new ClearingLicense($licenseRef1, false, ClearingEventTypes::USER, "42", "44");
    $clearingLicense2 = new ClearingLicense($licenseRef2, true, ClearingEventTypes::USER, "-42", "-44");
    
    $eventId1 = $this->clearingDao->insertClearingEvent($originallyClearedItemId=23, $userId=2, $groupId=3,
            $clearingLicense1->getLicenseId(), $clearingLicense1->isRemoved(), 
            $clearingLicense1->getType(), $clearingLicense1->getReportinfo(), $clearingLicense1->getComment());
    
    $eventId2 = $this->clearingDao->insertClearingEvent($originallyClearedItemId=23, $userId=2, $groupId=3,
            $clearingLicense2->getLicenseId(), $clearingLicense2->isRemoved(), 
            $clearingLicense2->getType(), $clearingLicense2->getReportinfo(), $clearingLicense2->getComment());
      
    $addedEventIds = array($eventId1, $eventId2);

    $this->clearingDao->createDecisionFromEvents($originallyClearedItemId, $userId, $groupId, DecisionTypes::IDENTIFIED, DecisionScopes::ITEM, $addedEventIds);
    
    /* upload 3 in the test db is the same as upload 2
     * items 13-24 in upload 2 correspond to 33-44 */
    $reusingUploadItemShift = 20;

    list($success,$output,$retCode) = $runner->run($uploadId, $userId, $groupId);

    $this->assertTrue($success, 'cannot run runner');
    $this->assertEquals($retCode, 0, 'reuser failed: '.$output);

    assertThat($this->getHeartCount($output), equalTo(1));

    $newUploadClearings = $this->getFilteredClearings($uploadId, $groupId);
    $potentiallyReusableClearings = $this->getFilteredClearings($reusedUpload, $groupId);

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

    $this->uploadDao->addReusedUpload($uploadId=3,$reusedUpload=2);

    
    $licenseRef1 = $this->licenseDao->getLicenseByShortName("GPL-3.0")->getRef();
    $licenseRef2 = $this->licenseDao->getLicenseByShortName("3DFX")->getRef();
    
    $addedLicenses = array($licenseRef1, $licenseRef2);

    assertThat($addedLicenses, not(arrayContaining(null)));

    $clearingLicense1 = new ClearingLicense($licenseRef1, false, ClearingEventTypes::USER, "42", "44");
    $clearingLicense2 = new ClearingLicense($licenseRef2, true, ClearingEventTypes::USER, "-42", "-44");
    
    $clearingLicenses = array($clearingLicense1, $clearingLicense2);
    
    $eventId1 = $this->clearingDao->insertClearingEvent($originallyClearedItemId=23, $userId=2, $groupId=3,
            $clearingLicense1->getLicenseId(), $clearingLicense1->isRemoved(), 
            $clearingLicense1->getType(), $clearingLicense1->getReportinfo(), $clearingLicense1->getComment());
    
    $eventId2 = $this->clearingDao->insertClearingEvent($originallyClearedItemId=23, $userId=2, $groupId=3,
            $clearingLicense2->getLicenseId(), $clearingLicense2->isRemoved(), 
            $clearingLicense2->getType(), $clearingLicense2->getReportinfo(), $clearingLicense2->getComment());
      
    $addedEventIds = array($eventId1, $eventId2);

    $this->clearingDao->createDecisionFromEvents($originallyClearedItemId, $userId, $groupId, DecisionTypes::IDENTIFIED, DecisionScopes::REPO, $addedEventIds);

    /* upload 3 in the test db is the same as upload 2
     * items 13-24 in upload 2 correspond to 33-44 */
    $reusingUploadItemShift = 20;

    list($success,$output,$retCode) = $runner->run($uploadId, $userId, $groupId);
    
    $this->assertTrue($success, 'cannot run runner');
    $this->assertEquals($retCode, 0, 'reuser failed: '.$output);

    assertThat($this->getHeartCount($output), equalTo(0));

    $newUploadClearings = $this->getFilteredClearings($uploadId, $groupId);
    $potentiallyReusableClearings = $this->getFilteredClearings($reusedUpload, $groupId);

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
    $newEvents = $this->clearingDao->getRelevantClearingEvents($bounds, $groupId);

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
