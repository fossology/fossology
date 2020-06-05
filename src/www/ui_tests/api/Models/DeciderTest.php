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
 * @brief Tests for Decider
 */

namespace Fossology\UI\Api\Test\Models;

use Fossology\UI\Api\Models\Decider;

/**
 * @class DeciderTest
 * @brief Tests for Decider model
 */
class DeciderTest extends \PHPUnit\Framework\TestCase
{
  /**
   * @test
   * -# Test for Decider::setUsingArray()
   * -# Create a test array and pass to setUsingArray() on test object
   * -# Check if the object is updated
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
   * @test
   * -# Test for Decider::getArray()
   * -# Create expected array and update test object to match it
   * -# Check if the response of getArray() on test object matches expected
   *    array
   */
  public function testDataFormat()
  {
    $expectedArray = [
      "nomos_monk"  => true,
      "bulk_reused" => false,
      "new_scanner" => false,
      "ojo_decider" => true
    ];

    $actualObject = new Decider();
    $actualObject->setNomosMonk(true);
    $actualObject->setOjoDecider(true);

    $this->assertEquals($expectedArray, $actualObject->getArray());
  }
}
