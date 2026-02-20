<?php
/*
 SPDX-FileCopyrightText: © 2026 Fossology contributors

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Dao;

use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Test\TestPgDb;
use Monolog\Logger;

/**
 * Tests for JobDao functionality
 */
class JobDaoTest extends \PHPUnit\Framework\TestCase
{
  /** @var TestPgDb */
  private $testDb;

  /** @var DbManager */
  private $dbManager;

  /** @var JobDao */
  private $jobDao;

  protected function setUp() : void
  {
    $this->testDb = new TestPgDb();
    $this->dbManager = &$this->testDb->getDbManager();

    // Setup required tables
    $this->testDb->createPlainTables(array(
      'job',
      'jobqueue',
      'upload',
      'users',
      'group_user_member'
    ));

    $logger = new Logger("JobDaoTest");
    $this->jobDao = new JobDao($this->dbManager, $logger);

    $this->assertCountBefore = \Hamcrest\MatcherAssert::getCount();
  }

  protected function tearDown() : void
  {
    $this->addToAssertionCount(\Hamcrest\MatcherAssert::getCount()-$this->assertCountBefore);
    $this->testDb = null;
    $this->dbManager = null;
  }

  private function insertUser($uid)
  {
    $this->dbManager->insertTableRow('users', array(
      'user_pk' => $uid,
      'user_name' => "user_$uid",
      'user_desc' => 'Test user',
      'user_seed' => 'seed',
      'user_pass' => 'pass',
      'user_email' => 'test@localhost',
      'email_notify' => 'n',
      'root_folder_fk' => 1
    ));
  }

  private function insertUpload($uploadId, $userId)
  {
    $this->dbManager->insertTableRow('upload', array(
      'upload_pk' => $uploadId,
      'upload_desc' => "upload_$uploadId",
      'upload_filename' => 'test.tar',
      'user_fk' => $userId,
      'upload_mode' => 104
    ));
  }

  private function insertJob($jobId, $uploadId, $userId)
  {
    $this->dbManager->insertTableRow('job', array(
      'job_pk' => $jobId,
      'job_upload_fk' => $uploadId,
      'job_user_fk' => $userId,
      'job_queued' => date('Y-m-d H:i:s')
    ));
  }

  private function insertJobQueue($queueId, $jobId, $status = 0)
  {
    $this->dbManager->insertTableRow('jobqueue', array(
      'jq_pk' => $queueId,
      'jq_job_fk' => $jobId,
      'jq_type' => 'nomos',
      'jq_args' => '1',
      'jq_end_bits' => $status,
      'jq_starttime' => null,
      'jq_endtime' => null
    ));
  }

  /**
   * Test getAllJobStatus - this should return job statuses for an upload
   */
  public function testGetAllJobStatus()
  {
    $uid = 10;
    $gid = 5;
    $uploadId = 100;
    $jobId = 500;

    $this->insertUser($uid);
    $this->insertUpload($uploadId, $uid);
    $this->insertJob($jobId, $uploadId, $uid);
    
    // Add a couple of queue entries
    $this->insertJobQueue(1001, $jobId, 0);
    $this->insertJobQueue(1002, $jobId, 1);

    $statuses = $this->jobDao->getAllJobStatus($uploadId, $uid, $gid);

    assertThat(count($statuses), is(2));
    assertThat($statuses[1001], is('0'));
    assertThat($statuses[1002], is('1'));
    $this->addToAssertionCount(3);
  }

  /**
   * Should return empty array when no jobs exist
   */
  public function testGetAllJobStatusWhenEmpty()
  {
    $result = $this->jobDao->getAllJobStatus(999, 1, 1);
    
    assertThat(count($result), is(0));
    $this->addToAssertionCount(1);
  }

  /**
   * Test getChlidJobStatus method for child jobs
   */
  public function testGetChildJobStatus()
  {
    $uid = 11;
    $uploadId = 101;
    $jobId = 501;

    $this->insertUser($uid);
    $this->insertUpload($uploadId, $uid);
    $this->insertJob($jobId, $uploadId, $uid);
    
    // Multiple queue entries for the job
    $this->insertJobQueue(2001, $jobId, 0);
    $this->insertJobQueue(2002, $jobId, 2);
    $this->insertJobQueue(2003, $jobId, 1);

    $result = $this->jobDao->getChlidJobStatus($jobId);

    assertThat(count($result), is(3));
    assertThat($result[2002], is('2'));
    $this->addToAssertionCount(2);
  }

  /**
   * Test permission check for job owner
   */
  public function testHasActionPermissionsForOwner()
  {
    $uid = 12;
    $gid = 5;
    $uploadId = 102;
    $jobId = 502;

    $this->insertUser($uid);
    $this->insertUpload($uploadId, $uid);
    $this->insertJob($jobId, $uploadId, $uid);

    $result = $this->jobDao->hasActionPermissionsOnJob($jobId, $uid, $gid);

    // Should have permissions since user owns the job
    assertThat(count($result), greaterThan(0));
    $this->addToAssertionCount(1);
  }

  /**
   * Test that non-owner doesn't have permissions
   */
  public function testHasActionPermissionsForNonOwner()
  {
    $owner = 13;
    $otherUser = 14;
    $gid = 5;
    $uploadId = 103;
    $jobId = 503;

    $this->insertUser($owner);
    $this->insertUser($otherUser);
    $this->insertUpload($uploadId, $owner);
    $this->insertJob($jobId, $uploadId, $owner);

    $result = $this->jobDao->hasActionPermissionsOnJob($jobId, $otherUser, $gid);

    // No permissions for other user
    assertThat(count($result), is(0));
    $this->addToAssertionCount(1);
  }
}
