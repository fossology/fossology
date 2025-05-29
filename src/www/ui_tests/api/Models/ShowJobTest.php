<?php
/*
 SPDX-FileCopyrightText: © 2025 Harshit Gandhi <gandhiharshit716@gmail.com>
 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @file
 * @brief Tests for ShowJob
 */
namespace Fossology\UI\Api\Test\Models;

use Fossology\UI\Api\Models\ShowJob;

use \PHPUnit\Framework\TestCase;

/**
 * @class ShowJobTest
 * @brief Tests for ShowJob model
 */
class ShowJobTest extends TestCase
{
  ////// Constructor Tests //////

  /**
   * Tests that the ShowjOB constructor initializes an instance correctly.
   *
   * @return void
   */
  public function testConstructor()
  {
    $testData = [
      'jobId' => 123,
      'jobName' => 'Test Job',
      'jobQueue' => ['queue1', 'queue2'],
      'uploadId' => 456
    ];

    $showJob = new ShowJob(
      $testData['jobId'],
      $testData['jobName'],
      $testData['jobQueue'],
      $testData['uploadId']
    );
    
    $this->assertInstanceOf(ShowJob::class, $showJob);
  }

  ////// Getter Tests //////

  /**
   * @test
   * -# Test getJobId getter
   */
  public function testGetJobId()
  {
    $showJob = new ShowJob(123, 'Test Job', ['queue1'], 456);
    $this->assertEquals(123, $showJob->getJobId());
  }

  /**
   * @test
   * -# Test getJobName getter
   */
  public function testGetJobName()
  {
    $showJob = new ShowJob(123, 'Test Job', ['queue1'], 456);
    $this->assertEquals('Test Job', $showJob->getJobName());
  }

  /**
   * @test
   * -# Test getJobQueue getter
   */
  public function testGetJobQueue()
  {
    $jobQueue = ['queue1', 'queue2'];
    $showJob = new ShowJob(123, 'Test Job', $jobQueue, 456);
    $this->assertEquals($jobQueue, $showJob->getJobQueue());
  }

  /**
   * @test
   * -# Test getUploadId getter
   */
  public function testGetUploadId()
  {
    $showJob = new ShowJob(123, 'Test Job', ['queue1'], 456);
    $this->assertEquals(456, $showJob->getUploadId());
  }

  ////// Setter Tests //////

  /**
   * @test
   * -# Test setJobId setter
   * -# Verify integer type conversion
   */
  public function testSetJobId()
  {
    $showJob = new ShowJob(123, 'Test Job', ['queue1'], 456);
    
    // Test with integer
    $showJob->setJobId(789);
    $this->assertEquals(789, $showJob->getJobId());
    
    // Test with string that should be converted to integer
    $showJob->setJobId("321");
    $this->assertEquals(321, $showJob->getJobId());
  }

  /**
   * @test
   * -# Test setJobName setter
   */
  public function testSetJobName()
  {
    $showJob = new ShowJob(123, 'Test Job', ['queue1'], 456);
    $showJob->setJobName('New Job Name');
    $this->assertEquals('New Job Name', $showJob->getJobName());
  }

  /**
   * @test
   * -# Test setJobQueue setter
   */
  public function testSetJobQueue()
  {
    $showJob = new ShowJob(123, 'Test Job', ['queue1'], 456);
    $newQueue = ['queue3', 'queue4'];
    $showJob->setJobQueue($newQueue);
    $this->assertEquals($newQueue, $showJob->getJobQueue());
  }

  /**
   * @test
   * -# Test setUploadId setter
   * -# Verify integer type conversion
   */
  public function testSetUploadId()
  {
    $showJob = new ShowJob(123, 'Test Job', ['queue1'], 456);
    
    // Test with integer
    $showJob->setUploadId(789);
    $this->assertEquals(789, $showJob->getUploadId());
    
    // Test with string that should be converted to integer
    $showJob->setUploadId("321");
    $this->assertEquals(321, $showJob->getUploadId());
  }

  /**
   * @test
   * -# Test getArray method
   */
  public function testGetArray()
  {
    $testData = [
      'jobId' => 123,
      'jobName' => 'Test Job',
      'jobQueue' => ['queue1', 'queue2'],
      'uploadId' => 456
    ];

    $showJob = new ShowJob(
      $testData['jobId'],
      $testData['jobName'],
      $testData['jobQueue'],
      $testData['uploadId']
    );
    
    $expectedArray = [
      'jobId' => $testData['jobId'],
      'jobName' => $testData['jobName'],
      'jobQueue' => $testData['jobQueue'],
      'uploadId' => $testData['uploadId']
    ];
    
    $this->assertEquals($expectedArray, $showJob->getArray());
  }

  /**
   * @test
   * -# Test getJSON method
   */
  public function testGetJSON()
  {
    $testData = [
      'jobId' => 123,
      'jobName' => 'Test Job',
      'jobQueue' => ['queue1', 'queue2'],
      'uploadId' => 456
    ];

    $showJob = new ShowJob(
      $testData['jobId'],
      $testData['jobName'],
      $testData['jobQueue'],
      $testData['uploadId']
    );
    
    $expectedArray = [
      'jobId' => $testData['jobId'],
      'jobName' => $testData['jobName'],
      'jobQueue' => $testData['jobQueue'],
      'uploadId' => $testData['uploadId']
    ];
    
    $this->assertEquals(json_encode($expectedArray), $showJob->getJSON());
  }
}