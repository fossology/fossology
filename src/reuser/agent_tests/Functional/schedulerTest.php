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

use Fossology\Lib\BusinessRules\ClearingDecisionFilter;
use Fossology\Lib\BusinessRules\ClearingDecisionProcessor;
use Fossology\Lib\Dao\ClearingDao;
use Fossology\Lib\Dao\LicenseDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Dao\HighlightDao;
use Fossology\Lib\Data\Highlight;
use Fossology\Lib\Data\AgentRef;
use Fossology\Lib\Data\ClearingDecision;
use Fossology\Lib\Data\DecisionTypes;
use Fossology\Lib\Data\DecisionScopes;
use Fossology\Lib\Data\LicenseMatch;
use Fossology\Lib\Data\Clearing\ClearingEvent;
use Fossology\Lib\Data\Clearing\ClearingLicense;
use Fossology\Lib\Data\Clearing\ClearingEventTypes;
use Fossology\Lib\Data\LicenseRef;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Test\TestPgDb;
use Fossology\Lib\BusinessRules\LicenseFilter;


class ReusercheduledTest extends \PHPUnit_Framework_TestCase
{
  /** @var TestPgDb */
  private $testDb;
  /** @var DbManager */
  private $dbManager;
  /** @var LicenseDao */
  private $licenseDao;
  /** @var ClearingDao */
  private $clearingDao;
  /** @var LicenseFilter */
  private $newestEditedLicenseSelector;
  /** @var ClearingDecisionFilter */
  private $clearingDecisionFilter;
  /** @var ClearingDecisionProcessor */
  private $clearingDecisionProcessor;
  /** @var UploadDao */
  private $uploadDao;
  /** @var HighlightDao */
  private $highlightDao;

  public function setUp()
  {
    $this->testDb = new TestPgDb("reuserSched".time());
    $this->dbManager = $this->testDb->getDbManager();

    $this->licenseDao = new LicenseDao($this->dbManager);
    $this->uploadDao = new UploadDao($this->dbManager);
    $this->highlightDao = new HighlightDao($this->dbManager);
    $this->clearingDecisionFilter = new ClearingDecisionFilter();
    $this->newestEditedLicenseSelector = new LicenseFilter($this->clearingDecisionFilter);
    $this->clearingDao = new ClearingDao($this->dbManager, $this->newestEditedLicenseSelector, $this->uploadDao);
//    $this->clearingDecisionProcessor = new ClearingDecisionProcessor();
  }

  public function tearDown()
  {
    $this->testDb = null;
    $this->dbManager = null;
    $this->licenseDao = null;
    $this->highlightDao = null;
    $this->clearingDao = null;
  }

  private function runReuser($uploadId, $userId=2, $groupId=2, $jobId=1, $args="")
  {
    $sysConf = $this->testDb->getFossSysConf();

    $agentName = "reuser";

    $agentDir = dirname(dirname(__DIR__));
    $execDir = "$agentDir/agent";
    system("install -D $agentDir/VERSION $sysConf/mods-enabled/$agentName/VERSION");

    $pipeFd = popen("echo $uploadId | $execDir/$agentName --userID=$userId --groupID=$groupId --jobId=$jobId --scheduler_start -c $sysConf $args", "r");
    $this->assertTrue($pipeFd !== false, 'running reuser failed');

    $output = "";
    while (($buffer = fgets($pipeFd, 4096)) !== false) {
      $output .= $buffer;
    }
    $retCode = pclose($pipeFd);

    unlink("$sysConf/mods-enabled/$agentName/VERSION");
    rmdir("$sysConf/mods-enabled/$agentName");
    rmdir("$sysConf/mods-enabled");

    return array($output,$retCode);
  }

