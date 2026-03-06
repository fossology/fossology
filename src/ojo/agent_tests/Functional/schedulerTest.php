<?php
/*
 SPDX-FileCopyrightText: © 2019 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @file
 * @brief Functional test cases for ojo agent using scheduler
 */

namespace Fossology\Ojo\Test;

require_once "SchedulerTestRunnerCli.php";
require_once "SchedulerTestRunnerScheduler.php";

use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Test\TestPgDb;
use Fossology\Lib\Test\TestInstaller;
use Monolog\Logger;
use Fossology\Lib\Dao\LicenseDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Dao\UploadPermissionDao;
use Fossology\Ojo\Test\SchedulerTestRunnerCli;
use Fossology\Ojo\Test\SchedulerTestRunnerScheduler;

/**
 * @class SchedulerTest
 * @brief Functional test cases for ojo agent using scheduler
 */
class SchedulerTest extends \PHPUnit\Framework\TestCase
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
   * @var SchedulerTestRunnerCli $cliRunner Agent CLI runner
   */
  private $cliRunner;
  /**
   * @var SchedulerTestRunnerScheduler $schedulerRunner Agent Scheduler runner
   */
  private $schedulerRunner;
  /**
   * @var string $regressionFile File containing regression JSON
   */
  private $regressionFile;

  /**
   * @brief Setup the test cases and initialize the objects
   * @see PHPUnit_Framework_TestCase::setUp()
   */
  protected function setUp() : void
  {
    $this->regressionFile = __DIR__ . DIRECTORY_SEPARATOR . "regexTest.json";

    $this->testDb = new TestPgDb("ojoSched" . time());
    $this->dbManager = $this->testDb->getDbManager();

    $logger = new Logger("OjoSchedulerTest");

    $this->licenseDao = new LicenseDao($this->dbManager);
    $this->uploadPermDao = \Mockery::mock(UploadPermissionDao::class);
    $this->uploadDao = new UploadDao($this->dbManager, $logger,
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
        'highlight'
      ));
    $this->testDb->createInheritedTables(
      array(
        'license_candidate'
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
        'license_file_fl_pk_seq'
      ));
    $this->testDb->createConstraints(
      array(
        'agent_pkey',
        'upload_pkey_idx',
        'pfile_pkey',
        'user_pkey',
        'license_file_pkey'
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
        'license_file'
      ));
    $this->testDb->createInheritedTables(array(
      'uploadtree_a'
    ));

    $this->testDb->insertData(
      array(
        'upload',
        'pfile',
        'uploadtree_a',
        'users',
        'license_ref'
      ), false);
  }

  /**
   * Check if the result array from ojo contains given license.
   * @param array $resultArray  Result array of a match
   * @param string $licenseName License to search
   * @return boolean True if it contains the license, false otherwise
   */
  private function resultArrayContainsLicense($resultArray, $licenseName)
  {
    foreach ($resultArray as $result) {
      if (strcmp($result["license"], $licenseName) === 0) {
        return true;
      }
    }
    return false;
  }

  /**
   * @brief Compare two matches from OJO (slow)
   *
   * The comparision algorithm is as follows:
   * -# Check if we are comparing results from same file.
   * -# Check if results are not null. The operand with null result will be
   *     smaller if other one is not null. Otherwise they will equal.
   * -# Check if size of results are equal.
   * -# At last, check the license of each result.
   * @param array $left  Left match
   * @param array $right Right match
   * @return number -1 if right is small, 1 is left is small or 0 if both are
   *         equal
   */
  public static function compareMatches($left, $right)
  {
    $leftFile = basename($left["file"]);
    $rightFile = basename($right["file"]);
    if (strcmp($leftFile, $rightFile) !== 0) {
      return strcmp($leftFile, $rightFile);
    }
    if ($left["results"] === null) {
      if ($right["results"] === null) {
        return 0;
      }
      return 1;
    }
    if ($right["results"] === null) {
      return -1;
    }
    if (count($left["results"]) !== count($right["results"])) {
      return count($left["results"]) - count($right["results"]);
    }
    foreach ($left["results"] as $key => $result) {
      if (strcmp($result["license"], $right["results"][$key]["license"]) !== 0) {
        return strcmp($result["license"], $right[$key]["license"]);
      }
    }
    return 0;
  }

  /**
   * Compare two matches based on file name only (fast).
   * @param array $left  Left match
   * @param array $right Right match
   * @return number strcmp of left filename and right filename
   */
  public static function compareMatchesFiles($left, $right)
  {
    return strcmp($left["file"], $right["file"]);
  }

  /**
   * @brief Run the test
   * @test
   * -# Setup test tables
   * -# Setup test repo
   * -# Run ojo on upload id 4
   * -# Remove test repo
   * -# Check entries in license_file table
   */
  public function testRun()
  {
    $this->setUpTables();
    $this->setUpRepo();
    $uploadId = 44;
    list ($success, $output, $retCode) = $this->schedulerRunner->run($uploadId);
    $this->rmRepo();
    $this->assertTrue($success, 'running ojo failed');
    $this->assertEquals($retCode, 0, "ojo failed ($retCode): $output");

    $uploadTreeTableName = $this->uploadDao->getUploadtreeTableName($uploadId);
    $uploadParent = $this->uploadDao->getItemTreeBounds(460, $uploadTreeTableName);
    $licenseMatches = $this->licenseDao->getLicensesPerFileNameForAgentId(
      $uploadParent, [1]);

    $this->assertGreaterThan(8, count($licenseMatches), $output);
    $this->assertContains("Classpath-exception-2.0",
      $licenseMatches["spdx.tar/spdx/GPL-2.0_WITH_Classpath-exception-2.0"]["scanResults"]);
    $this->assertContains("GPL-2.0-only",
      $licenseMatches["spdx.tar/spdx/GPL-2.0_WITH_Classpath-exception-2.0"]["scanResults"]);

    $this->assertContains("GPL-2.0-only",
      $licenseMatches["spdx.tar/spdx/GPL-2.0_OR_MIT"]["scanResults"]);
    $this->assertContains("MIT",
      $licenseMatches["spdx.tar/spdx/GPL-2.0_OR_MIT"]["scanResults"]);

    $this->assertContains("GPL-2.0-or-later",
      $licenseMatches["spdx.tar/spdx/GPL-2.0-or-later"]["scanResults"]);

    $this->assertContains("GPL-2.0-only",
      $licenseMatches["spdx.tar/spdx/GPL-2.0-only"]["scanResults"]);

    $this->assertContains("LGPL-2.1-or-later",
      $licenseMatches["spdx.tar/spdx/GPL-2.0_AND_LGPL-2.1-or-later_OR_MIT"]["scanResults"]);
    $this->assertContains("GPL-2.0-only",
      $licenseMatches["spdx.tar/spdx/GPL-2.0_AND_LGPL-2.1-or-later_OR_MIT"]["scanResults"]);
    $this->assertContains("MIT",
      $licenseMatches["spdx.tar/spdx/GPL-2.0_AND_LGPL-2.1-or-later_OR_MIT"]["scanResults"]);

    $this->assertContains("GPL-2.0-or-later",
      $licenseMatches["spdx.tar/spdx/GPL-2.0+"]["scanResults"]);

    $this->assertContains("GPL-2.0-only",
      $licenseMatches["spdx.tar/spdx/GPL-2.0"]["scanResults"]);
  }

  /**
   * @brief Run the test for CLI
   * @test
   * -# Run ojo on a test file
   * -# Check if ojo returns a JSON
   * -# Check if the returned JSON contains correct data
   */
  public function testCli()
  {
    $testFile = dirname(__DIR__, 3)."/nomos/agent_tests/testdata/NomosTestfiles/SPDX/MPL-2.0_AND_BSD-2-Clause_AND_MIT_OR_Apache-2.0.txt";

    $args = "--json $testFile";
    list ($success, $output, $retCode) = $this->cliRunner->run($args);
    $this->assertTrue($success, 'running ojo failed');
    $this->assertEquals($retCode, 0, "ojo failed ($retCode): $output");

    $this->assertJson($output);
    $result = json_decode($output, true);
    $resultArray = $result[0]["results"];

    $this->assertEquals($testFile, $result[0]["file"]);
    $this->assertTrue($this->resultArrayContainsLicense($resultArray,
      "MPL-2.0-no-copyleft-exception"));
    $this->assertTrue($this->resultArrayContainsLicense($resultArray,
      "BSD-2-Clause"));
    $this->assertTrue($this->resultArrayContainsLicense($resultArray,
      "MIT"));
    $this->assertTrue($this->resultArrayContainsLicense($resultArray,
      "Apache-2.0"));
    $this->assertTrue($this->resultArrayContainsLicense($resultArray,
      "Dual-license"));
  }

  /**
   * @brief Run a regression test for OJO
   * @test
   * -# Run ojo on a test directory
   * -# Check if ojo returns a JSON
   * -# Check if the returned JSON matches last run
   */
  public function regressionTest()
  {
    $testDir = dirname(__DIR__, 3)."/nomos/agent_tests/testdata/NomosTestfiles/SPDX";

    $args = "--json --directory $testDir";
    list ($success, $output, $retCode) = $this->cliRunner->run($args);
    $this->assertTrue($success, 'running ojo failed');
    $this->assertEquals($retCode, 0, "ojo failed ($retCode): $output");
    $this->assertJson($output);

    // Load data from last run
    $jsonFromFile = json_decode(file_get_contents($this->regressionFile), true);
    // Load result from agent
    $jsonFromOutput = json_decode($output, true);

    // Sort the data to reduce differences
    usort($jsonFromFile, array(self::class, 'compareMatchesFiles'));
    usort($jsonFromOutput, array(self::class, 'compareMatchesFiles'));

    // Find the difference
    $jsonDiff = array_udiff($jsonFromFile, $jsonFromOutput,
      array(self::class, 'compareMatches'));

    $outputDiff = "JSON does not match regression test file.\n";
    $outputDiff .= "Following are the results not in regression test file.\n";
    $outputDiff .= print_r($jsonDiff, true);

    $this->assertEquals(0, count($jsonDiff), $outputDiff);
  }
}
