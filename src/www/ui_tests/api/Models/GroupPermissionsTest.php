<?php
/*
 SPDX-FileCopyrightText: © 2024 Valens Niyonsenga <valensniyonsenga2003@gmail.com>
 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @file
 * @brief Tests for GroupPermission model
 */
namespace Fossology\UI\Api\Test\Models;


use Fossology\UI\Api\Models\ApiVersion;
use Fossology\UI\Api\Models\GroupPermission;
use Monolog\Test\TestCase;

class GroupPermissionsTest extends TestCase
{
  ////// Constructor Tests //////

  /**
   * Tests that the GroupPermissions constructor initializes an instance correctly.
   *
   * @return void
   */
  public function testConstructor()
  {
    $groupPermission = new GroupPermission("GroupPerm", 4, "fossy");
    $this->assertInstanceOf(GroupPermission::class, $groupPermission);
  }

  /**
   * Provides test data and an instance of the GroupPermission class.
   *
   * @return array An associative array containing:
   *  - `expectedArray`: The expected array structure.
   *  - `obj`: The instance of Group being tested.
   */
  private function getGroupPermissionInfo($version = ApiVersion::V2)
  {
    $expectedArray = null;
    if ($version == ApiVersion::V1)
    {
      $expectedArray = [
        "perm" => "Group perm",
        "group_pk"  => 4,
        "group_name" => "fossy",
      ];
    } else {
      $expectedArray = [
        "perm" => "Group perm",
        "groupPk"  => 4,
        "groupName" => "fossy",
      ];
    }
    $obj = new GroupPermission("Group perm",4,"fossy");
    return [
      'expectedArray' => $expectedArray,
      'obj' => $obj
    ];
  }
  /**
   * @test
   * -# Test data model returned by GroupPermission::getArray($version) when API version is V1
   */
  public function testDataFormatV1()
  {
    $this->testDataFormat(ApiVersion::V1);
  }

  /**
   * @test
   * -# Test data model returned by GroupPermission::getArray($version) when API version is V2
   */
  public function testDataFormatV2()
  {
    $this->testDataFormat(ApiVersion::V2);
  }

  /**
   * -# Test the data format returned by GroupPermission::getArray($version) model
   */
  private function testDataFormat($version)
  {
    $info = $this->getGroupPermissionInfo($version);
    $expectedArray = $info['expectedArray'];
    $groupPermission = $info['obj'];
    $this->assertEquals($expectedArray, $groupPermission->getArray($version));
  }
  /**
   * Tests GroupPermission::getPerm() method.
   *
   * This method validates that the `getPerm` method returns the correct permission value.
   */
  public function testGetPerm()
  {
    $groupPermission = new GroupPermission("Group perm", "4", "fossy");
    $this->assertEquals("Group perm", $groupPermission->getPerm());
  }

  /**
   * Tests GroupPermission::setPerm() method.
   *
   * This method validates that the `setPerm` method correctly updates the permission value.
   */
  public function testSetPerm()
  {
    $groupPermission = new GroupPermission("Group perm", "4", "fossy");
    $groupPermission->setPerm("New perm");
    $this->assertEquals("New perm", $groupPermission->getPerm());
  }

  /**
   * Tests GroupPermission::getGroupPk() method.
   *
   * This method validates that the `getGroupPk` method returns the correct group ID value.
   */
  public function testGetGroupPk()
  {
    $groupPermission = new GroupPermission("Group perm", "4", "fossy");
    $this->assertEquals("4", $groupPermission->getGroupPk());
  }

  /**
   * Tests GroupPermission::setGroupPk() method.
   *
   * This method validates that the `setGroupPk` method correctly updates the group ID value.
   */
  public function testSetGroupPk()
  {
    $groupPermission = new GroupPermission("Group perm", "4", "fossy");
    $groupPermission->setGroupPk("10");
    $this->assertEquals("10", $groupPermission->getGroupPk());
  }

  /**
   * Tests GroupPermission::getGroupName() method.
   *
   * This method validates that the `getGroupName` method returns the correct group name value.
   */
  public function testGetGroupName()
  {
    $groupPermission = new GroupPermission("Group perm", "4", "fossy");
    $this->assertEquals("fossy", $groupPermission->getGroupName());
  }

  /**
   * Tests GroupPermission::setGroupName() method.
   *
   * This method validates that the `setGroupName` method correctly updates the group name value.
   */
  public function testSetGroupName()
  {
    $groupPermission = new GroupPermission("Group perm", "4", "fossy");
    $groupPermission->setGroupName("newName");
    $this->assertEquals("newName", $groupPermission->getGroupName());
  }
}
