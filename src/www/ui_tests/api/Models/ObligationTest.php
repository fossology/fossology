<?php
/*
 SPDX-FileCopyrightText: © 2021 Siemens AG
 Author: Gaurav Mishra <mishra.gaurav@siemens.com>

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @file
 * @brief Tests for Obligation model
 */

namespace Fossology\UI\Api\Test\Models;

use Fossology\UI\Api\Models\Obligation;

/**
 * @class ObligationTest
 * @brief Test for Obligation model
 */
class ObligationTest extends \PHPUnit\Framework\TestCase
{
  /**
   * @test
   * -# Test data model returned by Obligation::getArray() is correct
   */
  public function testDataFormat()
  {
    $expectedObligation = [
      'id'             => 123,
      'topic'          => 'My obligation',
      'type'           => 'Obligation',
      'text'           => 'This should represent some valid obligation text.',
      'classification' => 'yellow',
      'comment'        => ""
    ];

    $actualObligation = new Obligation("123", 'My obligation', 'Obligation',
      'This should represent some valid obligation text.', 'yellow');

    $this->assertEquals($expectedObligation, $actualObligation->getArray());
  }
}
