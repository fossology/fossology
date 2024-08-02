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

use Fossology\UI\Api\Models\Job;
use PHPUnit\Framework\TestCase;

/**
 * @class JobTest
 * @brief Test for Job model
 */
class JobTest extends TestCase
{
  /**
   * Provides test data and instances of the Job class.
   * @return array An associative array containing test data and Job objects.
   */
  private function getJobInfo()
  {
    return [
      'jobInfo' => [
        'id'        => 22,
        'name'      => 'ojo',
        'queueDate' => '01-01-2020',
        'uploadId'  => 4,
        'userId'    => 2,
        'groupId'   => 2,
        'eta'       => 3,
        'status'    => 'Processing'
      ],
      'obj' => new Job(22, 'ojo', '01-01-2020', 4, 2, 2, 3, 'Processing')
    ];
  }
  /**
   * Test Job::getArray()
   * Tests the getArray method of the Job class.
   * - # Check if the returned array matches the expected format.
   */
  public function testDataFormat()
  {
    $expectedArray = $this->getJobInfo()['jobInfo'];
    $actualJob = $this->getJobInfo()['obj'];

    $this->assertEquals($expectedArray, $actualJob->getArray());
  }

  /**
   * Test Job::getJSON()
   * Tests the getJSON method of the Job class.
   * - # Check if the returned JSON string matches the expected format.
   */
  public function testGetJSON()
  {
    $expectedJson = json_encode([
      'id'        => 22,
      'name'      => 'ojo',
      'queueDate' => '01-01-2020',
      'uploadId'  => 4,
      'userId'    => 2,
      'groupId'   => 2,
      'eta'       => 3,
      'status'    => 'Processing'
    ]);

    $actualJob = new Job(22, 'ojo', '01-01-2020', 4, 2, 2, 3, 'Processing');

    $this->assertEquals($expectedJson, $actualJob->getJSON());
  }

  /**
   * Test Job::getId()
   * Tests the getId method of the Job class.
   * - # Check if the id property is correctly retrieved.
   */
  public function testGetId()
  {
    $job = $this->getJobInfo()['obj'];
    $this->assertEquals(22, $job->getId());
  }

  /**
   * Test Job::getName()
   * Tests the getName method of the Job class.
   * - # Check if the name property is correctly retrieved.
   */
  public function testGetName()
  {
    $job = $this->getJobInfo()['obj'];
    $this->assertEquals('ojo', $job->getName());
  }

  /**
   * Test Job::getQueueDate()
   * Tests the getQueueDate method of the Job class.
   * - # Check if the queueDate property is correctly retrieved.
   */
  public function testGetQueueDate()
  {
    $job = $this->getJobInfo()['obj'];
    $this->assertEquals('01-01-2020', $job->getQueueDate());
  }

  /**
   * Test Job::getUploadId()
   * Tests the getUploadId method of the Job class.
   * - # Check if the uploadId property is correctly retrieved.
   */
  public function testGetUploadId()
  {
    $job = $this->getJobInfo()['obj'];
    $this->assertEquals(4, $job->getUploadId());
  }

  /**
   * Test Job::getUserId()
   * Tests the getUserId method of the Job class.
   * - # Check if the userId property is correctly retrieved.
   */
  public function testGetUserId()
  {
    $job = $this->getJobInfo()['obj'];
    $this->assertEquals(2, $job->getUserId());
  }

  /**
   * Test Job::getGroupId()
   * Tests the getGroupId method of the Job class.
   * - # Check if the groupId property is correctly retrieved.
   */
  public function testGetGroupId()
  {
    $job = $this->getJobInfo()['obj'];
    $this->assertEquals(2, $job->getGroupId());
  }

  /**
   * Test Job::getEta()
   * Tests the getEta method of the Job class.
   * - # Check if the eta property is correctly retrieved.
   */
  public function testGetEta()
  {
    $job = $this->getJobInfo()['obj'];
    $this->assertEquals(3, $job->getEta());
  }

  /**
   * Test Job::getStatus()
   * Tests the getStatus method of the Job class.
   * - # Check if the status property is correctly retrieved.
   */
  public function testGetStatus()
  {
    $job = $this->getJobInfo()['obj'];
    $this->assertEquals('Processing', $job->getStatus());
  }

  /**
   * Test Job::setName()
   * Tests the setName method of the Job class.
   * - # Check if the name property is correctly set.
   */
  public function testSetName()
  {
    $job = $this->getJobInfo()['obj'];
    $job->setName('ojo');
    $this->assertEquals('ojo', $job->getName());
  }

  /**
   * Test Job::setQueueDate()
   * Tests the setQueueDate method of the Job class.
   * - # Check if the queueDate property is correctly set.
   */
  public function testSetQueueDate()
  {
    $job = $this->getJobInfo()['obj'];
    $job->setQueueDate('01-01-2020');
    $this->assertEquals('01-01-2020', $job->getQueueDate());
  }

  /**
   * Test Job::setUploadId()
   * Tests the setUploadId method of the Job class.
   * - # Check if the uploadId property is correctly set.
   */
  public function testSetUploadId()
  {
    $job = $this->getJobInfo()['obj'];
    $job->setUploadId(4);
    $this->assertEquals(4, $job->getUploadId());
  }

  /**
   * Test Job::setUserId()
   * Tests the setUserId method of the Job class.
   * - # Check if the userId property is correctly set.
   */
  public function testSetUserId()
  {
    $job = $this->getJobInfo()['obj'];
    $job->setUserId(2);
    $this->assertEquals(2, $job->getUserId());
  }

  /**
   * Test Job::setGroupId()
   * Tests the setGroupId method of the Job class.
   * - # Check if the groupId property is correctly set.
   */
  public function testSetGroupId()
  {
    $job = $this->getJobInfo()['obj'];
    $job->setGroupId(2);
    $this->assertEquals(2, $job->getGroupId());
  }

  /**
   * Test Job::setEta()
   * Tests the setEta method of the Job class.
   * - # Check if the eta property is correctly set.
   */
  public function testSetEta()
  {
    $job = $this->getJobInfo()['obj'];
    $job->setEta(3);
    $this->assertEquals(3, $job->getEta());
  }

  /**
   * Test Job::setStatus()
   * Tests the setStatus method of the Job class.
   * - # Check if the status property is correctly set.
   */
  public function testSetStatus()
  {
    $job = $this->getJobInfo()['obj'];
    $job->setStatus('Processing');
    $this->assertEquals('Processing', $job->getStatus());
  }
}
