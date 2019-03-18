<?php
/*
Copyright (C) 2015, Siemens AG
Copyright (C) 2017 TNG Technology Consulting GmbH

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
/**
 * @dir
 * @brief Functional test cases for SPDX2 agent
 * @file
 * @brief Functional test cases for SPDX2 agent and scheduler interaction
 */
/**
 * @namespace Fossology::SpdxTwo::Test
 * @brief Namespace to hold test cases for SPDX2 agent
 */
namespace Fossology\SpdxTwo\Test;

use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Test\TestInstaller;
use Fossology\Lib\Test\TestPgDb;

include_once(__DIR__.'/../../../lib/php/Test/Agent/AgentTestMockHelper.php');
include_once(__DIR__.'/SchedulerTestRunnerCli.php');

/**
 * @class SchedulerTest
 * @brief Tests for SPDX2 agent and scheduler interaction
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
   * DBManager to use
   */
  private $dbManager;
  /** @var TestInstaller $testInstaller
   * TestInstaller object
   */
  private $testInstaller;
  /** @var SchedulerTestRunnerCli $runnerCli
   * SchedulerTestRunnerCli object
   */
  private $runnerCli;

  /**
   * @brief Setup test db
   */
  protected function setUp()
  {
    $this->testDb = new TestPgDb("spdx2test");
    $this->dbManager = $this->testDb->getDbManager();

    $this->runnerCli = new SchedulerTestRunnerCli($this->testDb);
    $this->assertCountBefore = \Hamcrest\MatcherAssert::getCount();

    $this->agentDir = dirname(dirname(__DIR__));
  }

  /**
   * @brief Teardown test db
   */
  protected function tearDown()
  {
    $this->testDb->fullDestruct();
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
    $this->testInstaller->install($this->agentDir);
  }

  /**
   * @brief Teardown test repo
   */
  private function rmRepo()
  {
    $this->testInstaller->uninstall($this->agentDir);
    $this->testInstaller->rmRepo();
    $this->testInstaller->clear();
  }

  /**
   * @brief Setup tables required for test
   */
  private function setUpTables()
  {
    $this->testDb->createPlainTables(array(),true);
    $this->testDb->createInheritedTables();
    $this->dbManager->queryOnce("CREATE TABLE copyright_ars () INHERITS (ars_master)");

    $this->testDb->createSequences(array('agent_agent_pk_seq','pfile_pfile_pk_seq','upload_upload_pk_seq',
      'nomos_ars_ars_pk_seq','license_file_fl_pk_seq','license_ref_rf_pk_seq',
      'license_ref_bulk_lrb_pk_seq','clearing_decision_clearing_decision_pk_seq',
      'clearing_event_clearing_event_pk_seq'));
    $this->testDb->createConstraints(array('agent_pkey','pfile_pkey','upload_pkey_idx',
      'FileLicense_pkey','clearing_event_pkey'));
    $this->testDb->alterTables(array('agent','pfile','upload','ars_master','license_ref_bulk','license_set_bulk',
      'clearing_event','clearing_decision','license_file','highlight'));

    $this->testDb->insertData(array('mimetype_ars','pkgagent_ars','ununpack_ars','decider_ars'),true,__DIR__.'/fo_report.sql');
    $this->testDb->resetSequenceAsMaxOf('agent_agent_pk_seq', 'agent', 'agent_pk');
  }

  /**
   * @brief Get the heart count from agent
   */
  private function getHeartCount($output)
  {
    $matches = array();
    if (preg_match("/.*HEART: ([0-9]*).*/", $output, $matches))
    {
      return intval($matches[1]);
    }
    return -1;
  }

  /**
   * @brief Test SPDX2 agent for RDF
   *
   * Calls runAndTestReportRDF()
   */
  public function testSpdxForNormalUploadtreeTable()
  {
    $this->setUpTables();
    $this->setUpRepo();
    $this->runAndTestReportRDF();
  }

  /**
   * @brief Test SPDX2 agent for RDF
   *
   * Calls runAndTestReportRDF() with a special uploadtree table
   */
  public function testSpdxForSpecialUploadtreeTable()
  {
    $this->setUpTables();
    $this->setUpRepo();

    $uploadId = 1;
    $this->dbManager->queryOnce("ALTER TABLE uploadtree_a RENAME TO uploadtree_$uploadId", __METHOD__.'.alterUploadtree');
    $this->dbManager->getSingleRow("UPDATE upload SET uploadtree_tablename=$1 WHERE upload_pk=$2",
            array("uploadtree_$uploadId",$uploadId),__METHOD__.'.alterUpload');

    $this->runAndTestReportRDF($uploadId);
  }

  /**
   * @brief Run jobs from queue
   * @param int $uploadId
   * @param int $jobId
   */
  public function runJobFromJobque($uploadId, $jobId){
    list($success,$output,$retCode) = $this->runnerCli->run($uploadId, $this->userId, $this->groupId, $jobId);

    assertThat('cannot run runner', $success, equalTo(true));
    assertThat('report failed: "'.$output.'"', $retCode, equalTo(0));
    assertThat($this->getHeartCount($output), greaterThan(0));
  }

  /**
   * @brief Get the file path for report from DB
   * @param int $uploadId
   * @param int $jobId
   * @return string
   */
  public function getReportFilepathFromJob($uploadId, $jobId){
    $row = $this->dbManager->getSingleRow("SELECT upload_fk,job_fk,filepath FROM reportgen WHERE job_fk = $1", array($jobId),
      "reportFileName");
    assertThat($row, hasKeyValuePair('upload_fk', $uploadId));
    assertThat($row, hasKeyValuePair('job_fk', $jobId));
    $filepath = $row['filepath'];
    assertThat($filepath, endsWith('.rdf'));
    assertThat(file_exists($filepath),equalTo(true));

    return $filepath;
  }

  /**
   * @brief Create RDF report and check it
   * @param int $uploadId Default 1
   * \test
   * -# Run job from queue runJobFromJobque()
   * -# Get the report path
   * -# Check if report contains a known string
   * -# Check if report does not contain emails
   * -# Verify RDF format
   */
  public function runAndTestReportRDF($uploadId=1)
  {
    $jobId=7;

    $this->runJobFromJobque($uploadId, $jobId);
    $filepath = $this->getReportFilepathFromJob($uploadId, $jobId);

    $copyrightStatement = 'Copyright (C) 1991-2, RSA Data Security, Inc. Created 1991. All rights reserved';
    assertThat(file_get_contents($filepath), stringContainsInOrder($copyrightStatement));

    $email = 'condor-admin@cs.wisc.edu';
    assertThat(file_get_contents($filepath), not(stringContainsInOrder($email)));

    $this->addToAssertionCount(\Hamcrest\MatcherAssert::getCount()-$this->assertCountBefore);

    $this->verifyRdf($filepath);
    $this->rmRepo();
  }

  /**
   * @brief Use SPDX toolkit to verify RDF file format
   * @param string $filepath File to verify
   */
  protected function verifyRdf($filepath)
  {
    $toolJarFile = $this->pullSpdxTools();

    $verification = exec("java -jar $toolJarFile Verify $filepath");
    assertThat($verification,equalTo('This SPDX Document is valid.'));
    unlink($filepath);
  }

  /**
   * @brief Pull SPDX toolkit from github if not found
   *
   * -# Verify if Java is installed
   * -# Pull version 2.1.0
   * -# Store only spdx-tools-2.1.0-jar-with-dependencies.jar
   * @return string Jar file path
   */
  protected function pullSpdxTools()
  {
    $this-> verifyJavaIsInstalled();

    $version='2.1.0';
    $tag='v'.$version;

    $jarFileBasename = 'spdx-tools-'.$version.'-jar-with-dependencies.jar';
    $jarFile = __DIR__.'/'.$jarFileBasename;
    if(!file_exists($jarFile))
    {
      $zipFileBasename='SPDXTools-'.$tag.'.zip';
      $zipFile=__DIR__.'/'.$zipFileBasename;
      if(!file_exists($zipFile))
      {
        file_put_contents($zipFile, fopen('https://github.com/spdx/tools/releases/download/'.$tag.'/'.$zipFileBasename, 'r'));

      }
      $this->assertFileExists($zipFile, 'could not download SPDXTools');

      system('unzip -n -d '.__DIR__.' '.$zipFile);
      rename (__DIR__.'/SPDXTools-'.$tag.'/'.$jarFileBasename, $jarFile);
    }
    $this->assertFileExists($jarFile, 'could not extract SPDXTools');
    return $jarFile;
  }

  /**
   * @brief Verify if java is intalled on the system
   */
  protected function verifyJavaIsInstalled(){
    $lines = '';
    $returnVar = 0;
    exec('which java', $lines, $returnVar);
    $this->assertEquals(0,$returnVar,'java required for this test');
  }
}
