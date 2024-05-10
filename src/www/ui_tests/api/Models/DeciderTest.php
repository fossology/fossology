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

use Fossology\UI\Api\Models\Decider;
use Fossology\UI\Api\Models\ApiVersion;

/**
 * @class DeciderTest
 * @brief Tests for Decider model
 */
class DeciderTest extends \PHPUnit\Framework\TestCase
{
  /**
   * @test
   * -# Test for Decider::setUsingArray() when $version is V1
   * -# Create a test array and pass to setUsingArray() on test object
   * -# Check if the object is updated
   */
  public function testSetUsingArrayV1()
  {
    $this->testSetUsingArray(ApiVersion::V1);
  }

  /**
   * @test
   * -# Test for Decider::setUsingArray() when $version is V2
   * -# Create a test array and pass to setUsingArray() on test object
   * -# Check if the object is updated
   */
  public function testSetUsingArrayV2()
  {
    $this->testSetUsingArray(ApiVersion::V2);
  }

  /**
   * @param $version version to test
   * @return void
   * -# Test for Decider::setUsingArray() to check if the object is updated
   */
  private function testSetUsingArray($version)
  {
    if ($version == ApiVersion::V1) {
      $deciderArray = [
        "nomos_monk" => true,
        "bulk_reused" => false,
        "ojo_decider" => (1==1)
      ];
    } else {
      $deciderArray = [
        "nomosMonk" => true,
        "bulkReused" => false,
        "ojoDecider" => (1==1)
      ];
    }

    $expectedObject = new Decider(true, false, false, true);

    $actualObject = new Decider();
    $actualObject->setUsingArray($deciderArray, $version);

    $this->assertEquals($expectedObject, $actualObject);
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
    $this->testDataFormat(ApiVersion::V2);
  }

  /**
   * @param $version version to test
   * @return void
   * -# Test the data format returned by Decider::getArray($version) model
   */
  private function testDataFormat($version)
  {
    if ($version == ApiVersion::V1) {
      $expectedArray = [
        "nomos_monk"  => true,
        "bulk_reused" => false,
        "new_scanner" => false,
        "ojo_decider" => true
      ];
    } else {
      $expectedArray = [
        "nomosMonk"  => true,
        "bulkReused" => false,
        "newScanner" => false,
        "ojoDecider" => true
      ];
    }

    $actualObject = new Decider();
    $actualObject->setNomosMonk(true);
    $actualObject->setOjoDecider(true);

    $this->assertEquals($expectedArray, $actualObject->getArray($version));
  }
}
