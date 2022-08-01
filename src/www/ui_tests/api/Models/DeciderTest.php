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
