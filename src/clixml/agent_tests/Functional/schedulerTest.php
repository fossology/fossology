<?php
/*
 SPDX-FileCopyrightText: © 2021 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\CliXml\Test;

use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Test\TestInstaller;
use Fossology\Lib\Test\TestPgDb;

include_once(__DIR__.'/../../../lib/php/Test/Agent/AgentTestMockHelper.php');
include_once(__DIR__.'/SchedulerTestRunnerCli.php');

class SchedulerTest extends \PHPUnit\Framework\TestCase
{
  /** @var int */
  private $userId = 2;
  /** @var int */
  private $groupId = 2;

  /** @var TestPgDb */
  private $testDb;
  /** @var DbManager */
  private $dbManager;
  /** @var TestInstaller */
  private $testInstaller;
  /** @var SchedulerTestRunnerCli */
  private $runnerCli;

  protected function setUp() : void
  {
    $this->testDb = new TestPgDb("clixmltest");
    $this->dbManager = $this->testDb->getDbManager();

    $this->runnerCli = new SchedulerTestRunnerCli($this->testDb);
    $this->assertCountBefore = \Hamcrest\MatcherAssert::getCount();

    $this->agentDir = dirname(dirname(__DIR__));
  }

  protected function tearDown() : void
  {
    $this->testDb->fullDestruct();
    $this->testDb = null;
    $this->dbManager = null;
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
    $this->testInstaller->rmRepo();
    $this->testInstaller->clear();
  }

  private function setUpTables()
  {
    $this->testDb->createPlainTables(array(),true);
    $this->testDb->createInheritedTables();
    $this->dbManager->queryOnce("CREATE TABLE copyright_ars () INHERITS (ars_master)");

    $this->testDb->createSequences(array('agent_agent_pk_seq','pfile_pfile_pk_seq','upload_upload_pk_seq','nomos_ars_ars_pk_seq','license_file_fl_pk_seq','license_ref_rf_pk_seq','license_ref_bulk_lrb_pk_seq','clearing_decision_clearing_decision_pk_seq','clearing_event_clearing_event_pk_seq'));
    $this->testDb->createConstraints(array('agent_pkey','pfile_pkey','upload_pkey_idx','FileLicense_pkey','clearing_event_pkey'));
    $this->testDb->alterTables(array('agent','pfile','upload','ars_master','license_ref_bulk','license_set_bulk','clearing_event','clearing_decision','license_file','highlight'));

    $this->testDb->insertData(array('mimetype_ars','pkgagent_ars','ununpack_ars','decider_ars'),true,__DIR__.'/fo_report.sql');
    $this->testDb->resetSequenceAsMaxOf('agent_agent_pk_seq', 'agent', 'agent_pk');
  }

  private function getHeartCount($output)
  {
    $matches = array();
    if (preg_match("/.*HEART: ([0-9]*).*/", $output, $matches)) {
      return intval($matches[1]);
    }
    return -1;
  }

  /** @group Functional */
  public function testReportForNormalUploadtreeTable()
  {
    $this->setUpTables();
    $this->setUpRepo();
    $this->runAndTestReport();
  }

  /** @group Functional */
  public function testReportForSpecialUploadtreeTable()
  {
    $this->setUpTables();
    $this->setUpRepo();

    $uploadId = 1;
    $this->dbManager->queryOnce("ALTER TABLE uploadtree_a RENAME TO uploadtree_$uploadId", __METHOD__.'.alterUploadtree');
    $this->dbManager->getSingleRow("UPDATE upload SET uploadtree_tablename=$1 WHERE upload_pk=$2",
            array("uploadtree_$uploadId",$uploadId),__METHOD__.'.alterUpload');

    $this->runAndTestReport($uploadId);
  }

  public function runAndTestReport($uploadId=1)
  {
    list($success,$output,$retCode) = $this->runnerCli->run($uploadId, $this->userId, $this->groupId, $jobId=7);

    assertThat('cannot run runner', $success, equalTo(true));
    assertThat( 'report failed: "'.$output.'"', $retCode, equalTo(0));
    assertThat($this->getHeartCount($output), greaterThan(0));

    $row = $this->dbManager->getSingleRow("SELECT upload_fk,job_fk,filepath FROM reportgen WHERE job_fk = $1", array($jobId), "reportFileName");
    assertThat($row, hasKeyValuePair('upload_fk', $uploadId));
    assertThat($row, hasKeyValuePair('job_fk', $jobId));
    $filepath = $row['filepath'];
    assertThat($filepath, endsWith('.xml'));
    assertThat(file_exists($filepath),equalTo(true));

    $licenseNameTest = 'Condor/condor-1.1';
    assertThat(file_get_contents($filepath), stringContainsInOrder($licenseNameTest));

    $licenseTextTest = 'Here is an alternative license text';
    assertThat(file_get_contents($filepath), stringContainsInOrder($licenseTextTest));

    $licenseNameNotTest = 'LGPL-2.1+';
    assertThat(file_get_contents($filepath), not(stringContainsInOrder($licenseNameNotTest)));

    $copyrightStatement = 'Copyright (c) 1999 University of Chicago and The University of Southern California';
    assertThat(file_get_contents($filepath), stringContainsInOrder($copyrightStatement));

    $this->addToAssertionCount(\Hamcrest\MatcherAssert::getCount()-$this->assertCountBefore);
    unlink($filepath);
    $this->rmRepo();
  }
}
