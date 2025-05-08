<?php
/*
 SPDX-FileCopyrightText: © 2025 Harshit Gandhi <gandhiharshit716@gmail.com>
 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @file
 * @brief Tests for EditedLicense model
 */

namespace Fossology\UI\Api\Test\Models;

use Fossology\UI\Api\Models\EditedLicense;
use Fossology\UI\Api\Models\ApiVersion;
use PHPUnit\Framework\TestCase;

/**
 * @class EditedLicenseTest
 * @brief Tests for EditedLicense model
 */
class EditedLicenseTest extends TestCase
{
  /** @var array $sampleData Test data */
  private $id = 1;
  private $shortName = "GPL-2.0";
  private $count = 5;
  private $spdxId = "GPL-2.0-only";

  ////// Constructor Tests //////

  /**
   * Tests that the EditedLicense constructor initializes an instance correctly.
   *
   * @return void
   */
  public function testConstructor()
  {
    $license = new EditedLicense($this->id, $this->shortName, $this->count, $this->spdxId);
    $this->assertInstanceOf(EditedLicense::class, $license);
  }

  ////// Getter Tests //////

  /**
   * @test
   * -# Test getter for id
   */
  public function testGetId()
  {
    $license = new EditedLicense($this->id, $this->shortName, $this->count, $this->spdxId);
    $this->assertEquals($this->id, $license->getId());
  }

  /**
   * @test
   * -# Test getter for shortName
   */
  public function testGetShortName()
  {
    $license = new EditedLicense($this->id, $this->shortName, $this->count, $this->spdxId);
    $this->assertEquals($this->shortName, $license->getShortName());
  }

  /**
   * @test
   * -# Test getter for count
   */
  public function testGetCount()
  {
    $license = new EditedLicense($this->id, $this->shortName, $this->count, $this->spdxId);
    $this->assertEquals($this->count, $license->getCount());
  }

  /**
   * @test
   * -# Test getter for spdxId
   */
  public function testGetSpdxId()
  {
    $license = new EditedLicense($this->id, $this->shortName, $this->count, $this->spdxId);
    $this->assertEquals($this->spdxId, $license->getSpdxId());
  }

  ////// Setter Tests //////

  /**
   * @test
   * -# Test setter for id
   */
  public function testSetId()
  {
    $license = new EditedLicense($this->id, $this->shortName, $this->count, $this->spdxId);
    $newId = 2;
    $license->setId($newId);
    $this->assertEquals($newId, $license->getId());
  }

  /**
   * @test
   * -# Test setter for shortName
   */
  public function testSetShortName()
  {
    $license = new EditedLicense($this->id, $this->shortName, $this->count, $this->spdxId);
    $newShortName = "MIT";
    $license->setShortName($newShortName);
    $this->assertEquals($newShortName, $license->getShortName());
  }

  /**
   * @test
   * -# Test setter for count
   */
  public function testSetCount()
  {
    $license = new EditedLicense($this->id, $this->shortName, $this->count, $this->spdxId);
    $newCount = 10;
    $license->setCount($newCount);
    $this->assertEquals($newCount, $license->getCount());
  }

  /**
   * @test
   * -# Test setter for spdxId
   */
  public function testSetSpdxId()
  {
    $license = new EditedLicense($this->id, $this->shortName, $this->count, $this->spdxId);
    $newSpdxId = "MIT";
    $license->setSpdxId($newSpdxId);
    $this->assertEquals($newSpdxId, $license->getSpdxId());
  }

  /**
   * @test
   * -# Test setter with null values
   */
  public function testSettersWithNullValues()
  {
    $license = new EditedLicense($this->id, $this->shortName, $this->count, $this->spdxId);
    
    $license->setId(null);
    $this->assertNull($license->getId());
    
    $license->setShortName(null);
    $this->assertNull($license->getShortName());
    
    $license->setCount(null);
    $this->assertNull($license->getCount());
    
    $license->setSpdxId(null);
    $this->assertNull($license->getSpdxId());
  }

  /**
   * @test
   * -# Test setters with type conversion
   */
  public function testSettersWithTypeConversion()
  {
    $license = new EditedLicense($this->id, $this->shortName, $this->count, $this->spdxId);
    
    $license->setId("100");
    $this->assertEquals(100, $license->getId());
    
    $license->setCount("200");
    $this->assertEquals(200, $license->getCount());
    
    $license->setShortName(123);
    $this->assertEquals("123", $license->getShortName());
    
    $license->setSpdxId(456);
    $this->assertEquals("456", $license->getSpdxId());
  }

  ////// API Version Tests //////

  /**
   * @test
   * -# Test the data format returned by EditedLicense::getArray() for V1
   */
  public function testGetArrayV1()
  {
    $license = new EditedLicense($this->id, $this->shortName, $this->count, $this->spdxId);
    
    $expectedArray = [
      'id' => $this->id,
      'shortName' => $this->shortName,
      'count' => $this->count,
      'spdx_id' => $this->spdxId
    ];

    $this->assertEquals($expectedArray, $license->getArray(ApiVersion::V1));
  }

  /**
   * @test
   * -# Test the data format returned by EditedLicense::getArray() for V2
   */
  public function testGetArrayV2()
  {
    $license = new EditedLicense($this->id, $this->shortName, $this->count, $this->spdxId);
    
    $expectedArray = [
      'id' => $this->id,
      'shortName' => $this->shortName,
      'count' => $this->count,
      'spdxId' => $this->spdxId
    ];

    $this->assertEquals($expectedArray, $license->getArray(ApiVersion::V2));
  }

  /**
   * @test
   * -# Test JSON encoding through getJSON() method for V1
   */
  public function testGetJsonV1()
  {
    $license = new EditedLicense($this->id, $this->shortName, $this->count, $this->spdxId);
    
    $resultV1 = $license->getJSON(ApiVersion::V1);
    $this->assertIsString($resultV1);
    $this->assertJson($resultV1);
    $this->assertEquals($license->getArray(ApiVersion::V1), json_decode($resultV1, true));
  }

  /**
   * @test
   * -# Test JSON encoding through getJSON() method for V2
   */
  public function testGetJsonV2()
  {
    $license = new EditedLicense($this->id, $this->shortName, $this->count, $this->spdxId);

    $resultV2 = $license->getJSON(ApiVersion::V2);
    $this->assertIsString($resultV2);
    $this->assertJson($resultV2);
    $this->assertEquals($license->getArray(ApiVersion::V2), json_decode($resultV2, true));
  }
}