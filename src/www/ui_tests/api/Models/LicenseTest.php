<?php
/*
 SPDX-FileCopyrightText: © 2021 Siemens AG
 Author: Gaurav Mishra <mishra.gaurav@siemens.com>

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @file
 * @brief Tests for License model
 */

namespace Fossology\UI\Api\Test\Models;

use Fossology\UI\Api\Models\License;

/**
 * @class LicenseTest
 * @brief Test for License model
 */
class LicenseTest extends \PHPUnit\Framework\TestCase
{
  ////// Constructor Tests //////

  /**
   * Tests that the License constructor initializes an instance correctly.
   *
   * @return void
   */
  public function testConstructor()
  {
    $license = new License(22, "MIT", "MIT License",
      "MIT License Copyright (c) <year> <copyright holders> ...",
      "https://opensource.org/licenses/MIT", [], 3, false);
    $this->assertInstanceOf(License::class, $license);
  }

  /**
   * @test
   * -# Test data model returned by License::getArray() is correct
   */
  public function testDataFormat()
  {
    $expectedLicense = [
      'id'        => 22,
      'shortName' => "MIT",
      'fullName'  => "MIT License",
      'text'      => "MIT License Copyright (c) <year> <copyright holders> ...",
      'url'       => "https://opensource.org/licenses/MIT",
      'risk'      => 3,
      'isCandidate' => false,
      'obligations' => []
    ];

    $actualLicense = new License(22, "MIT", "MIT License",
      "MIT License Copyright (c) <year> <copyright holders> ...",
      "https://opensource.org/licenses/MIT", [], 3, false);

    $this->assertEquals($expectedLicense, $actualLicense->getArray());
  }

  /**
   * @test
   * -# Test data model returned by License::getArray() is correct when there is
   *    no obligation
   */
  public function testDataFormatNoObligation()
  {
    $expectedLicense = [
      'id'        => 22,
      'shortName' => "MIT",
      'fullName'  => "MIT License",
      'text'      => "MIT License Copyright (c) <year> <copyright holders> ...",
      'url'       => "https://opensource.org/licenses/MIT",
      'risk'      => 3,
      'isCandidate' => true
    ];

    $actualLicense = new License(22, "MIT", "MIT License",
      "MIT License Copyright (c) <year> <copyright holders> ...",
      "https://opensource.org/licenses/MIT", null, 3, true);

    $this->assertEquals($expectedLicense, $actualLicense->getArray());
  }

  /**
   * @test
   * -# Test parser License::parseFromArray()
   * -# Create a valid license input and check the function.
   * -# Create an input with invalid keys and check if function returns error.
   */
  public function testArrayParser()
  {
    $inputLicense = [
      'shortName' => "MIT",
      'fullName'  => "MIT License",
      'text'      => "MIT License Copyright (c) <year> <copyright holders> ...",
      'url'       => "https://opensource.org/licenses/MIT"
    ];

    $sampleLicense = new License(0, "MIT", "MIT License",
      "MIT License Copyright (c) <year> <copyright holders> ...",
      "https://opensource.org/licenses/MIT");

    $actualLicense = License::parseFromArray($inputLicense);

    $this->assertEquals($sampleLicense->getArray(), $actualLicense->getArray());

    $bogusInput = array_merge($inputLicense, ["invalidKey" => 123]);
    $bogusLicense = License::parseFromArray($bogusInput);
    $this->assertEquals(-1, $bogusLicense);
  }
}
