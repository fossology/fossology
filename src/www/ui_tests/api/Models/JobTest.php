<?php
/*
 SPDX-FileCopyrightText: © 2020 Siemens AG
 Author: Gaurav Mishra <mishra.gaurav@siemens.com>

 SPDX-License-Identifier: GPL-2.0-only
*/
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
