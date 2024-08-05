<?php
/*
 SPDX-FileCopyrightText: © 2024 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @file
 * @brief Functional test cases for compatibility agent using scheduler
 */
require_once "SchedulerTestRunnerCli.php";
require_once "SchedulerTestRunnerScheduler.php";

use Fossology\Compatibility\Test\SchedulerTestRunnerCli;
use Fossology\Compatibility\Test\SchedulerTestRunnerScheduler;
use Fossology\Lib\Dao\AgentDao;
use Fossology\Lib\Dao\CompatibilityDao;
use Fossology\Lib\Dao\LicenseDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Dao\UploadPermissionDao;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Exceptions\InvalidAgentStageException;
use Fossology\Lib\Test\TestInstaller;
use Fossology\Lib\Test\TestPgDb;
use Monolog\Logger;

/**
 * @class CompatibilityScheduledTest
 * @brief Functional test cases for compatibility agent using scheduler
 */
class CompatibilityScheduledTest extends \PHPUnit\Framework\TestCase
{

  /**
   * @var TestPgDb $testDb Object for test database
   */
  private $testDb;
  /**
   * @var DbManager $dbManager Database manager from test database
   */
  private $dbManager;
  /**
   * @var AgentDao $agentDao Agent Dao
   */
  private $agentDao;
  /**
   * @var LicenseDao $licenseDao Object of LicenseDao
   */
  private $licenseDao;
  /**
   * @var TestInstaller $testInstaller TestInstaller object
   */
  private $testInstaller;
  /**
   * @var UploadDao $uploadDao Object of UploadDao
   */
  private $uploadDao;
  /**
   * @var UploadPermissionDao $uploadPermDao Mockery of UploadPermissionDao
   */
  private $uploadPermDao;
  /**
   * @var CompatibilityDao $compatibilityDao Compatibility Dao
   */
  private $compatibilityDao;
  /**
   * @var SchedulerTestRunnerCli $cliRunner Agent CLI runner
   */
  private $cliRunner;
  /**
   * @var SchedulerTestRunnerScheduler $schedulerRunner Agent Scheduler runner
   */
  private $schedulerRunner;
  /**
   * @var Logger $logger Logger for test
   */
  private $logger;

  /**
   * @brief Setup the test cases and initialize the objects
   * @see PHPUnit_Framework_TestCase::setUp()
   */
  protected function setUp() : void
  {
    $this->testDb = new TestPgDb("compSched" . time());
    $this->dbManager = $this->testDb->getDbManager();

    $this->logger = new Logger("CompatibilitySchedulerTest");

    $this->agentDao = new AgentDao($this->dbManager, $this->logger);
    $this->licenseDao = new LicenseDao($this->dbManager);
    $this->uploadPermDao = \Mockery::mock(UploadPermissionDao::class);
    $this->uploadDao = new UploadDao($this->dbManager, $this->logger,
      $this->uploadPermDao);

    $this->cliRunner = new SchedulerTestRunnerCli($this->testDb);
    $this->schedulerRunner = new SchedulerTestRunnerScheduler($this->testDb);
  }

  /**
   * @brief Destruct the objects initialized during setUp()
   * @see PHPUnit_Framework_TestCase::tearDown()
   */
  protected function tearDown() : void
  {
    $this->testDb->fullDestruct();
    $this->testDb = null;
    $this->dbManager = null;
    $this->licenseDao = null;
  }

  /**
   * @brief Setup test repo mimicking install
   */
  private function setUpRepo()
  {
    $sysConf = $this->testDb->getFossSysConf();
    $this->testInstaller = new TestInstaller($sysConf);
    $this->testInstaller->init();
    $this->testInstaller->cpRepo();
  }

  /**
   * @brief Remove the test repo
   */
  private function rmRepo()
  {
    $this->testInstaller->rmRepo();
    $this->testInstaller->clear();
  }

