<?php
/*
 SPDX-FileCopyrightText: © 2025 Harshit Gandhi <gandhiharshit716@gmail.com>
 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @file
 * @brief Tests for AdminAcknowledgement model
 */

namespace Fossology\UI\Api\Test\Models;

use Fossology\UI\Api\Models\AdminAcknowledgement;
use Fossology\UI\Api\Models\ApiVersion;

use PHPUnit\Framework\TestCase;

/**
 * @class AdminAcknowledgementTest
 * @brief Tests for AdminAcknowledgement model
 */
class AdminAcknowledgementTest extends TestCase
{
  ////// Constructor Tests //////

  /**
   * Tests that the AdminAcknowledgement constructor initializes an instance correctly.
   *
   * @return void
   */
  public function testConstructor()
  {
    $id = 1;
    $name = 'Admin Approval';
    $acknowledgement = 'Acknowledged by admin';
    $isEnabled = true;

    $adminAcknowledgement = new AdminAcknowledgement($id, $name, $acknowledgement, $isEnabled);
    $this->assertInstanceOf(AdminAcknowledgement::class, $adminAcknowledgement);
  }

  /**
   * @test
   * -# Test the data format returned by AdminAcknowledgement::getArray() for API V1
   */
  public function testDataFormatV1()
  {
    $expectedArray = [
      'id' => 1,
      'name' => "Test Acknowledgement",
      'acknowledgement' => "Test acknowledgement text",
      'is_enabled' => true
    ];

    $adminAck = new AdminAcknowledgement(
      $expectedArray['id'],
      $expectedArray['name'],
      $expectedArray['acknowledgement'],
      $expectedArray['is_enabled']
    );

    $this->assertEquals($expectedArray, $adminAck->getArray(ApiVersion::V1));
  }

  /**
   * @test
   * -# Test the data format returned by AdminAcknowledgement::getArray() for API V2
   */
  public function testDataFormatV2()
  {
    $expectedArray = [
      'id' => 1,
      'name' => "Test Acknowledgement",
      'acknowledgement' => "Test acknowledgement text",
      'isEnabled' => true
    ];

    $adminAck = new AdminAcknowledgement(
      $expectedArray['id'],
      $expectedArray['name'],
      $expectedArray['acknowledgement'],
      $expectedArray['isEnabled']
    );

    $this->assertEquals($expectedArray, $adminAck->getArray(ApiVersion::V2));
  }

  /**
   * @test
   * -# Test JSON encoding through getJSON() method for API V1
   */
  public function testGetJsonV1()
  {
    $adminAck = new AdminAcknowledgement(1, "Test", "Test text", true);
    $expectedJson = json_encode([
      'id' => 1,
      'name' => "Test",
      'acknowledgement' => "Test text",
      'is_enabled' => true
    ]);

    $this->assertEquals($expectedJson, $adminAck->getJSON(ApiVersion::V1));
  }

  /**
   * @test
   * -# Test JSON encoding through getJSON() method for API V2
   */
  public function testGetJsonV2()
  {
    $adminAck = new AdminAcknowledgement(1, "Test", "Test text", true);
    $expectedJson = json_encode([
      'id' => 1,
      'name' => "Test",
      'acknowledgement' => "Test text",
      'isEnabled' => true
    ]);

    $this->assertEquals($expectedJson, $adminAck->getJSON(ApiVersion::V2));
  }

  ////// Getter Tests //////

  /**
   * @test
   * -# Test getter for id
   */
  public function testGetId()
  {
    $id = 1;
    $adminAck = new AdminAcknowledgement($id, "Test", "Test Text", true);
    $this->assertEquals($id, $adminAck->getId());
  }

  /**
   * @test
   * -# Test getter for name
   */
  public function testGetName()
  {
    $name = "Test Name";
    $adminAck = new AdminAcknowledgement(1, $name, "Test Text", true);
    $this->assertEquals($name, $adminAck->getName());
  }

  /**
   * @test
   * -# Test getter for acknowledgement
   */
  public function testGetAcknowledgement()
  {
    $acknowledgement = "Test Acknowledgement Text";
    $adminAck = new AdminAcknowledgement(1, "Test", $acknowledgement, true);
    $this->assertEquals($acknowledgement, $adminAck->getAcknowledgement());
  }

  /**
   * @test
   * -# Test getter for isEnabled
   */
  public function testGetIsEnabled()
  {
    $isEnabled = true;
    $adminAck = new AdminAcknowledgement(1, "Test", "Test Text", $isEnabled);
    $this->assertTrue($adminAck->getIsEnabled());
  }

  ////// Setter Tests //////

  /**
   * @test
   * -# Test setter for id
   */
  public function testSetId()
  {
    $adminAck = new AdminAcknowledgement(1, "Test", "Test Text", true);
    $newId = 2;
    $adminAck->setId($newId);
    $this->assertEquals($newId, $adminAck->getId());
  }

  /**
   * @test
   * -# Test setter for name
   */
  public function testSetName()
  {
    $adminAck = new AdminAcknowledgement(1, "Test", "Test Text", true);
    $newName = "Updated Name";
    $adminAck->setName($newName);
    $this->assertEquals($newName, $adminAck->getName());
  }

  /**
   * @test
   * -# Test setter for acknowledgement
   */
  public function testSetAcknowledgement()
  {
    $adminAck = new AdminAcknowledgement(1, "Test", "Test Text", true);
    $newAcknowledgement = "Updated Acknowledgement Text";
    $adminAck->setAcknowledgement($newAcknowledgement);
    $this->assertEquals($newAcknowledgement, $adminAck->getAcknowledgement());
  }

  /**
   * @test
   * -# Test setter for isEnabled
   */
  public function testSetIsEnabled()
  {
    $adminAck = new AdminAcknowledgement(1, "Test", "Test Text", true);
    $newIsEnabled = false;
    $adminAck->setIsEnabled($newIsEnabled);
    $this->assertFalse($adminAck->getIsEnabled());
  }
}