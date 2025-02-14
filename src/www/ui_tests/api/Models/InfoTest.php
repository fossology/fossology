<?php
/*
 SPDX-FileCopyrightText: © 2020 Siemens AG
 Author: Gaurav Mishra <mishra.gaurav@siemens.com>

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @file
 * @brief Tests for Info model
 */

namespace Fossology\UI\Api\Test\Models;

use Fossology\UI\Api\Models\Info;
use Fossology\UI\Api\Models\InfoType;

/**
 * @class InfoTest
 * @brief Tests for Info model
 */
class InfoTest extends \PHPUnit\Framework\TestCase
{
  ////// Constructor Tests //////

  /**
   * Tests that the Info constructor initializes an instance correctly.
   *
   * @return void
   */
  public function testConstructor()
  {
    $info = new Info(200, "All good", InfoType::INFO);
    $this->assertInstanceOf(Info::class, $info);
  }

  /**
   * @test
   * -# Test the data format returned by Info::getArray() model
   */
  public function testDataFormat()
  {
    $expectedInfo = [
      'code' => 200,
      'message' => "All good",
      'type' => "INFO"
    ];
    $expectedError = [
      'code' => 500,
      'message' => "Something bad",
      'type' => "ERROR"
    ];

    $actualInfo = new Info(200, "All good", InfoType::INFO);
    $actualError = new Info(500, "Something bad", InfoType::ERROR);

    $this->assertEquals($expectedInfo, $actualInfo->getArray());
    $this->assertEquals($expectedError, $actualError->getArray());
  }
}
