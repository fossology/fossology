<?php
/*
 SPDX-FileCopyrightText: © 2024 Valens Niyonsenga <valensniyonsenga2003@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/
namespace Fossology\UI\Api\Test\Models;

use Fossology\UI\Api\Models\ApiVersion;
use Fossology\UI\Api\Models\GroupPermission;
use PHPUnit\Framework\TestCase;

class GroupPermissionTest extends TestCase
{
  private function getGroupPermissionInfo($version = ApiVersion::V2)
  {
    $data = null;
    if ($version == ApiVersion::V1) {
      $data = [
        'perm' => "perm",
        'group_pk' => 3,
        'group_name' => "fossy"
      ];
    } else {
      $data = [
        'perm' => "perm",
        'groupPk' => 3,
        'groupName' => "fossy"
      ];
    }
    return [
      "groupsInfo" => $data,
      "obj" => new GroupPermission("perm", 3, "fossy")
    ];
  }

  /**
   * Test GroupPermission::getArray() for V1
   * Tests the getArray method of the GroupPermission class for API version V1.
   * - # Check if the returned array matches the expected format for version V1.
   */
  public function testDataFormatV1()
  {
    $this->testDataFormat(ApiVersion::V1);
  }

  /**
   * Test GroupPermission::getArray() for V2
   * Tests the getArray method of the GroupPermission class for API version V2.
   * - # Check if the returned array matches the expected format for version V2.
   */
  public function testDataFormatV2()
  {
    $this->testDataFormat();
  }

  /**
   * Helper method to test GroupPermission::getArray()
   * Helper method to test the getArray method of the GroupPermission class.
   * @param $version Version to test.
   * - # Check if the returned array matches the expected format for the given version.
   */
  private function testDataFormat($version = ApiVersion::V2)
  {
    $expectedArray = $this->getGroupPermissionInfo($version)['groupsInfo'];
    $groupPermission = $this->getGroupPermissionInfo($version)['obj'];
    $this->assertEquals($expectedArray, $groupPermission->getArray($version));
  }

  /**
   * Test GroupPermission::getJSON() for V1
   * Tests the getJSON method of the GroupPermission class for API version V1.
   * - # Check if the returned JSON string matches the expected format for version V1.
   */
  public function testGetJSONV1()
  {
    $groupPermissionInfo = $this->getGroupPermissionInfo(ApiVersion::V1);
    $expectedJson = json_encode($groupPermissionInfo['groupsInfo']);
    $this->assertEquals($expectedJson, $groupPermissionInfo['obj']->getJSON(ApiVersion::V1));
  }

  /**
   * Test GroupPermission::getJSON() for V2
   * Tests the getJSON method of the GroupPermission class for API version V2.
   * - # Check if the returned JSON string matches the expected format for version V2.
   */
  public function testGetJSONV2()
  {
    $groupPermissionInfo = $this->getGroupPermissionInfo();
    $expectedJson = json_encode($groupPermissionInfo['groupsInfo']);
    $this->assertEquals($expectedJson, $groupPermissionInfo['obj']->getJSON(ApiVersion::V2));
  }

  /**
   * Test GroupPermission::getPerm()
   * Tests the getPerm method of the GroupPermission class.
   * - # Check if the perm property is correctly retrieved.
   */
  public function testGetPerm()
  {
    $groupPermission = $this->getGroupPermissionInfo()['obj'];
    $this->assertEquals("perm", $groupPermission->getPerm());
  }

  /**
   * Test GroupPermission::getGroupPk()
   * Tests the getGroupPk method of the GroupPermission class.
   * - # Check if the groupPk property is correctly retrieved.
   */
  public function testGetGroupPk()
  {
    $groupPermission = $this->getGroupPermissionInfo()['obj'];
    $this->assertEquals(3, $groupPermission->getGroupPk());
  }

  /**
   * Test GroupPermission::getGroupName()
   * Tests the getGroupName method of the GroupPermission class.
   * - # Check if the groupName property is correctly retrieved.
   */
  public function testGetGroupName()
  {
    $groupPermission = $this->getGroupPermissionInfo()['obj'];
    $this->assertEquals("fossy", $groupPermission->getGroupName());
  }

  /**
   * Test GroupPermission::setPerm()
   * Tests the setPerm method of the GroupPermission class.
   * - # Check if the perm property is correctly set.
   */
  public function testSetPerm()
  {
    $groupPermission = $this->getGroupPermissionInfo()['obj'];
    $groupPermission->setPerm("newPerm");
    $this->assertEquals("newPerm", $groupPermission->getPerm());
  }

  /**
   * Test GroupPermission::setGroupPk()
   * Tests the setGroupPk method of the GroupPermission class.
   * - # Check if the groupPk property is correctly set.
   */
  public function testSetGroupPk()
  {
    $groupPermission = $this->getGroupPermissionInfo()['obj'];
    $groupPermission->setGroupPk(5);
    $this->assertEquals(5, $groupPermission->getGroupPk());
  }

  /**
   * Test GroupPermission::setGroupName()
   * Tests the setGroupName method of the GroupPermission class.
   * - # Check if the groupName property is correctly set.
   */
  public function testSetGroupName()
  {
    $groupPermission = $this->getGroupPermissionInfo()['obj'];
    $groupPermission->setGroupName("newFossy");
    $this->assertEquals("newFossy", $groupPermission->getGroupName());
  }
}
