<?php
/*
 SPDX-FileCopyrightText: © 2025 Harshit Gandhi <gandhiharshit716@gmail.com>
 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @file
 * @brief Tests for Agent model
 */

namespace Fossology\UI\Api\Test\Models;

use Fossology\UI\Api\Models\Agent;
use Fossology\UI\Api\Models\ApiVersion;

use PHPUnit\Framework\TestCase;

/**
 * @class AgentTest
 * @brief Tests for Agent model
 */
class AgentTest extends TestCase
{
  ////// Constructor Tests //////

  /**
   * Tests that the Agent constructor initializes an instance correctly.
   *
   * @return void
   */
  public function testConstructor()
  {
    $successfulAgents = ['Agent1', 'Agent2'];
    $uploadId = 12345;
    $agentName = 'TestAgent';
    $currentAgentId = 67890;
    $currentAgentRev = 'rev1';
    $isAgentRunning = true;

    $agent = new Agent($successfulAgents, $uploadId, $agentName, $currentAgentId, $currentAgentRev, $isAgentRunning);
    $this->assertInstanceOf(Agent::class, $agent);
  }

  /**
   * @test
   * -# Test the data format returned by Agent::getArray()
   */
  public function testDataFormat()
  {
    $expectedArray = [
      'successfulAgents' => ['agent1', 'agent2'],
      'uploadId' => 1,
      'agentName' => "Test Agent",
      'currentAgentId' => 100,
      'currentAgentRev' => "1.0.0",
      'isAgentRunning' => true
    ];

    $agent = new Agent(
      $expectedArray['successfulAgents'],
      $expectedArray['uploadId'],
      $expectedArray['agentName'],
      $expectedArray['currentAgentId'],
      $expectedArray['currentAgentRev'],
      $expectedArray['isAgentRunning']
    );

    $this->assertEquals($expectedArray, $agent->getArray());
  }

  /**
   * @test
   * -# Test JSON encoding through getJSON() method
   */
  public function testGetJson()
  {
    $agent = new Agent(
      ['agent1', 'agent2'],
      1,
      "Test Agent",
      100,
      "1.0.0",
      true
    );
    
    $expectedJson = json_encode([
      'successfulAgents' => ['agent1', 'agent2'],
      'uploadId' => 1,
      'agentName' => "Test Agent",
      'currentAgentId' => 100,
      'currentAgentRev' => "1.0.0",
      'isAgentRunning' => true
    ]);

    $this->assertEquals($expectedJson, $agent->getJSON());
  }

  ////// Getter Tests //////

  /**
   * @test
   * -# Test getter for successfulAgents
   */
  public function testGetSuccessfulAgents()
  {
    $successfulAgents = ['agent1', 'agent2'];
    $agent = new Agent($successfulAgents, 1, "Test", 100, "1.0.0", true);
    $this->assertEquals($successfulAgents, $agent->getSuccessfulAgents());
  }

  /**
   * @test
   * -# Test getter for uploadId
   */
  public function testGetUploadId()
  {
    $uploadId = 1;
    $agent = new Agent([], $uploadId, "Test", 100, "1.0.0", true);
    $this->assertEquals($uploadId, $agent->getUploadId());
  }

  /**
   * @test
   * -# Test getter for agentName
   */
  public function testGetAgentName()
  {
    $agentName = "Test Agent";
    $agent = new Agent([], 1, $agentName, 100, "1.0.0", true);
    $this->assertEquals($agentName, $agent->getAgentName());
  }

  /**
   * @test
   * -# Test getter for currentAgentId
   */
  public function testGetCurrentAgentId()
  {
    $currentAgentId = 100;
    $agent = new Agent([], 1, "Test", $currentAgentId, "1.0.0", true);
    $this->assertEquals($currentAgentId, $agent->getCurrentAgentId());
  }

  /**
   * @test
   * -# Test getter for currentAgentRev
   */
  public function testGetCurrentAgentRev()
  {
    $currentAgentRev = "1.0.0";
    $agent = new Agent([], 1, "Test", 100, $currentAgentRev, true);
    $this->assertEquals($currentAgentRev, $agent->getCurrentAgentRev());
  }

  /**
   * @test
   * -# Test getter for isAgentRunning
   */
  public function testGetIsAgentRunning()
  {
    $isAgentRunning = true;
    $agent = new Agent([], 1, "Test", 100, "1.0.0", $isAgentRunning);
    $this->assertTrue($agent->getIsAgentRunning());
  }

  ////// Setter Tests //////

  /**
   * @test
   * -# Test setter for successfulAgents
   */
  public function testSetSuccessfulAgents()
  {
    $agent = new Agent([], 1, "Test", 100, "1.0.0", true);
    $newSuccessfulAgents = ['agent1', 'agent2', 'agent3'];
    $agent->setSuccessfulAgents($newSuccessfulAgents);
    $this->assertEquals($newSuccessfulAgents, $agent->getSuccessfulAgents());
  }

  /**
   * @test
   * -# Test setter for uploadId
   */
  public function testSetUploadId()
  {
    $agent = new Agent([], 1, "Test", 100, "1.0.0", true);
    $newUploadId = 2;
    $agent->setUploadId($newUploadId);
    $this->assertEquals($newUploadId, $agent->getUploadId());
  }

  /**
   * @test
   * -# Test setter for agentName
   */
  public function testSetAgentName()
  {
    $agent = new Agent([], 1, "Test", 100, "1.0.0", true);
    $newAgentName = "New Test Agent";
    $agent->setAgentName($newAgentName);
    $this->assertEquals($newAgentName, $agent->getAgentName());
  }

  /**
   * @test
   * -# Test setter for currentAgentId
   */
  public function testSetCurrentAgentId()
  {
    $agent = new Agent([], 1, "Test", 100, "1.0.0", true);
    $newCurrentAgentId = 200;
    $agent->setCurrentAgentId($newCurrentAgentId);
    $this->assertEquals($newCurrentAgentId, $agent->getCurrentAgentId());
  }

  /**
   * @test
   * -# Test setter for currentAgentRev
   */
  public function testSetCurrentAgentRev()
  {
    $agent = new Agent([], 1, "Test", 100, "1.0.0", true);
    $newCurrentAgentRev = "2.0.0";
    $agent->setCurrentAgentRev($newCurrentAgentRev);
    $this->assertEquals($newCurrentAgentRev, $agent->getCurrentAgentRev());
  }

  /**
   * @test
   * -# Test setter for isAgentRunning
   */
  public function testSetIsAgentRunning()
  {
    $agent = new Agent([], 1, "Test", 100, "1.0.0", true);
    $newIsAgentRunning = false;
    $agent->setIsAgentRunning($newIsAgentRunning);
    $this->assertFalse($agent->getIsAgentRunning());
  }
}