  /**
   * @brief Setup tables required by copyright agent
   */
  private function setUpTables()
  {
    $this->testDb->createPlainTables(
      array(
        'agent',
        'uploadtree',
        'upload',
        'pfile',
        'users',
        'groups',
        'ars_master',
        'license_ref',
        'license_file',
        'comp_result',
        'license_rules',
        'upload_clearing_license'
      ));
    $this->testDb->createInheritedTables(
      array(
        'license_candidate',
        'nomos_ars',
        'monk_ars'
      ));
    $this->testDb->createSequences(
      array(
        'agent_agent_pk_seq',
        'upload_upload_pk_seq',
        'pfile_pfile_pk_seq',
        'users_user_pk_seq',
        'group_group_pk_seq',
        'nomos_ars_ars_pk_seq',
        'license_ref_rf_pk_seq',
        'license_file_fl_pk_seq',
        'license_rules_lr_pk_seq'
      ));
    $this->testDb->createConstraints(
      array(
        'agent_pkey',
        'upload_pkey_idx',
        'pfile_pkey',
        'user_pkey',
        'license_file_pkey',
        'license_rules_pkey'
      ));
    $this->testDb->alterTables(
      array(
        'agent',
        'pfile',
        'upload',
        'ars_master',
        'users',
        'groups',
        'license_ref',
        'license_file',
        'license_rules'
      ));
    $this->testDb->createInheritedTables(array(
      'uploadtree_a'
    ));

    $this->testDb->insertData(
      array(
        'agent',
        'upload',
        'pfile',
        'uploadtree_a',
        'users',
        'license_ref',
        'license_file',
        'license_rules',
        'nomos_ars',
        'monk_ars',
        'upload_clearing_license'
      ), false);

    $this->testDb->getDbManager()->getSingleRow(
      "UPDATE ars_master SET upload_fk = $1 WHERE ars_pk = $2",
      [3, 10], "ars_fix"
    );
    $this->testDb->getDbManager()->getSingleRow(
      "UPDATE ars_master SET upload_fk = $1 WHERE ars_pk = $2",
      [3, 12], "ars_fix"
    );
    $this->testDb->getDbManager()->getSingleRow(
      "SELECT setval('agent_agent_pk_seq', 300)", [], "agent_seq"
    );
  }

  /**
   * @brief Run the test
   * @test
   * -# Setup test tables
   * -# Setup test repo
   * -# Run compatibility on upload id 3
   * -# Remove test repo
   * -# Check some entries in comp_result table
   * @throws InvalidAgentStageException
   */
  public function testRun()
  {
    $this->setUpTables();
    $this->setUpRepo();

    $this->compatibilityDao = new CompatibilityDao($this->dbManager, $this->logger,
      $this->licenseDao, $this->agentDao);

    $uploadId = 3;
    list ($success, $output, $retCode) = $this->schedulerRunner->run($uploadId);
    $this->rmRepo();
    $this->assertTrue($success, 'running compatibility failed');
    $this->assertEquals($retCode, 0, "compatibility failed ($retCode): $output");

    $uploadTreeTableName = $this->uploadDao->getUploadtreeTableName($uploadId);

    // Compare LicenseB and LicenseD which are incompatible by id
    $this->assertFalse($this->compatibilityDao->getCompatibilityForFile(
      $this->uploadDao->getItemTreeBounds(40, $uploadTreeTableName),
      $this->licenseDao->getLicenseById(202)->getShortName())
    );
    $this->assertFalse($this->compatibilityDao->getCompatibilityForFile(
      $this->uploadDao->getItemTreeBounds(40, $uploadTreeTableName),
      $this->licenseDao->getLicenseById(498)->getShortName())
    );

    // Compare LicenseD and LicenseG which are compatible by type
    $this->assertTrue($this->compatibilityDao->getCompatibilityForFile(
      $this->uploadDao->getItemTreeBounds(38, $uploadTreeTableName),
      $this->licenseDao->getLicenseById(498)->getShortName())
    );

    // Compare LicenseD and LicenseF which are compatible by type
    $this->assertTrue($this->compatibilityDao->getCompatibilityForFile(
      $this->uploadDao->getItemTreeBounds(42, $uploadTreeTableName),
      $this->licenseDao->getLicenseById(544)->getShortName())
    );
  }
}
