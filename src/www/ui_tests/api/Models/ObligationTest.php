<?php
/***************************************************************
 * Copyright (C) 2021 Siemens AG
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
