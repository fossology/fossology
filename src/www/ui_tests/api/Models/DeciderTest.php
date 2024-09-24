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
use PHPUnit\Framework\TestCase;

/**
 * @class DeciderTest
 * @brief Tests for Decider model
 */
class DeciderTest extends TestCase
{
  /**
   * Provides test data and an instance of the Decider class.
   *
   * @param string $version The API version to use for formatting.
   * @return array An associative array containing:
   *  - `expectedArray`: The expected array structure.
   *  - `obj`: The instance of Decider being tested.
   */
  private function getDeciderInfo($version = ApiVersion::V1)
  {
    $nomosMonk = true;
    $bulkReused = false;
    $newScanner = false;
    $ojoDecider = true;

    if ($version == ApiVersion::V1) {
      $expectedArray = [
        "nomos_monk"  => $nomosMonk,
        "bulk_reused" => $bulkReused,
        "new_scanner" => $newScanner,
        "ojo_decider" => $ojoDecider
      ];
    } else {
      $expectedArray = [
        "nomosMonk"  => $nomosMonk,
        "bulkReused" => $bulkReused,
        "newScanner" => $newScanner,
        "ojoDecider" => $ojoDecider
      ];
    }

    $obj = new Decider($nomosMonk, $bulkReused, $newScanner, $ojoDecider);
    return [
      'expectedArray' => $expectedArray,
      'obj' => $obj
    ];
  }

  /**
   * @test
   * -# Test data format returned by Decider::getArray($version) when API version is V1
   */
  public function testDataFormatV1()
  {
    $this->testDataFormat(ApiVersion::V1);
  }

  /**
   * @test
   * -# Test data format returned by Decider::getArray($version) when API version is V2
   */
  public function testDataFormatV2()
  {
    $this->testDataFormat(ApiVersion::V2);
  }

  /**
   * -# Test the data format returned by Decider::getArray($version) model.
   *
   * @param string $version The API version to test.
   */
  private function testDataFormat($version)
  {
    $info = $this->getDeciderInfo($version);
    $expectedArray = $info['expectedArray'];
    $decider = $info['obj'];
    $this->assertEquals($expectedArray, $decider->getArray($version));
  }

  /**
   * @test
   * -# Test for Decider::setUsingArray() when $version is V1.
   */
  public function testSetUsingArrayV1()
  {
    $this->testSetUsingArray(ApiVersion::V1);
  }

  /**
   * @test
   * -# Test for Decider::setUsingArray() when $version is V2.
   */
  public function testSetUsingArrayV2()
  {
    $this->testSetUsingArray(ApiVersion::V2);
  }

  /**
   * Test Decider::setUsingArray() to check if the object is updated.
   *
   * @param string $version The API version to test.
   */
  private function testSetUsingArray($version)
  {
    $info = $this->getDeciderInfo($version);
    $expectedObject = $info['obj'];

    $deciderArray = $info['expectedArray'];
    $actualObject = new Decider();
    $actualObject->setUsingArray($deciderArray, $version);

    $this->assertEquals($expectedObject, $actualObject);
  }

  /**
   * @test
   * -# Test getters for Decider properties.
   */
  public function testGetters()
  {
    $decider = new Decider(true, false, false, true);

    $this->assertTrue($decider->getNomosMonk());
    $this->assertFalse($decider->getBulkReused());
    $this->assertFalse($decider->getNewScanner());
    $this->assertTrue($decider->getOjoDecider());
  }

  /**
   * @test
   * -# Test setters for Decider properties.
   */
  public function testSetters()
  {
    $decider = new Decider();

    $decider->setNomosMonk(true);
    $this->assertTrue($decider->getNomosMonk());

    $decider->setBulkReused(false);
    $this->assertFalse($decider->getBulkReused());

    $decider->setNewScanner(true);
    $this->assertTrue($decider->getNewScanner());

    $decider->setOjoDecider(false);
    $this->assertFalse($decider->getOjoDecider());
  }
}
