<?php
/*
 SPDX-FileCopyrightText: © 2025 Harshit Gandhi <gandhiharshit716@gmail.com>
 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @file
 * @brief Tests for SuccessfulAgent
 */
namespace Fossology\UI\Api\Test\Models;

use Fossology\UI\Api\Models\SuccessfulAgent;
use Fossology\UI\Api\Models\ApiVersion;

use \PHPUnit\Framework\TestCase;

/**
 * @class SuccessfulAgentTest
 * @brief Tests for SuccessfulAgent model
 */
class SuccessfulAgentTest extends TestCase
{
  ////// Constructor Tests //////

  /**
   * Tests that the SuccessfulAgent constructor initializes an instance correctly.
   *
   * @return void
   */
  public function testConstructor()
  {
    $testData = [
      'agentId' => 1,
      'agentRev' => '1.0.0',
      'agentName' => 'Test Agent'
    ];

    $agent = new SuccessfulAgent(
      $testData['agentId'],
      $testData['agentRev'],
      $testData['agentName']
    );
    
    $this->assertInstanceOf(SuccessfulAgent::class, $agent);
  }

  ////// Getter Tests //////

  /**
   * @test
   * -# Test getAgentId getter
   */
  public function testGetAgentId()
  {
    $agent = new SuccessfulAgent(1, '1.0.0', 'Test Agent');
    $this->assertEquals(1, $agent->getAgentId());
  }

  /**
   * @test
   * -# Test getAgentRev getter
   */
  public function testGetAgentRev()
  {
    $agent = new SuccessfulAgent(1, '1.0.0', 'Test Agent');
    $this->assertEquals('1.0.0', $agent->getAgentRev());
  }

  /**
   * @test
   * -# Test getAgentName getter
   */
  public function testGetAgentName()
  {
    $agent = new SuccessfulAgent(1, '1.0.0', 'Test Agent');
    $this->assertEquals('Test Agent', $agent->getAgentName());
  }

  ////// Setter Tests //////

  /**
   * @test
   * -# Test setAgentId setter
   */
  public function testSetAgentId()
  {
    $agent = new SuccessfulAgent(1, '1.0.0', 'Test Agent');
    $agent->setAgentId(2);
    $this->assertEquals(2, $agent->getAgentId());
  }

  /**
   * @test
   * -# Test setAgentRev setter
   */
  public function testSetAgentRev()
  {
    $agent = new SuccessfulAgent(1, '1.0.0', 'Test Agent');
    $agent->setAgentRev('2.0.0');
    $this->assertEquals('2.0.0', $agent->getAgentRev());
  }

  /**
   * @test
   * -# Test setAgentName setter
   */
  public function testSetAgentName()
  {
    $agent = new SuccessfulAgent(1, '1.0.0', 'Test Agent');
    $agent->setAgentName('New Agent');
    $this->assertEquals('New Agent', $agent->getAgentName());
  }

  /**
   * @test
   * -# Test getArray method with API version 1
   */
  public function testGetArrayV1()
  {
    $agent = new SuccessfulAgent(1, '1.0.0', 'Test Agent');
    
    $expectedArray = [
      'agent_id' => 1,
      'agent_rev' => '1.0.0',
      'agent_name' => 'Test Agent'
    ];
    
    $this->assertEquals($expectedArray, $agent->getArray(ApiVersion::V1));
  }

  /**
   * @test
   * -# Test getArray method with API version 2
   */
  public function testGetArrayV2()
  {
    $agent = new SuccessfulAgent(1, '1.0.0', 'Test Agent');
    
    $expectedArray = [
      'agentId' => 1,
      'agentRev' => '1.0.0',
      'agentName' => 'Test Agent'
    ];
    
    $this->assertEquals($expectedArray, $agent->getArray(ApiVersion::V2));
  }

  /**
   * @test
   * -# Test getJSON method with both API versions
   */
  public function testGetJSON()
  {
    $agent = new SuccessfulAgent(1, '1.0.0', 'Test Agent');
    
    $expectedArrayV1 = [
      'agent_id' => 1,
      'agent_rev' => '1.0.0',
      'agent_name' => 'Test Agent'
    ];
    
    $expectedArrayV2 = [
      'agentId' => 1,
      'agentRev' => '1.0.0',
      'agentName' => 'Test Agent'
    ];
    
    $this->assertEquals(json_encode($expectedArrayV1), $agent->getJSON(ApiVersion::V1));
    $this->assertEquals(json_encode($expectedArrayV2), $agent->getJSON(ApiVersion::V2));
  }
}