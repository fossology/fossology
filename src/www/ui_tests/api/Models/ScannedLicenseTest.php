<?php
/*
 SPDX-FileCopyrightText: © 2025 Harshit Gandhi <gandhiharshit716@gmail.com>
 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @file
 * @brief Tests for ScannedLicense
 */
namespace Fossology\UI\Api\Test\Models;

use Fossology\UI\Api\Models\ScannedLicense;
use Fossology\UI\Api\Models\ApiVersion;

use PHPUnit\Framework\TestCase;

/**
 * @class ScannedLicenseTest
 * @brief Tests for ScannedLicense model
 */
class ScannedLicenseTest extends TestCase
{
  ////// Constructor Tests //////

  /**
   * Tests that the ScannedLicense constructor initializes an instance correctly.
   *
   * @return void
   */
  public function testConstructor()
  {
    $testData = [
      'id' => 1,
      'shortname' => 'GPL-2.0',
      'occurence' => 5,
      'unique' => 2,
      'spdxName' => 'GPL-2.0-only'
    ];

    $license = new ScannedLicense(
      $testData['id'],
      $testData['shortname'],
      $testData['occurence'],
      $testData['unique'],
      $testData['spdxName']
    );
    
    $this->assertInstanceOf(ScannedLicense::class, $license);
  }

  ////// Getter Tests //////

  /**
   * @test
   * -# Test getId getter
   */
  public function testGetId()
  {
    $license = new ScannedLicense(1, 'GPL-2.0', 5, 2, 'GPL-2.0-only');
    $this->assertEquals(1, $license->getId());
  }

  /**
   * @test
   * -# Test getShortname getter
   */
  public function testGetShortname()
  {
    $license = new ScannedLicense(1, 'GPL-2.0', 5, 2, 'GPL-2.0-only');
    $this->assertEquals('GPL-2.0', $license->getShortname());
  }

  /**
   * @test
   * -# Test getOccurence getter
   */
  public function testGetOccurence()
  {
    $license = new ScannedLicense(1, 'GPL-2.0', 5, 2, 'GPL-2.0-only');
    $this->assertEquals(5, $license->getOccurence());
  }

  /**
   * @test
   * -# Test getUnique getter
   */
  public function testGetUnique()
  {
    $license = new ScannedLicense(1, 'GPL-2.0', 5, 2, 'GPL-2.0-only');
    $this->assertEquals(2, $license->getUnique());
  }

  /**
   * @test
   * -# Test getSpdxName getter
   */
  public function testGetSpdxName()
  {
    $license = new ScannedLicense(1, 'GPL-2.0', 5, 2, 'GPL-2.0-only');
    $this->assertEquals('GPL-2.0-only', $license->getSpdxName());
  }

  ////// Setter Tests //////

  /**
   * @test
   * -# Test setId setter
   */
  public function testSetId()
  {
    $license = new ScannedLicense(1, 'GPL-2.0', 5, 2, 'GPL-2.0-only');
    $license->setId(2);
    $this->assertEquals(2, $license->getId());
  }

  /**
   * @test
   * -# Test setShortname setter
   */
  public function testSetShortname()
  {
    $license = new ScannedLicense(1, 'GPL-2.0', 5, 2, 'GPL-2.0-only');
    $license->setShortname('MIT');
    $this->assertEquals('MIT', $license->getShortname());
  }

  /**
   * @test
   * -# Test setOccurence setter
   */
  public function testSetOccurence()
  {
    $license = new ScannedLicense(1, 'GPL-2.0', 5, 2, 'GPL-2.0-only');
    $license->setOccurence(10);
    $this->assertEquals(10, $license->getOccurence());
  }

  /**
   * @test
   * -# Test setUnique setter
   */
  public function testSetUnique()
  {
    $license = new ScannedLicense(1, 'GPL-2.0', 5, 2, 'GPL-2.0-only');
    $license->setUnique(4);
    $this->assertEquals(4, $license->getUnique());
  }

  /**
   * @test
   * -# Test setSpdxName setter
   */
  public function testSetSpdxName()
  {
    $license = new ScannedLicense(1, 'GPL-2.0', 5, 2, 'GPL-2.0-only');
    $license->setSpdxName('MIT');
    $this->assertEquals('MIT', $license->getSpdxName());
  }

  /**
   * @test
   * -# Test getArray method with API version 1
   */
  public function testGetArrayV1()
  {
    $license = new ScannedLicense(1, 'GPL-2.0', 5, 2, 'GPL-2.0-only');
    
    $expectedArray = [
      'id' => 1,
      'shortname' => 'GPL-2.0',
      'occurence' => 5,
      'unique' => 2,
      'spdxName' => 'GPL-2.0-only'
    ];
    
    $this->assertEquals($expectedArray, $license->getArray(ApiVersion::V1));
  }

  /**
   * @test
   * -# Test getArray method with API version 2
   */
  public function testGetArrayV2()
  {
    $license = new ScannedLicense(1, 'GPL-2.0', 5, 2, 'GPL-2.0-only');
    
    $expectedArray = [
      'id' => 1,
      'shortName' => 'GPL-2.0',
      'occurence' => 5,
      'unique' => 2,
      'spdxName' => 'GPL-2.0-only'
    ];
    
    $this->assertEquals($expectedArray, $license->getArray(ApiVersion::V2));
  }

  /**
   * @test
   * -# Test getJSON method
   */
  public function testGetJSON()
  {
    $license = new ScannedLicense(1, 'GPL-2.0', 5, 2, 'GPL-2.0-only');
    
    $expectedArrayV1 = [
      'id' => 1,
      'shortname' => 'GPL-2.0',
      'occurence' => 5,
      'unique' => 2,
      'spdxName' => 'GPL-2.0-only'
    ];
    
    $expectedArrayV2 = [
      'id' => 1,
      'shortName' => 'GPL-2.0',
      'occurence' => 5,
      'unique' => 2,
      'spdxName' => 'GPL-2.0-only'
    ];
    
    $this->assertEquals(json_encode($expectedArrayV1), $license->getJSON(ApiVersion::V1));
    $this->assertEquals(json_encode($expectedArrayV2), $license->getJSON(ApiVersion::V2));
  }
}