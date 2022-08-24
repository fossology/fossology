<?php
/*
 SPDX-FileCopyrightText: © 2014-2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @file schedulerTest.php
 * @brief Unit test cases for copyright agent using scheduler
 */

use Fossology\Lib\Dao\CopyrightDao;
use Fossology\Lib\Dao\LicenseDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Dao\UploadPermissionDao;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Test\TestPgDb;
use Monolog\Logger;

if (!function_exists('Traceback_uri'))
{
  function Traceback_uri(){
    return 'Traceback_uri_if_desired';
  }
}

/**
 * @class CopyrightScheduledTest
 * @brief Unit test cases for copyright agent using scheduler
 */
class CopyrightScheduledTest extends \PHPUnit\Framework\TestCase
{
  /** @var TestPgDb $testDb
   * Object for test database
   */
  private $testDb;
  /** @var DbManager $dbManager
   * Database manager from test database
   */
  private $dbManager;
  /** @var LicenseDao $licenseDao
   * Object of LicenseDao
   */
  private $licenseDao;
  /** @var UploadDao $uploadDao
   * Object of UploadDao
   */
  private $uploadDao;
  /** @var UploadPermissionDao $uploadPermDao
   * Mockery of UploadPermissionDao
   */
  private $uploadPermDao;
  /** @var CopyrightDao $copyrightDao
   * Object of CopyrightDao
   */
  private $copyrightDao;

  /**
   * @brief Setup the test cases and initialize the objects
   * @see PHPUnit_Framework_TestCase::setUp()
   */
  protected function setUp() : void
  {
    $this->testDb = new TestPgDb("copyrightSched".time());
    $this->dbManager = $this->testDb->getDbManager();

    $logger = new Logger("CopyrightSchedulerTest");

    $this->licenseDao = new LicenseDao($this->dbManager);
    $this->uploadPermDao = \Mockery::mock(UploadPermissionDao::class);
    $this->uploadDao = new UploadDao($this->dbManager, $logger, $this->uploadPermDao);
    $this->copyrightDao = new CopyrightDao($this->dbManager, $this->uploadDao);
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
   * @brief Run copyright on a given upload id
   *
   * Setup copyright agent and test environment then run
   * copyright agent and pass the given upload id to scan.
   *
   * Reports error if agent return code is not \b 0.
   * @param int $uploadId Upload id to be scanned
   * @return string Copyright findings returned by the agent
   */
  private function runCopyright($uploadId)
  {
    $sysConf = $this->testDb->getFossSysConf();

    $agentName = "copyright";

    $agentDir = dirname(__DIR__, 2);
    $execDir = "$agentDir/agent";
    system("install -D $agentDir/VERSION-copyright $sysConf/mods-enabled/$agentName/VERSION");
    system("install -D $agentDir/agent/copyright.conf  $sysConf/mods-enabled/$agentName/agent/copyright.conf");
    $pCmd = "echo $uploadId | $execDir/$agentName -c $sysConf --scheduler_start";
    $pipeFd = popen($pCmd, "r");
    $this->assertTrue($pipeFd !== false, 'running copyright failed');

    $output = "";
    while (($buffer = fgets($pipeFd, 4096)) !== false) {
      $output .= $buffer;
    }
    $retCode = pclose($pipeFd);

    unlink("$sysConf/mods-enabled/$agentName/VERSION");
    unlink("$sysConf/mods-enabled/$agentName/agent/copyright.conf");
    rmdir("$sysConf/mods-enabled/$agentName/agent/");
    rmdir("$sysConf/mods-enabled/$agentName");
    rmdir("$sysConf/mods-enabled");
    unlink($sysConf."/fossology.conf");

    $this->assertEquals($retCode, 0, "copyright failed ($retCode): $output [$pCmd]");
    return $output;
  }

  /**
   * @brief Setup test repo mimicking install
   */
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

  /**
   * @brief Remove the test repo
   */
  private function rmRepo()
  {
    $sysConf = $this->testDb->getFossSysConf();
    system("rm $sysConf/repo -rf");
  }

  /**
   * @brief Setup tables required by copyright agent
   */
  private function setUpTables()
  {
    $this->testDb->createPlainTables(array('agent','uploadtree','upload','pfile','users','bucketpool','mimetype','ars_master','author','copyright_event'));
    $this->testDb->createSequences(array('agent_agent_pk_seq','upload_upload_pk_seq','pfile_pfile_pk_seq','users_user_pk_seq','nomos_ars_ars_pk_seq'));
    $this->testDb->createConstraints(array('agent_pkey','upload_pkey_idx','pfile_pkey','user_pkey'));
    $this->testDb->alterTables(array('agent','pfile','upload','ars_master','users'));
    $this->testDb->createInheritedTables(array('uploadtree_a'));

    $this->testDb->insertData(array('upload','pfile','uploadtree_a','bucketpool','mimetype','users'), false);
  }

  /**
   * @brief Run the test
   * @test
   * -# Setup test tables
   * -# Setup test repo
   * -# Run copyright on upload id 1
   * -# Remove test repo
   * -# Check entries in copyright table
   */
  public function testRun()
  {
    $this->setUpTables();
    $this->setUpRepo();
    $output = $this->runCopyright($uploadId=1);
    $this->rmRepo();

    $uploadTreeTableName = $this->uploadDao->getUploadtreeTableName($uploadId);
    $matches = $this->copyrightDao->getAllEntries("copyright", $uploadId, $uploadTreeTableName);
    $this->assertGreaterThan($expected=5, count($matches), $output);
  }

}
