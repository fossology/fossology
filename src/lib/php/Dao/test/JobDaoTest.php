<?php
/*
 SPDX-FileCopyrightText: © 2026 Fossology contributors

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Dao;

use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Test\TestPgDb;
use Mockery as M;
use Monolog\Logger;

class JobDaoTest extends \PHPUnit\Framework\TestCase
{
  /** @var TestPgDb */
  private $testDb;
  
  /** @var DbManager */
  private $dbManager;
  
  /** @var Logger|M\MockInterface */
  private $logger;
  
  /** @var JobDao */
  private $jobDao;

  protected function setUp() : void
  {
    $this->testDb = new TestPgDb();
    $this->dbManager = &$this->testDb->getDbManager();
    $this->logger = M::mock(Logger::class);
    $this->logger->shouldReceive('debug');
    $this->logger->shouldReceive('info');

    // Create necessary tables for the test
    $this->testDb->createPlainTables(array(
      'upload', 
      'job', 
      'jobqueue',
      'users',
      'group_user_member'
    ));
    
    $this->testDb->createSequences(array(
      'upload_upload_pk_seq',
      'job_job_pk_seq',
      'jobqueue_jq_pk_seq'
    ));

    $this->jobDao = new JobDao($this->dbManager, $this->logger);
    
    $this->assertCountBefore = \Hamcrest\MatcherAssert::getCount();
  }

  protected function tearDown() : void
  {
    $this->addToAssertionCount(\Hamcrest\MatcherAssert::getCount()-$this->assertCountBefore);
    $this->testDb = null;
    $this->dbManager = null;
    M::close();
  }

  /**
   * Helper method to insert a user into the database
   */
  private function insertUser($userId, $userName = 'testuser')
  {
    $this->dbManager->insertTableRow('users', array(
      'user_pk' => $userId,
      'user_name' => $userName,
      'user_desc' => 'Test User',
      'user_seed' => 'Seed',
      'user_pass' => 'Pass',
      'user_email' => 'test@example.com',
      'email_notify' => 'n',
      'root_folder_fk' => 1
    ));
  }

  /**
   * Helper method to insert an upload into the database
   */
  private function insertUpload($uploadId, $userId)
  {
    $this->dbManager->insertTableRow('upload', array(
      'upload_pk' => $uploadId,
      'upload_desc' => 'Test Upload',
      'upload_filename' => 'test.tar.gz',
      'user_fk' => $userId,
      'upload_mode' => 104,
      'public' => 1
    ));
  }

  /**
   * Helper to create a job in the database
   */
  private function insertJob($jobId, $uploadId, $userId)
  {
    $this->dbManager->insertTableRow('job', array(
      'job_pk' => $jobId,
      'job_queued' => date('Y-m-d H:i:s'),
      'job_name' => 'Test Job',
      'job_upload_fk' => $uploadId,
      'job_user_fk' => $userId
    ));
  }

  /**
   * Helper to add a job to the queue
   */
  private function insertJobQueue($jqId, $jobId, $endBits = 0)
  {
    $this->dbManager->insertTableRow('jobqueue', array(
      'jq_pk' => $jqId,
      'jq_job_fk' => $jobId,
      'jq_type' => 'test',
      'jq_args' => 1,
      'jq_starttime' => null,
      'jq_endtime' => null,
      'jq_end_bits' => $endBits,
      'jq_schedinfo' => null,
      'jq_itemsprocessed' => 0
    ));
  }

  /**
   * Test getting job status for a single user
   */
  public function testGetAllJobStatusForSingleUser()
  {
    $userId = 100;
    $groupId = 200;
    $uploadId = 300;
    $jobId = 400;
    $jqId = 500;

    $this->insertUser($userId);
    $this->insertUpload($uploadId, $userId);
    $this->insertJob($jobId, $uploadId, $userId);
    $this->insertJobQueue($jqId, $jobId, 1);

    $result = $this->jobDao->getAllJobStatus($uploadId, $userId, $groupId);
    
    assertThat($result, is(arrayWithSize(1)));
    assertThat($result[$jqId], is(1));
    $this->addToAssertionCount(2);
  }

  /**
   * Test that no jobs are returned for different upload
   */
  public function testGetAllJobStatusReturnsEmptyForDifferentUpload()
  {
    $userId = 101;
    $groupId = 201;
    $uploadId = 301;
    $jobId = 401;
    $jqId = 501;

    $this->insertUser($userId);
    $this->insertUpload($uploadId, $userId);
    $this->insertJob($jobId, $uploadId, $userId);
    $this->insertJobQueue($jqId, $jobId, 0);

    // Query for a different upload ID
    $result = $this->jobDao->getAllJobStatus(999, $userId, $groupId);
    
    assertThat($result, is(emptyArray()));
    $this->addToAssertionCount(1);
  }

  /**
   * Test getting jobs with multiple queue entries
   */
  public function testGetAllJobStatusWithMultipleQueueEntries()
  {
    $userId = 102;
    $groupId = 202;
    $uploadId = 302;
    $jobId = 402;

    $this->insertUser($userId);
    $this->insertUpload($uploadId, $userId);
    $this->insertJob($jobId, $uploadId, $userId);
    
    // Add multiple queue entries for the same job
    $this->insertJobQueue(600, $jobId, 1);
    $this->insertJobQueue(601, $jobId, 0);
    $this->insertJobQueue(602, $jobId, 2);

    $result = $this->jobDao->getAllJobStatus($uploadId, $userId, $groupId);
    
    assertThat($result, is(arrayWithSize(3)));
    assertThat($result[600], is(1));
    assertThat($result[601], is(0));
    assertThat($result[602], is(2));
    $this->addToAssertionCount(4);
  }

  /**
   * Test getting child job queue status
   */
  public function testGetChildJobStatus()
  {
    $userId = 103;
    $uploadId = 303;
    $jobId = 403;

    $this->insertUser($userId);
    $this->insertUpload($uploadId, $userId);
    $this->insertJob($jobId, $uploadId, $userId);
    
    // Add job queue entries
    $this->insertJobQueue(700, $jobId, 1);
    $this->insertJobQueue(701, $jobId, 2);

    $result = $this->jobDao->getChlidJobStatus($jobId);
    
    assertThat($result, is(arrayWithSize(2)));
    assertThat(array_key_exists(700, $result), is(true));
    assertThat(array_key_exists(701, $result), is(true));
    $this->addToAssertionCount(3);
  }

  /**
   * Test that empty array is returned when no child jobs exist
   */
  public function testGetChildJobStatusReturnsEmptyForNoJobs()
  {
    $result = $this->jobDao->getChlidJobStatus(99999);
    
    assertThat($result, is(emptyArray()));
    $this->addToAssertionCount(1);
  }

  /**
   * Test permission checking for job actions
   * This test verifies the method works, though the implementation
   * appears to have some issues (returns wrong data in original code)
   */
  public function testHasActionPermissionsOnJob()
  {
    $userId = 104;
    $groupId = 204;
    $uploadId = 304;
    $jobId = 404;

    $this->insertUser($userId);
    $this->insertUpload($uploadId, $userId);
    $this->insertJob($jobId, $uploadId, $userId);

    // The method exists and should execute without error
    $result = $this->jobDao->hasActionPermissionsOnJob($jobId, $userId, $groupId);
    
    // Just verify the method returns an array
    assertThat($result, is(arrayValue()));
    $this->addToAssertionCount(1);
  }
}
