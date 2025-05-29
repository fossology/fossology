<?php
/*
 SPDX-FileCopyrightText: © 2025 Harshit Gandhi <gandhiharshit716@gmail.com>
 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @file
 * @brief Tests for JobQueue model
 */

namespace Fossology\UI\Api\Test\Models;

use Fossology\UI\Api\Models\JobQueue;
use PHPUnit\Framework\TestCase;

/**
 * @class JobQueueTest
 * @brief Tests for JobQueue model
 */
class JobQueueTest extends TestCase
{
  private $jobQueue;

  protected function setUp(): void
  {
    $this->jobQueue = new JobQueue(
      123,
      "monkbulk",
      "2024-02-16 10:00:00",
      "2024-02-16 10:30:00",
      "Completed",
      500,
      "/srv/fossology/logs/123.log",
      [1, 2, 3],
      16.67,
      true,
      false,
      true,
      ["text" => "Download Report", "link" => "/download/report/123"]
    );
  }

  ////// Constructor Tests //////

  /**
   * Tests that the JobQueue constructor initializes an instance correctly.
   *
   * @return void
   */
  public function testConstructor()
  {
    $this->assertInstanceOf(JobQueue::class, $this->jobQueue);
  }

  ////// Getter Tests //////

  /**
   * @test
   * -# Test getter for JobQueueId
   */
  public function testGetJobQueueId()
  { 
    $this->assertEquals(123, $this->jobQueue->getJobQueueId());
  }
  
  /**
   * @test
   * -# Test getter for JobQueueType
   */
  public function testGetJobQueueType()
  {
    $this->assertEquals("monkbulk", $this->jobQueue->getJobQueueType());
  }

  /**
   * @test
   * -# Test getter for StartTime
   */
  public function testGetStartTime() 
  { 
    $this->assertEquals("2024-02-16 10:00:00", $this->jobQueue->getStartTime()); 
  }

  /**
   * @test
   * -# Test getter for EndTime
   */
  public function testGetEndTime() 
  {
    $this->assertEquals("2024-02-16 10:30:00", $this->jobQueue->getEndTime());
  }

  /**
   * @test
   * -# Test getter for Status
   */
  public function testGetStatus() 
  { 
    $this->assertEquals("Completed", $this->jobQueue->getStatus()); 
  }

  /**
   * @test
   * -# Test getter for ItemsProcesse
   */
  public function testGetItemsProcessed() 
  { 
    $this->assertEquals(500, $this->jobQueue->getItemsProcessed());
  }

  /**
   * @test
   * -# Test getter for Log
   */
  public function testGetLog() 
  { 
    $this->assertEquals("/srv/fossology/logs/123.log", $this->jobQueue->getLog()); 
  }

  /**
   * @test
   * -# Test getter for Dependencies
   */
  public function testGetDependencies() 
  { 
    $this->assertEquals([1, 2, 3], $this->jobQueue->getDependencies()); 
  }

  /**
   * @test
   * -# Test getter for ItemsPerSec
   */
  public function testGetItemsPerSec() 
  { 
    $this->assertEquals(16.67, $this->jobQueue->getItemsPerSec()); 
  }

  /**
   * @test
   * -# Test getter for CanDoActions
   */
  public function testIsCanDoActions() 
  { 
    $this->assertEquals(true, $this->jobQueue->isCanDoActions()); 
  }

  /**
   * @test
   * -# Test getter for Progress
   */
  public function testIsInProgress() 
  { 
    $this->assertEquals(false, $this->jobQueue->isInProgress()); 
  }

  /**
   * @test
   * -# Test getter for isReady
   */
  public function testIsReady() 
  { 
    $this->assertEquals(true, $this->jobQueue->isReady()); 
  }

  /**
   * @test
   * -# Test getter for Download
   */
  public function testGetDownload() 
  { 
    $this->assertEquals(["text" => "Download Report", "link" => "/download/report/123"], $this->jobQueue->getDownload()); 
  }

  ////// Setter Tests //////

  /**
   * @test
   * -# Test setter for Job Queue Id
   */
  public function testSetJobQueueId() 
  {
    $this->jobQueue->setJobQueueId(123);
    $this->assertEquals(123, $this->jobQueue->getJobQueueId());
  }

