<?php
/*
 SPDX-FileCopyrightText: © 2014-2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

use Fossology\Lib\Dao\ClearingDao;
use Fossology\Lib\Dao\HighlightDao;
use Fossology\Lib\Dao\LicenseDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Dao\UploadPermissionDao;
use Fossology\Lib\Data\Clearing\ClearingEvent;
use Fossology\Lib\Data\Highlight;
use Fossology\Lib\Data\Tree\ItemTreeBounds;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Test\TestPgDb;
use Monolog\Logger;

class bulkTest extends \PHPUnit\Framework\TestCase
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
  /** @var UploadPermissionDao */
  private $uploadPermDao;
  /** @var HighlightDao */
  private $highlightDao;

  protected function setUp() : void
  {
    $this->testDb = new TestPgDb("monkBulk");
    $this->dbManager = $this->testDb->getDbManager();

    $this->licenseDao = new LicenseDao($this->dbManager);
    $logger = new Logger("MonkBulkTest");
    $this->uploadPermDao = \Mockery::mock(UploadPermissionDao::class);
    $this->uploadDao = new UploadDao($this->dbManager, $logger, $this->uploadPermDao);
    $this->highlightDao = new HighlightDao($this->dbManager);
    $this->clearingDao = new ClearingDao($this->dbManager, $this->uploadDao);
  }

  protected function tearDown() : void
  {
    $this->testDb->fullDestruct();
    $this->testDb = null;
    $this->dbManager = null;
    $this->licenseDao = null;
    $this->highlightDao = null;
    $this->clearingDao = null;
  }

  private function runBulkMonk($userId = 2, $groupId = 2, $jobId = 1, $bulkId = 3)
  {
    $sysConf = $this->testDb->getFossSysConf();

    $agentName = "monkbulk";

    $agentDir = dirname(__DIR__,4).'/build/src/monk';
    $execDir = $agentDir.'/agent';
    system("install -D $agentDir/VERSION-monkbulk $sysConf/mods-enabled/$agentName/VERSION");

    $pipeFd = popen("echo '0\n$bulkId\n0' | $execDir/$agentName -c $sysConf --userID=$userId --groupID=$groupId --jobId=$jobId --scheduler_start", "r");
    $this->assertTrue($pipeFd !== false, 'running monk bulk failed');

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
    $this->testDb->createPlainTables(array('upload','uploadtree','license_ref','license_ref_bulk', 'license_set_bulk',
        'clearing_event','clearing_decision','report_info','clearing_decision_event','license_file','highlight','highlight_bulk','agent','pfile','ars_master','users', 'license_expression'),false);
    $this->testDb->createSequences(array('agent_agent_pk_seq','pfile_pfile_pk_seq','upload_upload_pk_seq','nomos_ars_ars_pk_seq','license_file_fl_pk_seq','license_ref_rf_pk_seq','license_ref_bulk_lrb_pk_seq','clearing_event_clearing_event_pk_seq'),false);
    $this->testDb->createViews(array('license_file_ref'),false);
    $this->testDb->createConstraints(array('agent_pkey','pfile_pkey','upload_pkey_idx','FileLicense_pkey','clearing_event_pkey', 'license_ref_bulk_pkey', 'license_set_bulk_fkey'),false);
    $this->testDb->alterTables(array('agent','pfile','upload','ars_master','license_ref_bulk','license_ref','license_set_bulk','clearing_event','license_file','highlight', 'license_expression'),false);
    $this->testDb->createInheritedTables();
    $this->testDb->insertData(array('pfile','upload','uploadtree_a','users'), false);
    $this->testDb->insertData_license_ref();
  }

  private function getHeartCount($output)
  {
    $matches = array();
    if (preg_match("/.*HEART: ([0-9]*).*/", $output, $matches)) {
      return intval($matches[1]);
    }
    else {
      return 0;
    }
  }

  /** @group Functional */
  public function testRunTwoIndependentMonkBulkScans()
  {
    $this->setUpTables();
    $this->setUpRepo();

    $userId = 2;
    $groupId = 2;
    $uploadTreeId = 1;

    $licenseId = 225;
    $removing = false;
    $refText = "The GNU General Public License is a free, copyleft license for software and other kinds of works.";

    $bulkId = $this->licenseDao->insertBulkLicense($userId, $groupId, $uploadTreeId,
      array($licenseId => array($removing,"","","")), $refText);

    $this->assertGreaterThan($expected=0, $bulkId);

    $jobId = 64;
    list($output,$retCode) = $this->runBulkMonk($userId, $groupId, $jobId, $bulkId);

    $this->assertEquals($retCode, 0, 'monk bulk failed: '.$output);
    $bounds6 = new ItemTreeBounds(6, 'uploadtree_a', 1, 17, 18);
    $bounds7 = new ItemTreeBounds(7, 'uploadtree_a', 1, 15, 16);
    $relevantDecisionsItem6 = $this->clearingDao->getRelevantClearingEvents($bounds6, $groupId);
    $relevantDecisionsItem7 = $this->clearingDao->getRelevantClearingEvents($bounds7, $groupId);

    assertThat(count($relevantDecisionsItem6),is(equalTo(1)));
    assertThat(count($relevantDecisionsItem7),is(equalTo(1)));
    assertThat($relevantDecisionsItem6,hasKeyInArray($licenseId));

    $refSecondText = "Our General Public Licenses are designed to make sure that you " .
               "have the freedom to distribute copies of free software";
    $licenseSecondId = 215;
    $bulkSecondId = $this->licenseDao->insertBulkLicense($userId, $groupId, $uploadTreeId,
      array($licenseSecondId => array($removing,"","","")), $refSecondText);

    $jobId++;
    list($output,$retCode) = $this->runBulkMonk($userId, $groupId, $jobId, $bulkSecondId);

    $this->assertEquals($retCode, 0, 'monk bulk failed: '.$output);
    $relevantDecisionsItemPfile3 = $this->clearingDao->getRelevantClearingEvents($bounds6, $groupId);
    $relevantDecisionsItemPfile4 = $this->clearingDao->getRelevantClearingEvents($bounds7, $groupId);
    assertThat(count($relevantDecisionsItemPfile3), is(equalTo(1)));

    assertThat(count($relevantDecisionsItemPfile4), is(equalTo(2)));
    assertThat($relevantDecisionsItemPfile4, hasKeyInArray($licenseSecondId));

    $this->rmRepo();
  }

  /** @group Functional */
  public function testRunMonkBulkScan()
  {
    $this->setUpTables();
    $this->setUpRepo();

    $userId = 2;
    $groupId = 2;
    $uploadTreeId = 1;

    $licenseId1 = 225;
    $removing1 = false;
    $licenseId2 = 213;
    $removing2 = false;
    $refText = "The GNU General Public License is a free, copyleft license for software and other kinds of works.";

    $bulkId = $this->licenseDao->insertBulkLicense($userId, $groupId, $uploadTreeId,
      array($licenseId1 => array($removing1,"","",""),
        $licenseId2 => array($removing2,"","","")), $refText);

    $this->assertGreaterThan($expected=0, $bulkId);

    $jobId = 64;
    list($output,$retCode) = $this->runBulkMonk($userId, $groupId, $jobId, $bulkId);
    $this->assertEquals(6, $this->getHeartCount($output));
    $this->rmRepo();

    $this->assertEquals($retCode, 0, 'monk bulk failed: '.$output);
    $bounds6 = new ItemTreeBounds(6, 'uploadtree_a', 1, 17, 18);
    $bounds7 = new ItemTreeBounds(7, 'uploadtree_a', 1, 15, 16);
    $relevantDecisionsItem6 = $this->clearingDao->getRelevantClearingEvents($bounds6, $groupId);
    $relevantDecisionsItem7 = $this->clearingDao->getRelevantClearingEvents($bounds7, $groupId);

    assertThat(count($relevantDecisionsItem6),is(equalTo(2)));
    assertThat(count($relevantDecisionsItem7),is(equalTo(2)));
    $rfForACE = 225;
    assertThat($relevantDecisionsItem6,hasKeyInArray($rfForACE));
    /** @var ClearingEvent $clearingEvent */
    $clearingEvent = $relevantDecisionsItem6[$rfForACE];
    $eventId = $clearingEvent->getEventId();
    $bulkHighlights = $this->highlightDao->getHighlightBulk(6, $eventId);

    assertThat(count($bulkHighlights), is(1));

    /** @var Highlight $bulkHighlight1 */
    $bulkHighlight1 = $bulkHighlights[0];
    assertThat($bulkHighlight1->getLicenseId(), is(equalTo($licenseId1)));
    assertThat($bulkHighlight1->getType(), is(equalTo(Highlight::BULK)));
    assertThat($bulkHighlight1->getStart(), is(3));
    assertThat($bulkHighlight1->getEnd(), is(103));

    $rfForACE = 213;
    assertThat($relevantDecisionsItem6,hasKeyInArray($rfForACE));
    /** @var ClearingEvent $clearingEvent */
    $clearingEvent = $relevantDecisionsItem6[$rfForACE];
    $eventId = $clearingEvent->getEventId();
    $bulkHighlights = $this->highlightDao->getHighlightBulk(6, $eventId);

    assertThat(count($bulkHighlights), is(1));

    /** @var Highlight $bulkHighlight1 */
    $bulkHighlight2 = $bulkHighlights[0];
    assertThat($bulkHighlight2->getLicenseId(), is(equalTo($licenseId2)));
    assertThat($bulkHighlight2->getType(), is(equalTo(Highlight::BULK)));
    assertThat($bulkHighlight2->getStart(), is(3));
    assertThat($bulkHighlight2->getEnd(), is(103));

    $bulkHighlights = $this->highlightDao->getHighlightBulk(6);

    assertThat(count($bulkHighlights), is(equalTo(2)));
    assertThat($bulkHighlights, containsInAnyOrder($bulkHighlight1, $bulkHighlight2));
  }

  /** @group Functional */
  public function testRunMonkBulkScanWithMultipleLicenses()
  {
    $this->setUpTables();
    $this->setUpRepo();

    $userId = 2;
    $groupId = 2;
    $uploadTreeId = 1;

    $licenseId = 225;
    $removing = false;
    $refText = "The GNU General Public License is a free, copyleft license for software and other kinds of works.";

    $bulkId = $this->licenseDao->insertBulkLicense($userId, $groupId, $uploadTreeId,
      array($licenseId => array($removing,"","","")), $refText);

    $this->assertGreaterThan($expected = 0, $bulkId);

    $jobId = 64;
    list($output, $retCode) = $this->runBulkMonk($userId, $groupId, $jobId, $bulkId);
    $this->assertEquals(6, $this->getHeartCount($output));
    $this->rmRepo();

    $this->assertEquals($retCode, 0, 'monk bulk failed: ' . $output);
    $bounds6 = new ItemTreeBounds(6, 'uploadtree_a', 1, 17, 18);
    $bounds7 = new ItemTreeBounds(7, 'uploadtree_a', 1, 15, 16);
    $relevantDecisionsItem6 = $this->clearingDao->getRelevantClearingEvents($bounds6, $groupId);
    $relevantDecisionsItem7 = $this->clearingDao->getRelevantClearingEvents($bounds7, $groupId);

    assertThat(count($relevantDecisionsItem6), is(equalTo(1)));
    assertThat(count($relevantDecisionsItem7), is(equalTo(1)));
    $rfForACE = 225;
    assertThat($relevantDecisionsItem6, hasKeyInArray($rfForACE));
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

    $licenseId = 225;
    $removing = false;
    $refText = "The GNU General Public License is copyleft license for software and other kinds of works.";

    $jobId = 64;

    $bulkId = $this->licenseDao->insertBulkLicense($userId, $groupId, $uploadTreeId,
      array($licenseId => array($removing,"","","")), $refText);

    $this->assertGreaterThan($expected=0, $bulkId);

    list($output,$retCode) = $this->runBulkMonk($userId, $groupId, $jobId, $bulkId);

    $this->rmRepo();

    $this->assertEquals($retCode, 0, "monk bulk failed: $output");
    $bounds6 = new ItemTreeBounds(6, 'uploadtree_a', 1, 17, 18);
    $bounds7 = new ItemTreeBounds(7, 'uploadtree_a', 1, 15, 16);
    $relevantDecisionsItem6 = $this->clearingDao->getRelevantClearingEvents($bounds6, $groupId);
    $relevantDecisionsItem7 = $this->clearingDao->getRelevantClearingEvents($bounds7, $groupId);

    $this->assertEquals($expected=0, count($relevantDecisionsItem6));
    $this->assertEquals($expected=0, count($relevantDecisionsItem7));
  }

  /** @group Functional */
  public function testRunMonkBulkScanWithAShortSearch()
  {
    $this->setUpTables();
    $this->setUpRepo();

    $userId = 2;
    $groupId = 2;
    $uploadTreeId = 1;

    $licenseId = 225;
    $removing = false;
    $refText = "The GNU";

    $bulkId = $this->licenseDao->insertBulkLicense($userId, $groupId, $uploadTreeId,
      array($licenseId => array($removing,"","","")), $refText);

    $this->assertGreaterThan($expected=0, $bulkId);

    $jobId = 64;
    list($output,$retCode) = $this->runBulkMonk($userId, $groupId, $jobId, $bulkId);

    $this->rmRepo();

    $this->assertEquals($retCode, 0, 'monk bulk failed: '.$output);
    $bounds6 = new ItemTreeBounds(6, 'uploadtree_a', 1, 17, 18);
    $bounds7 = new ItemTreeBounds(7, 'uploadtree_a', 1, 15, 16);
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
    $this->assertEquals(10, $bulkHighlight->getEnd());

    $bulkHighlights = $this->highlightDao->getHighlightBulk(6);

    $this->assertEquals(1, count($bulkHighlights));
    $this->assertEquals($bulkHighlight, $bulkHighlights[0]);
  }

  /** @group Functional */
  public function testRunMonkBulkScanWithAnEmptySearchText()
  {
    $this->setUpTables();
    $this->setUpRepo();

    $userId = 2;
    $groupId = 2;
    $uploadTreeId = 1;

    $licenseId = 225;
    $removing = false;
    $refText = "";

    $bulkId = $this->licenseDao->insertBulkLicense($userId, $groupId, $uploadTreeId,
      array($licenseId => array($removing,"","","")), $refText);

    $this->assertGreaterThan($expected=0, $bulkId);

    $jobId = 64;
    list($output,$retCode) = $this->runBulkMonk($userId, $groupId, $jobId, $bulkId);

    $this->rmRepo();

    $this->assertEquals($retCode, 0, 'monk bulk failed: '.$output);
    $bounds6 = new ItemTreeBounds(6, 'uploadtree_a', 1, 17, 18);
    $bounds7 = new ItemTreeBounds(7, 'uploadtree_a', 1, 15, 16);
    $relevantDecisionsItem6 = $this->clearingDao->getRelevantClearingEvents($bounds6, $groupId);
    $relevantDecisionsItem7 = $this->clearingDao->getRelevantClearingEvents($bounds7, $groupId);

    assertThat(count($relevantDecisionsItem6),is(equalTo(0)));
    assertThat(count($relevantDecisionsItem7),is(equalTo(0)));

    $bulkHighlights = $this->highlightDao->getHighlightBulk(6);

    assertThat(count($bulkHighlights),is(equalTo(0)));
  }
}
