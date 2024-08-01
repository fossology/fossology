<?php

/*
 SPDX-FileCopyrightText: © 2024 Valens Niyonsenga <valensniyonsenga2003@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @file
 * @brief Tests for FileInfo
 */
namespace Fossology\UI\Api\Test\Models;

use Fossology\UI\Api\Models\Agent;
use Fossology\UI\Api\Models\ApiVersion;

class AgentTest extends \PHPUnit\Framework\TestCase
{

  /**
   * Provides test data and an instance of the Agent class.
   * @return array An associative array containing test data and an Agent object.
   */
  public function getAgentInfo($version=ApiVersion::V2)
  {
    return [
      'agentInfo' => [
        'successfulAgents' => [],
        'uploadId' => 4,
        'agentName' => "decisionimporter",
        'currentAgentId' => 24,
        'currentAgentRev' => "68097a",
        'isAgentRunning' => null
      ],
      'obj' => new Agent([], 4, "decisionimporter", 24, "68097a", null)
    ];
  }

  /**
   * Test Agent::_constructor
   * Tests that the Agent object's getArray method returns the correct data format.
   * - # Check if the agent was initialized correctly.
   */
  public function testDataFormat()
  {
    $obj = $this->getAgentInfo()['obj'];
    $expectedArray = $this->getAgentInfo()['agentInfo'];
    $this->assertEquals($expectedArray, $obj->getArray());
  }

  /**
   * Test Agent::setUploadId()
   * Tests the setUploadId method of the Agent class.
   *  - # Check if the upload id has changed to the new value.
   */
  public function testSetUploadId()
  {
    $obj = $this->getAgentInfo()['obj'];
    $obj->setUploadId(10);
    $this->assertEquals(10, $obj->getUploadId());
  }

  /**
   * Test Agent:setAgentName()
   * Tests the setAgentName method of the Agent class.
   *  - # Check if the agent name has changed to the new value
   */
  public function testSetAgentName()
  {
    $obj = $this->getAgentInfo()['obj'];
    $obj->setAgentName("reportImport");
    $this->assertEquals("reportImport", $obj->getAgentName());
  }

  /**
   * Tests the setCurrentAgentId method of the Agent class.
   * - # Check if the currentAgentId has changed to the new value.
   */
  public function testSetCurrentAgentId()
  {
    $obj = $this->getAgentInfo()['obj'];
    $obj->setCurrentAgentId(29);
    $this->assertEquals(29, $obj->getCurrentAgentId());
  }
  /**
   * Tests the setCurrentAgentRev method of the Agent class.
   */
  public function testSetCurrentAgentRev()
  {
    $obj = $this->getAgentInfo()['obj'];
    $obj->setCurrentAgentRev("68097b");
    $this->assertEquals("68097b", $obj->getCurrentAgentRev());
  }

  /**
   * Tests the setIsAgentRunning method of the Agent class.
   */
  public function testSetIsAgentRunning()
  {
    $obj = $this->getAgentInfo()['obj'];
    $obj->setIsAgentRunning(true);
    $this->assertEquals(true, $obj->getIsAgentRunning());
  }
}
