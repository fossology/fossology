<?php
/*
 SPDX-FileCopyrightText: © 2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @dir
 * @brief Functional tests for unified report
 * @file
 * @brief Functional tests for unified report
 */
/**
 * @namespace Fossology::Report::Test
 * @brief Namespace for report related tests
 */
namespace Fossology\Report\Test;

use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Test\TestInstaller;
use Fossology\Lib\Test\TestPgDb;

include_once(__DIR__.'/../../../lib/php/Test/Agent/AgentTestMockHelper.php');
include_once(__DIR__.'/SchedulerTestRunnerCli.php');

/**
 * @class SchedulerTest
 * @brief Test for unified report
 */
class SchedulerTest extends \PHPUnit\Framework\TestCase
{
  /** @var int $userId
   * User id to be used
   */
  private $userId = 2;
  /** @var int $groupId
   * Group id to be used
   */
  private $groupId = 2;

  /** @var TestPgDb $testDb
   * Test db
   */
  private $testDb;
  /** @var DbManager $dbManager
   * Db Manager object
   */
  private $dbManager;
  /** @var TestInstaller $testInstaller
   * Test installer object
   */
  private $testInstaller;
  /** @var SchedulerTestRunnerCli $runnerCli
   * The CLI interface for scheduler
   */
  private $runnerCli;

  /**
   * @brief Setup test env
   */
  public function setUp() : void
  {
    $this->testDb = new TestPgDb("report".time());
    $this->dbManager = $this->testDb->getDbManager();

    $this->runnerCli = new SchedulerTestRunnerCli($this->testDb);
    $this->assertCountBefore = \Hamcrest\MatcherAssert::getCount();
  }

  /**
   * @brief Tear down test env
   */
  public function tearDown() : void
  {
    $this->addToAssertionCount(\Hamcrest\MatcherAssert::getCount()-$this->assertCountBefore);
    // $this->testDb->fullDestruct();
    $this->testDb = null;
    $this->dbManager = null;
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
    $this->testDb->createSequences(array(),true);
    $this->testDb->createPlainTables(array(),true);
    $this->testDb->createInheritedTables();
    $this->testDb->createInheritedArsTables(array('copyright','monk','nomos'));
    $this->testDb->createConstraints(array('agent_pkey','pfile_pkey','upload_pkey_idx',
      'FileLicense_pkey','clearing_event_pkey'),false);
    $this->testDb->alterTables(array('agent','pfile','upload','ars_master',
      'license_ref_bulk','clearing_event','clearing_decision','license_file','highlight'),false);

    $this->testDb->insertData(array('mimetype_ars','pkgagent_ars','ununpack_ars','decider_ars'),true,__DIR__.'/fo_report.sql');
    // $this->testDb->insertData_license_ref();
    $this->testDb->resetSequenceAsMaxOf('agent_agent_pk_seq', 'agent', 'agent_pk');
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
   * @brief Test report agent
   * @test
   * -# Generate report an upload
   * -# Check if the DB is updated with new value
   * -# Check if report file is created
   * -# Check if report file size is close to test report
   * @todo Generate the test file and fix the test case
   */
  public function testReport()
  {
    $this->setUpTables();
    $this->setUpRepo();

    list($success,$output,$retCode) = $this->runnerCli->run($uploadId=1, $this->userId, $this->groupId, $jobId=7);

    assertThat('cannot run runner', $success, equalTo(true));
    assertThat( 'report failed: "'.$output.'"', $retCode, equalTo(0));
    assertThat($this->getHeartCount($output), greaterThan(0));

    $row = $this->dbManager->getSingleRow("SELECT upload_fk,job_fk,filepath FROM reportgen WHERE job_fk = $1", array($jobId), "reportFileName");
    assertThat($row, hasKeyValuePair('upload_fk', $uploadId));
    assertThat($row, hasKeyValuePair('job_fk', $jobId));
    $filepath = $row['filepath'];
    // $comparisionFile = __DIR__.'/ReportTestfiles.tar_clearing_report_Mon_May_04_05_2015_11_53_18.docx';
    // assertThat(is_file($comparisionFile),equalTo(true));
    // assertThat(filesize($filepath), closeTo(filesize($comparisionFile),5));

    $this->rmRepo();
  }
}
