<?php
/*
 SPDX-FileCopyrightText: © Fossology contributors

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Dao;

use Fossology\Lib\Test\TestLiteDb;
use Monolog\Logger;

class JobDaoTest extends \PHPUnit\Framework\TestCase
{
  /** @var TestLiteDb */
  private $testDb;

  /** @var \Fossology\Lib\Db\DbManager */
  private $dbManager;

  /** @var JobDao */
  private $jobDao;

  /** @var int */
  private $assertCountBefore;

  protected function setUp() : void
  {
    $this->testDb = new TestLiteDb();
    $this->dbManager = $this->testDb->getDbManager();
    $this->jobDao = new JobDao($this->dbManager, new Logger("test"));
    $this->assertCountBefore = \Hamcrest\MatcherAssert::getCount();

    $this->testDb->createPlainTables(array('job', 'jobqueue', 'group_user_member'));

    $this->dbManager->insertTableRow('job', array(
      'job_pk' => 10,
      'job_user_fk' => 1001,
      'job_upload_fk' => 1,
      'job_name' => 'job-owner',
      'job_queued' => date('c')
    ));
    $this->dbManager->insertTableRow('job', array(
      'job_pk' => 11,
      'job_user_fk' => 1002,
      'job_upload_fk' => 1,
      'job_name' => 'job-group',
      'job_queued' => date('c')
    ));

    $this->dbManager->insertTableRow('jobqueue', array(
      'jq_pk' => 21,
      'jq_job_fk' => 10,
      'jq_type' => 'scan'
    ));
    $this->dbManager->insertTableRow('jobqueue', array(
      'jq_pk' => 22,
      'jq_job_fk' => 11,
      'jq_type' => 'scan'
    ));

    $this->dbManager->insertTableRow('group_user_member', array(
      'user_fk' => 1003,
      'group_fk' => 2001
    ));
    $this->dbManager->insertTableRow('group_user_member', array(
      'user_fk' => 1002,
      'group_fk' => 2001
    ));
  }

  protected function tearDown() : void
  {
    $this->addToAssertionCount(\Hamcrest\MatcherAssert::getCount() - $this->assertCountBefore);
    $this->jobDao = null;
    $this->dbManager = null;
    $this->testDb = null;
  }

  public function testHasActionPermissionsOnJobForOwnerAndGroupMember()
  {
    assertThat($this->jobDao->hasActionPermissionsOnJob(10, 1001, 9999), is(true));
    assertThat($this->jobDao->hasActionPermissionsOnJob(11, 1003, 2001), is(true));
    assertThat($this->jobDao->hasActionPermissionsOnJob(11, 1004, 9999), is(false));
  }

  public function testHasActionPermissionsOnJobQueueUsesQueueIdentifier()
  {
    assertThat($this->jobDao->hasActionPermissionsOnJobQueue(21, 1001, 9999), is(true));
    assertThat($this->jobDao->hasActionPermissionsOnJobQueue(22, 1003, 2001), is(true));
    assertThat($this->jobDao->hasActionPermissionsOnJobQueue(22, 1004, 9999), is(false));
    assertThat($this->jobDao->hasActionPermissionsOnJobQueue(999, 1001, 9999), is(false));
  }
}
