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
 * @brief Tests for Job model
 */

namespace Fossology\UI\Api\Test\Models;

use Fossology\UI\Api\Models\Job;

/**
 * @class JobTest
 * @brief Test for Job model
 */
class JobTest extends \PHPUnit\Framework\TestCase
{
  /**
   * @test
   * -# Test data model returned by Job::getArray() is correct
   */
  public function testDataFormat()
  {
    $expectedStatus = [
      'id'        => 22,
      'name'      => 'ojo',
      'queueDate' => '01-01-2020',
      'uploadId'  => 4,
      'userId'    => 2,
      'groupId'   => 2,
      'eta'       => 3,
      'status'    => 'Processing'
    ];

    $actualJob = new Job(22, 'ojo', '01-01-2020', 4, 2, 2, 3, 'Processing');

    $this->assertEquals($expectedStatus, $actualJob->getArray());
  }
}