  /**
   * @test
   * -# Test setter for Job Queue Type
   */
  public function testSetJobQueueType() 
  {
    $this->jobQueue->setJobQueueType("monkBulk");
    $this->assertEquals("monkBulk", $this->jobQueue->getJobQueueType());
  }

  /**
   * @test
   * -# Test setter for Start Time
   */
  public function testSetStartTime() 
  {
    $this->jobQueue->setStartTime("2024-02-16 10:00:00");
    $this->assertEquals("2024-02-16 10:00:00", $this->jobQueue->getStartTime());
  }

  /**
   * @test
   * -# Test setter for End Time
   */
  public function testSetEndTime() 
  {
    $this->jobQueue->setEndTime("2024-02-16 10:30:00");
    $this->assertEquals("2024-02-16 10:30:00", $this->jobQueue->getEndTime());
  }

  /**
   * @test
   * -# Test setter for Status
   */
  public function testSetStatus() 
  {
    $this->jobQueue->setStatus("Completed");
    $this->assertEquals("Completed", $this->jobQueue->getStatus());
  }

  /**
   * @test
   * -# Test setter for Items Processed
   */
  public function testSetItemsProcessed() 
  {
    $this->jobQueue->setItemsProcessed(500);
    $this->assertEquals(500, $this->jobQueue->getItemsProcessed());
  }

  /**
   * @test
   * -# Test setter for Items Per Sec
   */
  public function testSetItemsPerSec() 
  {
    $this->jobQueue->setItemsPerSec(16.67);
    $this->assertEquals(16.67, $this->jobQueue->getItemsPerSec());
  }

  /**
   * @test
   * -# Test setter for Can Do Actions
   */
  public function testSetCanDoActions() 
  {
    $this->jobQueue->setCanDoActions(true);
    $this->assertEquals(true, $this->jobQueue->isCanDoActions());
  }

  /**
   * @test
   * -# Test setter for Is In Progress
   */
  public function testSetIsInProgress() 
  {
    $this->jobQueue->setCanDoActions(false);
    $this->assertEquals(false, $this->jobQueue->isInProgress());
  }

  /**
   * @test
   * -# Test setter for Items Per Sec
   */
  public function testSetIsReady() 
  {
    $this->jobQueue->setIsReady(true);
    $this->assertEquals(true, $this->jobQueue->isReady());
  }

  /**
   * @test
   * -# Test setter for log
   */
  public function testSetLog() 
  {
    $this->jobQueue->setLog(null);
    $this->assertNull($this->jobQueue->getLog());
  }

  /**
   * @test
   * -# Test setter for Download
   */
  public function testSetDownload() 
  {
    $this->jobQueue->setDownload(null);
    $this->assertNull($this->jobQueue->getDownload());
  }

  /**
   * @test
   * -# Test setter for Dependencies
   */
  public function testSetEmptyDependencies() 
  {
    $this->jobQueue->setDependencies([]);
    $this->assertEmpty($this->jobQueue->getDependencies());
  }

  ////// JSON Tests //////

  /**
   * @test
   * -# Test getter for JSON
   */
  public function testGetJSON() {
    $json = $this->jobQueue->getJSON();
    $this->assertJson($json);
    $this->assertEquals($this->jobQueue->getArray(), json_decode($json, true));
  }

  ////// GetArray Tests //////

  /**
   * @test
   * -# Test getter for Array
   */
  public function testGetArray() 
  {
    $expectedArray = [
      "jobQueueId" => 123,
      "jobQueueType" => "monkbulk",
      "startTime" => "2024-02-16 10:00:00",
      "endTime" => "2024-02-16 10:30:00",
      "status" => "Completed",
      "itemsProcessed" => 500,
      "log" => "/srv/fossology/logs/123.log",
      "dependencies" => [1, 2, 3],
      "itemsPerSec" => 16.67,
      "canDoActions" => true,
      "isInProgress" => false,
      "isReady" => true,
      "download" => ["text" => "Download Report", "link" => "/download/report/123"]
    ];
    $this->assertEquals($expectedArray, $this->jobQueue->getArray());
  }
}
