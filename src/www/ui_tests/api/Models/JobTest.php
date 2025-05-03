<?php

/*
 SPDX-FileCopyrightText: © 2020 Siemens AG
 Author: Gaurav Mishra <mishra.gaurav@siemens.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @file
 * @brief Tests for Job model
 */

namespace Fossology\UI\Api\Test\Models;

use Fossology\Lib\Dao\UserDao;
use Fossology\UI\Api\Models\ApiVersion;
use Fossology\UI\Api\Models\Job;
use Fossology\UI\Api\Models\JobQueue;
use Mockery as M;
use PHPUnit\Framework\TestCase;

/**
 * @class JobTest
 * @brief Test for Job model
 */
class JobTest extends TestCase
{
  /**
   * @test
   * -# Test data model returned by Job::getArray($version) when API version is V1
   */
  public function testDataFormatV1()
  {
    $this->testDataFormat(ApiVersion::V1);
  }

  /**
   * @test
   * -# Test data model returned by Job::getArray($version) when API version is V2
   */
  public function testDataFormatV2()
  {
    $this->testDataFormat(ApiVersion::V2);
  }

  /**
   * -# Test the data format returned by Job::getArray($version) model
   */
  private function testDataFormat($version)
  {
    $jobQueue = new JobQueue(44, 'readmeoss', '2024-07-03 20:41:49', '2024-07-03 20:41:50',
      'Completed', 0, null, [], 0, true, false, true,
      ['text' => 'ReadMeOss', 'link' => 'http://localhost/repo/api/v1/report/16']
    );

    if ($version == ApiVersion::V2){
      $expectedStatus = [
        'id'        => 22,
        'name'      => 'ojo',
        'queueDate' => '01-01-2020',
        'uploadId'  => 4,
        'userName'  => "fossy",
        'groupName' => "fossy",
        'eta'       => 3,
        'status'    => 'Processing',
        'jobQueue'  => $jobQueue->getArray()
      ];

      global $container;
      $userDao = M::mock(UserDao::class);
      $container = M::mock('ContainerBuilder');
      $container->shouldReceive('get')->with('dao.user')->andReturn($userDao);
      $userDao->shouldReceive('getUserName')->with(2)->andReturn('fossy');
      $userDao->shouldReceive('getGroupNameById')->with(2)->andReturn('fossy');
    } else {
      $expectedStatus = [
        'id'        => 22,
        'name'      => 'ojo',
        'queueDate' => '01-01-2020',
        'uploadId'  => 4,
        'userId'    => 2,
        'groupId'   => 2,
        'eta'       => 3,
        'status'    => 'Processing'
      ];
    }

    $actualJob = new Job(22, 'ojo', '01-01-2020', 4, 2, 2, 3, 'Processing', $jobQueue->getArray());

    $this->assertEquals($expectedStatus, $actualJob->getArray($version));
  }

  // Getter tests
  public function testGetId()
  {
    $job = new Job(22, 'ojo', '01-01-2020', 4, 2, 2, 3, 'Processing');
    $this->assertEquals(22, $job->getId());
  }

  public function testGetName()
  {
    $job = new Job(22, 'ojo', '01-01-2020', 4, 2, 2, 3, 'Processing');
    $this->assertEquals('ojo', $job->getName());
  }

  public function testGetQueueDate()
  {
    $job = new Job(22, 'ojo', '01-01-2020', 4, 2, 2, 3, 'Processing');
    $this->assertEquals('01-01-2020', $job->getQueueDate());
  }

  public function testGetUploadId()
  {
    $job = new Job(22, 'ojo', '01-01-2020', 4, 2, 2, 3, 'Processing');
    $this->assertEquals(4, $job->getUploadId());
  }

  public function testGetUserId()
  {
    $job = new Job(22, 'ojo', '01-01-2020', 4, 2, 2, 3, 'Processing');
    $this->assertEquals(2, $job->getUserId());
  }

  public function testGetGroupId()
  {
    $job = new Job(22, 'ojo', '01-01-2020', 4, 2, 2, 3, 'Processing');
    $this->assertEquals(2, $job->getGroupId());
  }

  public function testGetEta()
  {
    $job = new Job(22, 'ojo', '01-01-2020', 4, 2, 2, 3, 'Processing');
    $this->assertEquals(3, $job->getEta());
  }

  public function testGetStatus()
  {
    $job = new Job(22, 'ojo', '01-01-2020', 4, 2, 2, 3, 'Processing');
    $this->assertEquals('Processing', $job->getStatus());
  }

  public function testGetJobQueue()
  {
    $jobQueue = new JobQueue(44, 'readmeoss', '2024-07-03 20:41:49', '2024-07-03 20:41:50',
      'Completed', 0, null, [], 0, true, false, true,
      ['text' => 'ReadMeOss', 'link' => 'http://localhost/repo/api/v1/report/16']
    );
    $job = new Job(22, 'ojo', '01-01-2020', 4, 2, 2, 3, 'Processing', $jobQueue->getArray());
    $this->assertEquals($jobQueue->getArray(), $job->getJobQueue());
  }

  // Setter tests
  public function testSetName()
  {
    $job = new Job(22, 'ojo', '01-01-2020', 4, 2, 2, 3, 'Processing');
    $job->setName('newName');
    $this->assertEquals('newName', $job->getName());
  }

  public function testSetQueueDate()
  {
    $job = new Job(22, 'ojo', '01-01-2020', 4, 2, 2, 3, 'Processing');
    $job->setQueueDate('02-02-2020');
    $this->assertEquals('02-02-2020', $job->getQueueDate());
  }

  public function testSetUploadId()
  {
    $job = new Job(22, 'ojo', '01-01-2020', 4, 2, 2, 3, 'Processing');
    $job->setUploadId(5);
    $this->assertEquals(5, $job->getUploadId());
  }

  public function testSetUserId()
  {
    $job = new Job(22, 'ojo', '01-01-2020', 4, 2, 2, 3, 'Processing');
    $job->setUserId(3);
    $this->assertEquals(3, $job->getUserId());
  }

  public function testSetGroupId()
  {
    $job = new Job(22, 'ojo', '01-01-2020', 4, 2, 2, 3, 'Processing');
    $job->setGroupId(4);
    $this->assertEquals(4, $job->getGroupId());
  }
  public function testSetEta()
  {
    $job = new Job(22, 'ojo', '01-01-2020', 4, 2, 2, 3, 'Processing');
    $job->setEta(10);
    $this->assertEquals(10, $job->getEta());
  }

  public function testSetStatus()
  {
    $job = new Job(22, 'ojo', '01-01-2020', 4, 2, 2, 3, 'Processing');
    $job->setStatus('Completed');
    $this->assertEquals('Completed', $job->getStatus());
  }
}
