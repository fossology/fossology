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

use Fossology\Lib\Dao\ClearingDao;
use Fossology\Lib\Dao\HighlightDao;
use Fossology\Lib\Dao\LicenseDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Data\AgentRef;
use Fossology\Lib\Data\Clearing\ClearingEvent;
use Fossology\Lib\Data\Highlight;
use Fossology\Lib\Data\LicenseMatch;
use Fossology\Lib\Data\LicenseRef;
use Fossology\Lib\Data\Tree\ItemTreeBounds;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Test\TestPgDb;


class MonkScheduledTest extends PHPUnit_Framework_TestCase
{
  /** @var TestPgDb */
  private $testDb;
  /** @var DbManager */
  private $dbManager;
  /** @var LicenseDao */
  private $licenseDao;
  /** @var ClearingDao */
  private $clearingDao;
  /** @var UploadDao */
  private $uploadDao;
  /** @var HighlightDao */
  private $highlightDao;

  public function setUp()
  {
    $this->testDb = new TestPgDb("monkSched".time());
    $this->dbManager = $this->testDb->getDbManager();

    $this->licenseDao = new LicenseDao($this->dbManager);
    $this->uploadDao = new UploadDao($this->dbManager);
    $this->highlightDao = new HighlightDao($this->dbManager);
    $this->clearingDao = new ClearingDao($this->dbManager, $this->uploadDao);
  }

  public function tearDown()
  {
    $this->testDb = null;
    $this->dbManager = null;
    $this->licenseDao = null;
    $this->highlightDao = null;
    $this->clearingDao = null;
  }

  private function runMonk($uploadId, $userId=2, $groupId=2, $jobId=1, $args="")
  {
    $sysConf = $this->testDb->getFossSysConf();

    $agentName = "monk";

    $agentDir = dirname(dirname(__DIR__));
    $execDir = __DIR__;
    system("install -D $agentDir/VERSION $sysConf/mods-enabled/$agentName/VERSION");

    $pipeFd = popen("echo $uploadId | $execDir/$agentName -c $sysConf --userID=$userId --groupID=$groupId --jobId=$jobId --scheduler_start $args", "r");
    $this->assertTrue($pipeFd !== false, 'running monk failed');

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
    unlink($sysConf."/fossology.conf");
  }

  private function setUpTables()
  {
    $this->testDb->createPlainTables(array('upload','uploadtree','uploadtree_a','license_ref','license_ref_bulk',
        'clearing_event','clearing_decision','clearing_decision_event','license_file','highlight','highlight_bulk','agent','pfile','ars_master','users'),false);
    $this->testDb->createSequences(array('agent_agent_pk_seq','pfile_pfile_pk_seq','upload_upload_pk_seq','nomos_ars_ars_pk_seq','license_file_fl_pk_seq','license_ref_rf_pk_seq','license_ref_bulk_lrb_pk_seq','clearing_event_clearing_event_pk_seq'),false);
    $this->testDb->createViews(array('license_file_ref'),false);
    $this->testDb->createConstraints(array('agent_pkey','pfile_pkey','upload_pkey_idx','FileLicense_pkey','clearing_event_pkey'),false);
    $this->testDb->alterTables(array('agent','pfile','upload','ars_master','license_ref_bulk','clearing_event','license_file','highlight'),false);
    $this->testDb->getDbManager()->queryOnce("alter table uploadtree_a inherit uploadtree");
    $this->testDb->createInheritedTables();
    $this->testDb->insertData(array('pfile','upload','uploadtree_a','users'), false);
    $this->testDb->insertData_license_ref();
  }

  private function getHeartCount($output)
  {
    $matches = array();
    if (preg_match("/.*HEART: ([0-9]*).*/", $output, $matches))
      return intval($matches[1]);
    else
      return 0;
  }

  /** @group Functional */
  public function testRunMonkScan()
  {
    $this->setUpTables();
    $this->setUpRepo();

    list($output,$retCode) = $this->runMonk($uploadId=1);

    $this->rmRepo();

    $this->assertEquals($retCode, 0, 'monk failed: '.$output);

    $this->assertEquals(6, $this->getHeartCount($output));

    $bounds = $this->uploadDao->getParentItemBounds($uploadId);
    $matches = $this->licenseDao->getAgentFileLicenseMatches($bounds);

    $this->assertEquals($expected=2, count($matches));

    /** @var LicenseMatch */
    $licenseMatch = $matches[0];

    $this->assertEquals($expected=4, $licenseMatch->getFileId());

    /** @var LicenseRef */
    $matchedLicense = $licenseMatch->getLicenseRef();
    $this->assertEquals($matchedLicense->getShortName(), "GPL-3.0");

    /** @var AgentRef */
    $agentRef = $licenseMatch->getAgentRef();
    $this->assertEquals($agentRef->getAgentName(), "monk");

    $highlights = $this->highlightDao->getHighlightDiffs($this->uploadDao->getItemTreeBounds(7));

    $expectedHighlight = new Highlight(18, 35825, Highlight::MATCH, 20, 35819);
    $expectedHighlight->setLicenseId($matchedLicense->getId());

    $this->assertEquals(array($expectedHighlight), $highlights);

    $highlights = $this->highlightDao->getHighlightDiffs($this->uploadDao->getItemTreeBounds(11));

    $expectedHighlights = array();
    $expectedHighlights[] = new Highlight(18, 339, Highlight::MATCH, 20, 350);
    $expectedHighlights[] = new Highlight(340, 347, Highlight::CHANGED, 351, 357);
    $expectedHighlights[] = new Highlight(348, 35149, Highlight::MATCH, 358, 35819);
    foreach($expectedHighlights as $expectedHighlight) {
      $expectedHighlight->setLicenseId($matchedLicense->getId());
    }
    assertThat($highlights, containsInAnyOrder($expectedHighlights));
  }