  private function setUpRepo()
  {
    $sysConf = $this->testDb->getFossSysConf();

    $confFile = $sysConf."/fossology.conf";
    $fakeInstallationDir = "$sysConf/inst";
    $libDir = dirname(dirname(dirname(__DIR__)))."/lib";

    $config = "[FOSSOLOGY]\ndepth = 0\npath = $sysConf/repo\n[DIRECTORIES]\nMODDIR = $fakeInstallationDir";
    file_put_contents($confFile, $config);
    mkdir($fakeInstallationDir);

    $topDir = dirname(dirname(dirname(dirname(__DIR__))));
    system("install -D $topDir/VERSION $sysConf");

    system("ln -s $libDir $fakeInstallationDir/lib");
    mkdir("$fakeInstallationDir/www/ui/", 0777, true);
    touch("$fakeInstallationDir/www/ui/ui-menus.php");

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
    $this->testDb->createPlainTables(array('upload','upload_reuse','uploadtree','uploadtree_a','license_ref','license_ref_bulk','clearing_decision','clearing_licenses','clearing_event','license_file','highlight','highlight_bulk','agent','pfile','ars_master','users','group_user_member'),false);
    $this->testDb->createSequences(array('agent_agent_pk_seq','pfile_pfile_pk_seq','upload_upload_pk_seq','nomos_ars_ars_pk_seq','license_file_fl_pk_seq','license_ref_rf_pk_seq','license_ref_bulk_lrb_pk_seq','clearing_decision_clearing_id_seq','clearing_event_clearing_event_pk_seq'),false);
    $this->testDb->createViews(array('license_file_ref'),false);
    $this->testDb->createConstraints(array('agent_pkey','pfile_pkey','upload_pkey_idx','FileLicense_pkey','clearing_event_pkey'),false);
    $this->testDb->alterTables(array('agent','pfile','upload','ars_master','license_ref_bulk','clearing_event','clearing_decision','clearing_licenses','license_file','highlight'),false);
    $this->testDb->getDbManager()->queryOnce("alter table uploadtree_a inherit uploadtree");
    $this->testDb->getDbManager()->queryOnce("create table nomos_ars() inherits(ars_master)");
    $this->testDb->getDbManager()->queryOnce("create table monk_ars() inherits(ars_master)");

    $this->testDb->insertData(array('pfile','upload','uploadtree_a','users','group_user_member','agent','license_file','nomos_ars','monk_ars'), false);
    $this->testDb->insertData_license_ref();

    $this->testDb->resetSequenceAsMaxOf('agent_agent_pk_seq', 'agent', 'agent_pk');
  }

  private function getHeartCount($output)
  {
    $matches = array();
    if (preg_match("/.*HEART: ([0-9]*).*/", $output, $matches))
      return intval($matches[1]);
    else
      return 0;
  }

  private function getFilteredClearings($uploadId)
  {
    $bounds = $this->uploadDao->getParentItemBounds($uploadId);

    $clearings = $this->clearingDao->getFileClearingsFolder($bounds);
    return $this->clearingDecisionFilter->filterRelevantClearingDecisions($clearings);
  }

  /** @group Functional */
  public function testReuserScanWithoutAnyUploadToCopyAndNoClearing()
  {
    $this->setUpTables();
    $this->setUpRepo();

    list($output,$retCode) = $this->runReuser($uploadId=1);

    $this->assertEquals($retCode, 0, 'reuser failed: '.$output);

    assertThat($this->getHeartCount($output), equalTo(0));

    $bounds = $this->uploadDao->getParentItemBounds($uploadId);
    assertThat($this->clearingDao->getFileClearingsFolder($bounds), is(emptyArray()));

    $this->rmRepo();
  }

  private function getClearingLicenses()
  {
  }

  /** @group Functional */
  public function testReuserScanWithALocalClearing()
  {
    $this->setUpTables();
    $this->setUpRepo();

    $this->uploadDao->addReusedUpload($uploadId=3,$reusedUpload=2);

    $addedLicense = new ClearingLicense($this->licenseDao->getLicenseByShortName("GPL-3.0")->getRef(), false, "42", "44");
    $removedLicense = new ClearingLicense($this->licenseDao->getLicenseByShortName("3DFX")->getRef(), true, "-42", "-44");

    assertThat($addedLicense,notNullValue());
    assertThat($removedLicense,notNullValue());

    $addedLicenses = array($addedLicense);
    $removedLicenses = array($removedLicense);

    $this->clearingDao->insertClearingDecision($originallyClearedItemId=23, $userId=2, DecisionTypes::IDENTIFIED, DecisionScopes::ITEM, $addedLicenses, $removedLicenses);
    /* upload 3 in the test db is the same as upload 2
     * items 13-24 in upload 2 correspond to 33-44 */
    $reusingUploadItemShift = 20;

    list($output,$retCode) = $this->runReuser($uploadId, $userId);

    $this->assertEquals($retCode, 0, 'reuser failed: '.$output);

    assertThat($this->getHeartCount($output), equalTo(1));

    $newUploadClearings = $this->getFilteredClearings($uploadId);
    $potentiallyReusableClearings = $this->getFilteredClearings($reusedUpload);

    assertThat($newUploadClearings, is(arrayWithSize(1)));

    assertThat($potentiallyReusableClearings, is(arrayWithSize(1)));
    /** @var ClearingDecision */
    $potentiallyReusableClearing = $potentiallyReusableClearings[0];
    /** @var ClearingDecision */
    $newClearing = $newUploadClearings[0];

    assertThat($newClearing, not(equalTo($potentiallyReusableClearing)));

    assertThat($newClearing->getPositiveLicenses(), arrayContainingInAnyOrder($addedLicenses));
    assertThat($newClearing->getNegativeLicenses(), arrayContainingInAnyOrder($removedLicenses));

    assertThat($newClearing->getType(), equalTo($potentiallyReusableClearing->getType()));
    assertThat($newClearing->getScope(), equalTo($potentiallyReusableClearing->getScope()));

    assertThat($newClearing->getUploadTreeId(), equalTo($potentiallyReusableClearing->getUploadTreeId() + $reusingUploadItemShift));

    $this->rmRepo();
  }

