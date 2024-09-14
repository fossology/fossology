<?php
/*
 SPDX-FileCopyrightText: © 2024 Valens NIYONSENGA <valensniyonsenga2003@gmail.com>
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
  /**
   * Provides test data and an instance of the Decider class.
   *
   * @param string $version The API version to use for formatting.
   * @return array An associative array containing:
   *  - `expectedArray`: The expected array structure.
   *  - `obj`: The instance of Decider being tested.
   */
  private function getAgentInfo()
  {
    $expectedArray = [
      'successfulAgents' => ["Monk","nomos"],
      'uploadId' => 3,
      'agentName' => "ninka",
      'currentAgentId' => 4,
      'currentAgentRev' => 454,
      'isAgentRunning' => null
      ];
    $obj = new Agent($expectedArray['successfulAgents'], $expectedArray['uploadId'], $expectedArray['agentName'], $expectedArray['currentAgentId'], $expectedArray['currentAgentRev'],null);
    return [
      'expectedArray' => $expectedArray,
      'obj' => $obj
    ];
  }
  /**
   * Tests Agent::getSuccessfulAgents() method.
   *
   * This method validates that the `getSuccessfulAgents` method returns the correct array of successful agents.
   */
  public function testGetSuccessfulAgents()
  {
    $info = $this->getAgentInfo();
    $agent = $info['obj'];
    $this->assertEquals(["Monk","nomos"], $agent->getSuccessfulAgents());
  }

  /**
   * Tests Agent::getUploadId() method.
   *
   * This method validates that the `getUploadId` method returns the correct upload ID value.
   */
  public function testGetUploadId()
  {
    $info = $this->getAgentInfo();
    $agent = $info['obj'];
    $this->assertEquals(3, $agent->getUploadId());
  }

  /**
   * Tests Agent::getAgentName() method.
   *
   * This method validates that the `getAgentName` method returns the correct agent name.
   */
  public function testGetAgentName()
  {
    $info = $this->getAgentInfo();
    $agent = $info['obj'];
    $this->assertEquals("ninka", $agent->getAgentName());
  }

  /**
   * Tests Agent::getCurrentAgentId() method.
   *
   * This method validates that the `getCurrentAgentId` method returns the correct current agent ID value.
   */
  public function testGetCurrentAgentId()
  {
    $info = $this->getAgentInfo();
    $agent = $info['obj'];
    $this->assertEquals(4, $agent->getCurrentAgentId());
  }

  /**
   * Tests Agent::getCurrentAgentRev() method.
   *
   * This method validates that the `getCurrentAgentRev` method returns the correct current agent revision.
   */
  public function testGetCurrentAgentRev()
  {
    $info = $this->getAgentInfo();
    $agent = $info['obj'];
    $this->assertEquals(454, $agent->getCurrentAgentRev());
  }

  /**
   * Tests Agent::setSuccessfulAgents() method.
   *
   * This method validates that the `setSuccessfulAgents` method updates the list of successful agents.
   */
  public function testSetSuccessfulAgents()
  {
    $info = $this->getAgentInfo();
    $agent = $info['obj'];
    $agent->setSuccessfulAgents(["nomos"]);
    $this->assertEquals(["nomos"], $agent->getSuccessfulAgents());
  }

  /**
   * Tests Agent::setUploadId() method.
   *
   * This method validates that the `setUploadId` method updates the upload ID value.
   */
  public function testSetUploadId()
  {
    $info = $this->getAgentInfo();
    $agent = $info['obj'];
    $agent->setUploadId(5);
    $this->assertEquals(5, $agent->getUploadId());
  }

  /**
   * Tests Agent::setAgentName() method.
   *
   * This method validates that the `setAgentName` method updates the agent's name.
   */
  public function testSetAgentName()
  {
    $info = $this->getAgentInfo();
    $agent = $info['obj'];
    $agent->setAgentName("monk");
    $this->assertEquals("monk", $agent->getAgentName());
  }

  /**
   * Tests Agent::setCurrentAgentId() method.
   *
   * This method validates that the `setCurrentAgentId` method updates the current agent ID.
   */
  public function testSetCurrentAgentId()
  {
    $info = $this->getAgentInfo();
    $agent = $info['obj'];
    $agent->setCurrentAgentId(6);
    $this->assertEquals(6, $agent->getCurrentAgentId());
  }

  /**
   * Tests Agent::setCurrentAgentRev() method.
   *
   * This method validates that the `setCurrentAgentRev` method updates the current agent revision.
   */
  public function testSetCurrentAgentRev()
  {
    $info = $this->getAgentInfo();
    $agent = $info['obj'];
    $agent->setCurrentAgentRev(40);
    $this->assertEquals(40, $agent->getCurrentAgentRev());
  }

  /**
   * Tests Agent::setIsAgentRunning() method.
   *
   * This method validates that the `setIsAgentRunning` method updates the agent running status.
   */
  public function testSetIsAgentRunning()
  {
    $info = $this->getAgentInfo();
    $agent = $info['obj'];
    $agent->setIsAgentRunning(true);
    $this->assertTrue($agent->getIsAgentRunning());
  }

  /**
   * Tests Agent::getArray() method for API version V1.
   *
   * This method checks if `getArray()` returns the correct associative array for version V1.
   */
  public function testGetArrayV1()
  {
    $info = $this->getAgentInfo();
    $agent = $info['obj'];

    $expectedArray = [
      'successfulAgents' =>  $agent->getSuccessfulAgents(),
      'uploadId' =>  $agent->getUploadId(),
      'agentName' =>  $agent->getAgentName(),
      'currentAgentId' =>  $agent->getCurrentAgentId(),
      'currentAgentRev' =>  $agent->getCurrentAgentRev(),
      'isAgentRunning' => null,
    ];

    $this->assertEquals($expectedArray, $agent->getArray(ApiVersion::V1));
  }

  /**
   * Tests Agent::getJSON() method for API version V1.
   *
   * This method checks if `getJSON()` returns the correct JSON string for version V1.
   */
  public function testGetJSONV1()
  {
    $info = $this->getAgentInfo();
    $agent = $info['obj'];

    $expectedJSON = json_encode([
      'successfulAgents' => $agent->getSuccessfulAgents(),
      'uploadId' => $agent->getUploadId(),
      'agentName' => $agent->getAgentName(),
      'currentAgentId' => $agent->getCurrentAgentId(),
      'currentAgentRev' => $agent->getCurrentAgentRev(),
      'isAgentRunning' => null,
    ]);

    $this->assertEquals($expectedJSON, $agent->getJSON(ApiVersion::V1));
  }
}
