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

use Fossology\Lib\Dao\UserDao;
use Fossology\UI\Api\Models\ApiVersion;
use Fossology\UI\Api\Models\Job;
use Fossology\UI\Api\Models\JobQueue;
use Mockery as M;

/**
 * @class JobTest
 * @brief Test for Job model
 */
class JobTest extends \PHPUnit\Framework\TestCase
{

  /**
   * @test
   * -# Test data model returned by Job::getArray($version) when API version is V1
   */
  public function testDataFormatV1()
  {
    $this->testDataFormat(ApiVersion::V1);
  }

  /**
   * @test
   * -# Test data model returned by Job::getArray($version) when API version is V2
   */
  public function testDataFormatV2()
  {
    $this->testDataFormat(ApiVersion::V2);
  }

  /**
   * -# Test the data format returned by Job::getArray($version) model 
   */
  private function testDataFormat($version)
  {
    $jobQueue = new JobQueue(44, 'readmeoss', '2024-07-03 20:41:49', '2024-07-03 20:41:50',
      'Completed', 0, null, [], 0, true, false, true,
      ['text' => 'ReadMeOss', 'link' => 'http://localhost/repo/api/v1/report/16']);
    if ($version == ApiVersion::V2){
      $expectedStatus = [
        'id'        => 22,
        'name'      => 'ojo',
        'queueDate' => '01-01-2020',
        'uploadId'  => 4,
        'userName'  => "fossy",
        'groupName' => "fossy",
        'eta'       => 3,
        'status'    => 'Processing',
        'jobQueue'  => $jobQueue->getArray()
      ];
      global $container;
      $userDao = M::mock(UserDao::class);
      $container = M::mock('ContainerBuilder');
      $container->shouldReceive('get')->withArgs(array(
        "dao.user"))->andReturn($userDao);
      $userDao->shouldReceive("getUserName")->with(2)->andReturn("fossy");
      $userDao->shouldReceive("getGroupNameById")->with(2)->andReturn("fossy");
    } else {
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
    }

    $actualJob = new Job(22, 'ojo', '01-01-2020', 4, 2, 2, 3, 'Processing', $jobQueue->getArray());

    $this->assertEquals($expectedStatus, $actualJob->getArray($version));
  }
}
