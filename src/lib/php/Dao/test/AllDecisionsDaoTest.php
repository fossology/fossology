<?php
/*
 SPDX-FileCopyrightText: © 2024 Fossology contributors

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Dao;

use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Test\TestPgDb;
use Mockery as M;
use Monolog\Logger;

class AllDecisionsDaoTest extends \PHPUnit\Framework\TestCase
{
  /** @var TestPgDb */
  private $testDb;
  /** @var DbManager */
  private $dbManager;
  /** @var Logger */
  private $logger;
  /** @var AllDecisionsDao */
  private $allDecisionsDao;
  /** @var integer */
  private $assertCountBefore;

  protected function setUp() : void
  {
    $this->testDb = new TestPgDb();
    $this->dbManager = $this->testDb->getDbManager();
    $this->logger = new Logger("test");

    global $container;
    $container = M::mock('ContainerBuilder');
    $agentDao = M::mock('Fossology\Lib\Dao\AgentDao');
    $uploadDao = M::mock('Fossology\Lib\Dao\UploadDao');
    $container->shouldReceive('get')->with('dao.agent')->andReturn($agentDao);
    $container->shouldReceive('get')->with('dao.upload')->andReturn($uploadDao);

    $this->allDecisionsDao = new AllDecisionsDao($this->dbManager, $this->logger);

    $this->testDb->createPlainTables(array('job', 'jobqueue', 'upload'));

    $this->assertCountBefore = \Hamcrest\MatcherAssert::getCount();
  }

  protected function tearDown() : void
  {
    $this->addToAssertionCount(\Hamcrest\MatcherAssert::getCount()-$this->assertCountBefore);
    $this->testDb->fullDestruct();
    $this->testDb = null;
    $this->dbManager = null;
    M::close();
  }

  public function testGetAllJobTypeForUpload()
  {
    $uploadId = 1;

    $this->dbManager->insertTableRow('job', array('job_pk' => 1, 'job_upload_fk' => $uploadId));
    $this->dbManager->insertTableRow('jobqueue', array('jq_pk' => 1, 'jq_job_fk' => 1, 'jq_type' => 'nomos', 'jq_end_bits' => 1));
    $this->dbManager->insertTableRow('jobqueue', array('jq_pk' => 2, 'jq_job_fk' => 1, 'jq_type' => 'monk', 'jq_end_bits' => 1));
    $this->dbManager->insertTableRow('jobqueue', array('jq_pk' => 3, 'jq_job_fk' => 1, 'jq_type' => 'other', 'jq_end_bits' => 1));

    $jobTypes = $this->allDecisionsDao->getAllJobTypeForUpload($uploadId);

    sort($jobTypes);
    assertThat($jobTypes, is(array('monk', 'nomos')));
  }
}