  /** @group Functional */
  public function testReuserScanWithARepoClearing()
  {
    $this->setUpTables();
    $this->setUpRepo();

    $this->uploadDao->addReusedUpload($uploadId=3,$reusedUpload=2);

    $addedLicense = new ClearingLicense($this->licenseDao->getLicenseByShortName("GPL-3.0")->getRef(), false, "42", "44");
    $removedLicense = new ClearingLicense($this->licenseDao->getLicenseByShortName("3DFX")->getRef(), true, "-42", "-44");

    assertThat($addedLicense,notNullValue());
    assertThat($removedLicense,notNullValue());

    $addedLicenses = array($addedLicense);
    $removedLicenses = array($removedLicense);

    $userId = 2;
    $originallyClearedItemId=23;
    /*foreach ($addedLicenses as $addedLicense)
    {
      $this->clearingDao->addClearing($originallyClearedItemId, $userId, $addedLicense->getId(), ClearingEventTypes::USER);
    }
    foreach ($removedLicenses as $removedLicense)
    {
      $this->clearingDao->removeClearing($originallyClearedItemId, $userId, $removedLicense->getId(), ClearingEventTypes::USER);
    }

    $itemTreeBounds = $this->uploadDao->getItemTreeBounds($originallyClearedItemId);
    $this->clearingDecisionProcessor->makeDecisionFromLastEvents($itemTreeBounds, $userId, DecisionTypes::IDENTIFIED, $global=true);*/
    $this->clearingDao->insertClearingDecision($originallyClearedItemId=23, $userId=2, DecisionTypes::IDENTIFIED, DecisionScopes::REPO, $addedLicenses, $removedLicenses);

    /* upload 3 in the test db is the same as upload 2
     * items 13-24 in upload 2 correspond to 33-44 */
    $reusingUploadItemShift = 20;

    list($output,$retCode) = $this->runReuser($uploadId, $userId);

    $this->assertEquals($retCode, 0, 'reuser failed: '.$output);

    assertThat($this->getHeartCount($output), equalTo(1));

    $newUploadClearings = $this->getFilteredClearings($uploadId);
    $potentiallyReusableClearings = $this->getFilteredClearings($reusedUpload);

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

    assertThat($newClearing->getPositiveLicenses(), arrayContainingInAnyOrder($addedLicenses));
    assertThat($newClearing->getNegativeLicenses(), arrayContainingInAnyOrder($removedLicenses));

    assertThat($newClearing->getType(), equalTo($potentiallyReusableClearing->getType()));
    assertThat($newClearing->getScope(), equalTo($potentiallyReusableClearing->getScope()));

    assertThat($newClearing->getUploadTreeId(), equalTo($potentiallyReusableClearing->getUploadTreeId()));

    /* reuser should have created a correct local event history */
    $newEvents = $this->clearingDao->getRelevantClearingEvents($userId, $originallyClearedItemId + $reusingUploadItemShift);

    /* TODO this assert Fails: bug or bad expectation? */
    assertThat($newEvents, is(arrayWithSize(count($addedLicenses)+count($removedLicenses))));

    /** @var ClearingEvent $newEvent */
    foreach($newEvents as $newEvent)
    {
      assertThat($newEvent->getClearingLicense(), anyOf($newEvent->isRemoved() ? $removedLicenses : $addedLicenses));
    }

    $this->rmRepo();
  }
}
