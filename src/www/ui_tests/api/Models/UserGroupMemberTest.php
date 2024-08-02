<?php
/*
 SPDX-FileCopyrightText: © 2024 Valens Niyonsenga <valensniyonsenga2003@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @dir
 * @brief Unit test cases for API models
 * @file
 * @brief Tests for UserGroupMember
 */

namespace Fossology\UI\Api\Test\Models;

use Fossology\UI\Api\Models\ApiVersion;
use Fossology\UI\Api\Models\User;
use Fossology\UI\Api\Models\UserGroupMember;
use PHPUnit\Framework\TestCase;
use Mockery as M;

/**
 * @class UserGroupMemberTest
 * @brief Tests for UserGroupMember model
 */
class UserGroupMemberTest extends TestCase
{
  private $container;
  private $restHelper;

  /**
   * @brief Setup test objects
   * @see PHPUnit_Framework_TestCase::setUp()
   */
  protected function setUp(): void
  {
    global $container;
    $container = M::mock('ContainerBuilder');
    $this->restHelper = M::mock(RestHelper::class);
    $this->container = M::mock(ContainerInterface::class);
    $container->shouldReceive('get')->withArgs(['helper.restHelper'])->andReturn($this->restHelper);
  }

  /**
   * Provides test data and instances of the UserGroupMember class.
   * @return array An associative array containing test data and UserGroupMember objects.
   */
  private function getUserGroupMemberInfo($version = ApiVersion::V2)
  {
    $user = new User(2, "fossy", "admin users", "fossy@gmail.com", 2, 4, true, null);
    $memberInfo = null;
    if ($version == ApiVersion::V2) {
      $memberInfo =  [
        'user' => $user->getArray($version),
        'groupPerm' => 1
      ];
    } else {
      $memberInfo = [
        'user' => $user->getArray($version),
        'group_perm' => 1
      ];
    }
    return [
      'memberInfo' => $memberInfo,
      'obj' => new UserGroupMember($user, 1)
    ];
  }

  /**
   * @test
   * Test the data format returned by UserGroupMember::getArray($version) method when $version is V1
   */
  public function testDataFormatV1()
  {
    $this->testDataFormat(ApiVersion::V1);
  }

  /**
   * @test
   * Test the data format returned by UserGroupMember::getArray($version) method when $version is V2
   */
  public function testDataFormatV2()
  {
    $this->testDataFormat();
  }

  private function testDataFormat($version = ApiVersion::V2)
  {
    $expectedArray = $this->getUserGroupMemberInfo($version)['memberInfo'];
    $userGroupMember = $this->getUserGroupMemberInfo($version)['obj'];
    $this->assertEquals($expectedArray, $userGroupMember->getArray($version));
  }

  /**
   * @test
   * Test the UserGroupMember::getJSON() method
   */
  public function testGetJSON()
  {
    $userGroupMember = $this->getUserGroupMemberInfo()['obj'];
    $expectedJson = json_encode($this->getUserGroupMemberInfo()['memberInfo']);
    $this->assertJsonStringEqualsJsonString($expectedJson, $userGroupMember->getJSON());
  }

  /**
   * @test
   * Test the UserGroupMember::getUser() method
   */
  public function testGetUser()
  {
    $user = new User(2, "fossy", "admin users", "fossy@gmail.com", 2, 4, true, null);
    $userGroupMember = new UserGroupMember($user, 1);
    $this->assertEquals($user, $userGroupMember->getUser());
  }

  /**
   * @test
   * Test the UserGroupMember::getGroupPerm() method
   */
  public function testGetGroupPerm()
  {
    $userGroupMember = $this->getUserGroupMemberInfo()['obj'];
    $this->assertEquals(1, $userGroupMember->getGroupPerm());
  }

  /**
   * @test
   * Test the UserGroupMember::setUser() method
   */
  public function testSetUser()
  {
    $user = new User(3, "newuser", "users", "newuser@gmail.com", 3, 5, false, null);
    $userGroupMember = $this->getUserGroupMemberInfo()['obj'];
    $userGroupMember->setUser($user);
    $this->assertEquals($user, $userGroupMember->getUser());
  }

  /**
   * @test
   * Test the UserGroupMember::setGroupPerm() method
   */
  public function testSetGroupPerm()
  {
    $userGroupMember = $this->getUserGroupMemberInfo()['obj'];
    $userGroupMember->setGroupPerm(2);
    $this->assertEquals(2, $userGroupMember->getGroupPerm());
  }
}
