<?php
/*
 SPDX-FileCopyrightText: © 2026 Contributors to FOSSology

 SPDX-License-Identifier: GPL-2.0-only
*/
namespace Fossology\Lib\Dao;

use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Test\TestPgDb;
use Mockery as M;

class JobDaoTest extends \PHPUnit\Framework\TestCase
{
  /** @var TestPgDb */
  private $testDb;
  /** @var DbManager */
  private $dbManager;
  /** @var JobDao */
  private $jobDao;

  private const ALICE = 2;
  private const BOB = 3;
  private const CAROL = 4;
  private const ALICE_GROUP = 2;
  private const BOB_GROUP = 3;

  protected function setUp() : void
  {
    $this->testDb = new TestPgDb();
    $this->dbManager = &$this->testDb->getDbManager();
    $this->testDb->createPlainTables(array('job', 'jobqueue', 'group_user_member'));

    // alice and carol share group 2, bob is alone in group 3
    foreach (array(array(1, self::ALICE_GROUP, self::ALICE),
                   array(2, self::ALICE_GROUP, self::CAROL),
                   array(3, self::BOB_GROUP, self::BOB)) as $m) {
      $this->dbManager->insertTableRow('group_user_member', array(
        'group_user_member_pk' => $m[0], 'group_fk' => $m[1],
        'user_fk' => $m[2], 'group_perm' => 0));
    }
    // job_pk and jq_pk deliberately overlap: job 2000 belongs to alice,
    // but jq 2000 is a step of bob's job 200
    $jobs = array(array(100, 10, self::ALICE), array(200, 20, self::BOB),
                  array(2000, 10, self::ALICE));
    foreach ($jobs as $j) {
      $this->dbManager->insertTableRow('job', array('job_pk' => $j[0],
        'job_queued' => date('c'), 'job_name' => 'job' . $j[0],
        'job_upload_fk' => $j[1], 'job_user_fk' => $j[2]));
    }
    foreach (array(array(1000, 100), array(2000, 200), array(2500, 2000)) as $q) {
      $this->dbManager->insertTableRow('jobqueue', array(
        'jq_pk' => $q[0], 'jq_job_fk' => $q[1], 'jq_type' => 'nomos'));
    }

    $logger = M::mock('Monolog\\Logger');
    $logger->shouldReceive('debug');
    $this->jobDao = new JobDao($this->dbManager, $logger);
  }

  protected function tearDown() : void
  {
    $this->testDb = null;
    $this->dbManager = null;
    M::close();
  }

  public function testOwnerMayActOnOwnJobQueue()
  {
    $this->assertTrue($this->jobDao->hasActionPermissionsOnJobQueue(1000, self::ALICE, self::ALICE_GROUP));
    $this->assertTrue($this->jobDao->hasActionPermissionsOnJobQueue(1000, self::ALICE, self::ALICE_GROUP, 10));
  }

  public function testUserMayNotActOnForeignJobQueue()
  {
    $this->assertFalse($this->jobDao->hasActionPermissionsOnJobQueue(2000, self::ALICE, self::ALICE_GROUP));
    $this->assertFalse($this->jobDao->hasActionPermissionsOnJobQueue(2000, self::ALICE, self::ALICE_GROUP, 10));
  }

  public function testJobQueueIdIsNotConfusedWithJobId()
  {
    // job_pk 2000 is alice's, but jq_pk 2000 is bob's: only bob may act on it
    $this->assertFalse($this->jobDao->hasActionPermissionsOnJobQueue(2000, self::ALICE, self::ALICE_GROUP));
    $this->assertTrue($this->jobDao->hasActionPermissionsOnJobQueue(2000, self::BOB, self::BOB_GROUP));
  }

  public function testJobQueueMustBelongToGivenUpload()
  {
    $this->assertFalse($this->jobDao->hasActionPermissionsOnJobQueue(1000, self::ALICE, self::ALICE_GROUP, 20));
  }

  public function testMemberOfSameActiveGroupMayAct()
  {
    $this->assertTrue($this->jobDao->hasActionPermissionsOnJobQueue(1000, self::CAROL, self::ALICE_GROUP, 10));
    $this->assertFalse($this->jobDao->hasActionPermissionsOnJobQueue(1000, self::CAROL, self::BOB_GROUP, 10));
  }

  public function testUnknownOrEmptyJobQueueIsDenied()
  {
    $this->assertFalse($this->jobDao->hasActionPermissionsOnJobQueue(99999, self::ALICE, self::ALICE_GROUP));
    $this->assertFalse($this->jobDao->hasActionPermissionsOnJobQueue(0, self::ALICE, self::ALICE_GROUP));
    $this->assertFalse($this->jobDao->hasActionPermissionsOnJobQueue(null, self::ALICE, self::ALICE_GROUP));
  }
}
