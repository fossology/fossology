<?php
/***************************************************************
 * Copyright (C) 2020 Siemens AG
 * Author: Gaurav Mishra <mishra.gaurav@siemens.com>
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 ***************************************************************/
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