  /** @group Functional */
  public function testRunMonkTwiceOnAScan()
  {
    $this->setUpTables();
    $this->setUpRepo();

    list($output,$retCode) = $this->runMonk($uploadId=1);
    list($output2,$retCode2) = $this->runMonk($uploadId);

    $this->assertEquals($retCode, 0, 'monk failed: '.$output);
    $this->assertEquals(6, $this->getHeartCount($output));

    $this->assertEquals($retCode2, 0, 'monk failed: '.$output2);
    $this->assertEquals(0, $this->getHeartCount($output2));

    $this->rmRepo();

    $bounds = $this->uploadDao->getParentItemBounds($uploadId);
    $matches = $this->licenseDao->getAgentFileLicenseMatches($bounds);

    $this->assertEquals($expected=2, count($matches));

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

  /** @group Functional */
  public function testRunMonkBulkScan()
  {
    $this->setUpTables();
    $this->setUpRepo();

    $userId = 2;
    $groupId = 2;
    $uploadTreeId = 1;
    $uploadId = 1;

    $licenseId = 225;
    $removing = false;
    $refText = "The GNU General Public License is a free, copyleft license for software and other kinds of works.";

    $jobId = 64;

    $bulkId = $this->licenseDao->insertBulkLicense($userId, $groupId, $uploadTreeId, $licenseId, $removing, $refText);

    $this->assertGreaterThan($expected=0, $bulkId);

    $bulkFlag = "-B"; // TODO agent_fomonkbulk::BULKFLAG
    $args = $bulkFlag.$bulkId;

    list($output,$retCode) = $this->runMonk($uploadId, $userId, $groupId, $jobId, $args);

    $this->rmRepo();

    $this->assertEquals($retCode, 0, 'monk failed: '.$output);
    $bounds6 = new ItemTreeBounds(6, 'uploadtree_a', 1, 15, 16);
    $bounds7 = new ItemTreeBounds(7, 'uploadtree_a', 1, 17, 18);
    $relevantDecisionsItem6 = $this->clearingDao->getRelevantClearingEvents($bounds6, $groupId);
    $relevantDecisionsItem7 = $this->clearingDao->getRelevantClearingEvents($bounds7, $groupId);

    assertThat(count($relevantDecisionsItem6),is(equalTo(1)));
    assertThat(count($relevantDecisionsItem7),is(equalTo(1)));
    $rfForACE = 225;
    assertThat($relevantDecisionsItem6,hasKeyInArray($rfForACE));
    /** @var ClearingEvent $clearingEvent */
    $clearingEvent = $relevantDecisionsItem6[$rfForACE];
    $eventId = $clearingEvent->getEventId();
    $bulkHighlights = $this->highlightDao->getHighlightBulk(6, $eventId);

    $this->assertEquals(1, count($bulkHighlights));

    /** @var Highlight $bulkHighlight */
    $bulkHighlight = $bulkHighlights[0];
    $this->assertEquals($licenseId, $bulkHighlight->getLicenseId());
    $this->assertEquals(Highlight::BULK, $bulkHighlight->getType());
    $this->assertEquals(3, $bulkHighlight->getStart());
    $this->assertEquals(103, $bulkHighlight->getEnd());

    $bulkHighlights = $this->highlightDao->getHighlightBulk(6);

    $this->assertEquals(1, count($bulkHighlights));
    $this->assertEquals($bulkHighlight, $bulkHighlights[0]);
  }

  /** @group Functional */
  public function testRunMonkBulkScanWithBadSearchForDiff()
  {
    $this->setUpTables();
    $this->setUpRepo();

    $userId = 2;
    $groupId = 2;
    $uploadTreeId = 1;
    $uploadId = 1;

    $licenseId = 225;
    $removing = "f";
    $refText = "The GNU General Public License is copyleft license for software and other kinds of works.";

    $jobId = 64;

    $bulkId = $this->licenseDao->insertBulkLicense($userId, $groupId, $uploadId, $uploadTreeId, $licenseId, $removing, $refText);

    $this->assertGreaterThan($expected=0, $bulkId);

    $bulkFlag = "-B"; // TODO agent_fomonkbulk::BULKFLAG
    $args = $bulkFlag.$bulkId;

    list($output,$retCode) = $this->runMonk($uploadId, $userId, $groupId, $jobId, $args);

    $this->rmRepo();

    $this->assertEquals($retCode, 0, "monk failed: $output");
    $bounds6 = new ItemTreeBounds(6, 'uploadtree_a', 1, 15, 16);
    $bounds7 = new ItemTreeBounds(7, 'uploadtree_a', 1, 17, 18);
    $relevantDecisionsItem6 = $this->clearingDao->getRelevantClearingEvents($bounds6, $groupId);
    $relevantDecisionsItem7 = $this->clearingDao->getRelevantClearingEvents($bounds7, $groupId);

    $this->assertEquals($expected=0, count($relevantDecisionsItem6));
    $this->assertEquals($expected=0, count($relevantDecisionsItem7));
  }
}
