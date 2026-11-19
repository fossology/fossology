<?php
/*
 SPDX-FileCopyrightText: © 2014-2015, 2019 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Monk\Test;

use Fossology\Lib\Dao\ClearingDao;
use Fossology\Lib\Dao\HighlightDao;
use Fossology\Lib\Dao\LicenseDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Dao\UploadPermissionDao;
use Fossology\Lib\Data\AgentRef;
use Fossology\Lib\Data\Highlight;
use Fossology\Lib\Data\LicenseMatch;
use Fossology\Lib\Data\LicenseRef;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Test\TestInstaller;
use Fossology\Lib\Test\TestPgDb;
use Monolog\Logger;

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
  /** @var UploadDao */
  private $uploadDao;
  /** @var UploadPermissionDao */
  private $uploadPermDao;
  /** @var HighlightDao */
  private $highlightDao;

  protected function setUp() : void
  {
    $this->testDb = new TestPgDb("monkSched");
    $this->dbManager = $this->testDb->getDbManager();

    $this->licenseDao = new LicenseDao($this->dbManager);
    $logger = new Logger("SchedulerTest");
    $this->uploadPermDao = \Mockery::mock(UploadPermissionDao::class);
    $this->uploadDao = new UploadDao($this->dbManager, $logger, $this->uploadPermDao);
    $this->highlightDao = new HighlightDao($this->dbManager);
    $this->clearingDao = new ClearingDao($this->dbManager, $this->uploadDao);

    $this->agentDir = dirname(__DIR__, 4).'/build/src/monk';
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

  private function runMonk($uploadId, $userId=2, $groupId=2, $jobId=1, $args="")
  {
    $sysConf = $this->testDb->getFossSysConf();

    $agentName = "monk";
    $execDir = dirname(__DIR__,4).'/build/src/monk/agent';

    $pipeFd = popen("echo $uploadId | $execDir/$agentName -c $sysConf --userID=$userId --groupID=$groupId --jobId=$jobId --scheduler_start $args", "r");
    $this->assertTrue($pipeFd !== false, 'running monk failed');

    $output = "";
    while (($buffer = fgets($pipeFd, 4096)) !== false) {
      $output .= $buffer;
    }
    $retCode = pclose($pipeFd);

    return array($output,$retCode);
  }

  private function setUpRepo()
  {
    $sysConf = $this->testDb->getFossSysConf();
    $this->testInstaller = new TestInstaller($sysConf);
    $this->testInstaller->init();
    $this->testInstaller->cpRepo();
    $this->testInstaller->install($this->agentDir);
  }

  private function rmRepo()
  {
    $this->testInstaller->uninstall($this->agentDir);
    $this->testInstaller->clear();
    $this->testInstaller->rmRepo();
  }

  private function setUpTables()
  {
    $this->testDb->createPlainTables(array('upload','uploadtree','uploadtree_a','license_ref','license_ref_bulk','license_set_bulk',
        'clearing_event','clearing_decision','clearing_decision_event','license_file','highlight','highlight_bulk','agent','pfile','ars_master','users'),false);
    $this->testDb->createSequences(array('agent_agent_pk_seq','pfile_pfile_pk_seq','upload_upload_pk_seq','nomos_ars_ars_pk_seq','license_file_fl_pk_seq','license_ref_rf_pk_seq','license_ref_bulk_lrb_pk_seq','clearing_event_clearing_event_pk_seq','clearing_decision_clearing_decision_pk_seq'),false);
    $this->testDb->createViews(array('license_file_ref'),false);
    $this->testDb->createConstraints(array('agent_pkey','pfile_pkey','upload_pkey_idx','FileLicense_pkey','clearing_event_pkey','clearing_decision_pkey'),false);
    $this->testDb->alterTables(array('agent','pfile','upload','ars_master','license_ref_bulk','license_ref','license_set_bulk','clearing_event','license_file','highlight','clearing_decision'),false);
    $this->testDb->createInheritedTables();
    $this->testDb->insertData(array('pfile','upload','uploadtree_a','users'), false);
    $this->testDb->insertData_license_ref(200);
  }

  private function getHeartCount($output)
  {
    $matches = array();
    if (preg_match("/.*HEART: ([0-9]*).*/", $output, $matches))
    {
      return intval($matches[1]);
    }
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
    $this->assertEquals($matchedLicense->getShortName(), "GPL-3.0-only");

    /** @var AgentRef */
    $agentRef = $licenseMatch->getAgentRef();
    $this->assertEquals($agentRef->getAgentName(), "monk");

    $highlights = $this->highlightDao->getHighlightDiffs($this->uploadDao->getItemTreeBounds(7));

    $expectedHighlight = new Highlight(18, 35824, Highlight::MATCH, 0, 34505);
    $expectedHighlight->setLicenseId($matchedLicense->getId());

    $this->assertEquals(array($expectedHighlight), $highlights);

    $highlights = $this->highlightDao->getHighlightDiffs($this->uploadDao->getItemTreeBounds(11));

    $expectedHighlights = array();
    $expectedHighlights[] = new Highlight(18, 338, Highlight::MATCH, 0, 265);
    $expectedHighlights[] = new Highlight(339, 346, Highlight::CHANGED, 266, 272);
    $expectedHighlights[] = new Highlight(347, 35148, Highlight::MATCH, 273, 34505);
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
    $this->assertEquals($matchedLicense->getShortName(), "GPL-3.0-only");

    /** @var AgentRef */
    $agentRef = $licenseMatch->getAgentRef();
    $this->assertEquals($agentRef->getAgentName(), "monk");
  }
}
