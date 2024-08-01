<?php
/*
 SPDX-FileCopyrightText: © 2020 Siemens AG
 Author: Gaurav Mishra <mishra.gaurav@siemens.com>

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @file
 * @brief Tests for Decider
 */

namespace Fossology\UI\Api\Test\Models;
use Fossology\UI\Api\Models\ApiVersion;
use Fossology\UI\Api\Models\Decider;

/**
 * @class DeciderTest
 * @brief Tests for Decider model
 */
class DeciderTest extends \PHPUnit\Framework\TestCase
{

  private $decider;

  /**
   * @brief Setup method to initialize the Decider object
   */
  public function setUp(): void
  {
    $this->decider = new Decider();
  }

  /**
   * @test
   * @brief Test for Decider::setUsingArray()
   * - Create a test array and pass to setUsingArray() on test object
   * - Check if the object is updated
   */
  public function testSetUsingArray()
  {
    $deciderArray = [
      "nomos_monk" => true,
      "bulk_reused" => false,
      "ojo_decider" => (1==1)
    ];

    $expectedObject = new Decider(true, false, false, true);

    $actualObject = new Decider();
    $actualObject->setUsingArray($deciderArray);

    $this->assertEquals($expectedObject, $actualObject);
  }

  /**
   * Provides test data and an instance of the Decider class.
   * @return array An associative array containing test data and an Agent object.
   */
  public function getDeciderInfo($version=ApiVersion::V2)
  {
    if($version==ApiVersion::V1){
      $deciderInfo = [
        "nomos_monk"  => true,
        "bulk_reused" => false,
        "new_scanner" => false,
        "ojo_decider" => true
      ];
    }else{
      $deciderInfo = [
        "nomosMonk"  => true,
        "bulkReused" => false,
        "newScanner" => false,
        "ojoDecider" => true
      ];
    }
    return [
      'deciderInfo' =>  $deciderInfo,
    ];
  }

  /**
   * @test
   * -# Test the data format returned by Decider::getArray($version) model when $version is V1
   * -# Create expected array and update test object to match it
   * -# Get the array from object and match with expected array
   */
  public function testDataFormatV1()
  {
    $this->testDataFormat(ApiVersion::V1);
  }

  /**
   * @test
   * -# Test the data format returned by Decider::getArray($version) model when $version is V2
   * -# Create expected array and update test object to match it
   * -# Get the array from object and match with expected array
   */
  public function testDataFormatV2()
  {
    $this->testDataFormat();
  }

  /**
   * @test
   * @brief Test for Decider::getArray()
   * - Create expected array and update test object to match it
   * - Check if the response of getArray() on test object matches expected array
   */
  private function testDataFormat($version=ApiVersion::V2)
  {
    $expectedArray = $this->getDeciderInfo($version)['deciderInfo'];
    $actualObject = new Decider();
    $actualObject->setNomosMonk(true);
    $actualObject->setOjoDecider(true);
    $this->assertEquals($expectedArray, $actualObject->getArray($version));
  }

  /**
   * @test
   * @brief Test for Decider::setNomosMonk()
   * - Set nomos_monk to true and verify the value is correctly set
   */
  public function testSetNomosMonk()
  {
    $this->decider->setNomosMonk(true);
    $this->assertTrue($this->decider->getNomosMonk());
  }

  /**
   * @test
   * @brief Test for Decider::setBulkReused()
   * - Set bulk_reused to true and verify the value is correctly set
   */
  public function testSetBulkReused()
  {
    $this->decider->setBulkReused(true);
    $this->assertTrue($this->decider->getBulkReused());
  }

  /**
   * @test
   * @brief Test for Decider::setNewScanner()
   * - Set new_scanner to true and verify the value is correctly set
   */
  public function testSetNewScanner()
  {
    $this->decider->setNewScanner(true);
    $this->assertTrue($this->decider->getNewScanner());
  }

  /**
   * @test
   * @brief Test for Decider::setOjoDecider()
   * - Set ojo_decider to true and verify the value is correctly set
   */
  public function testSetOjoDecider()
  {
    $this->decider->setOjoDecider(true);
    $this->assertTrue($this->decider->getOjoDecider());
  }
}
