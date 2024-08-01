<?php

/*
 SPDX-FileCopyrightText: © 2024 Valens Niyonsenga <valensniyonsenga2003@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @file
 * @brief Tests for PermissionsTest
 */

namespace Fossology\UI\Api\Test\Models;

use Fossology\UI\Api\Models\ApiVersion;
use Fossology\UI\Api\Models\Permissions;

class PermissionsTest extends \PHPUnit\Framework\TestCase
{
  /**
   * Provides test data and an instance of the Permissions class.
   *
   * @return array An associative array containing test data and a Permissions object.
   */
  public function getPermissionsInfo($version=ApiVersion::V2)
  {
    $publicPerm = "read";
    $permGroups = [
      'fossy' => ['read', 'write'],
      'Default User' => ['read']
    ];

    return [
      'v1' => [
        'publicPerm' => $publicPerm,
        'permGroups' => $permGroups
      ],
      'obj' => new Permissions($publicPerm, $permGroups)
    ];
  }

  /**
   * Test Permissions:getArray()
   * Tests the getArray method of the Permissions class.
   *  - # Check if the returned array matches the expected data
   */
  public function testDataFormat()
  {
    $obj = $this->getPermissionsInfo()['obj'];
    $expectedArray = $this->getPermissionsInfo()['v1'];
    $this->assertEquals($expectedArray, $obj->getArray());
  }

  /**
   * Test Permissions:setpublicPerm()
   * Tests the setpublicPerm method of the Permissions class.
   *  - # Check if the public permission has changed to the new value
   */
  public function testSetPublicPerm()
  {
    $obj = $this->getPermissionsInfo()['obj'];
    $obj->setpublicPerm("write");
    $this->assertEquals("write", $obj->getpublicPerm());
  }

  /**
   * Test Permissions:setpermGroups()
   * Tests the setpermGroups method of the Permissions class.
   *  - # Check if the permission groups have changed to the new value
   */
  public function testSetPermGroups()
  {
    $obj = $this->getPermissionsInfo()['obj'];
    $newPermGroups = [
      'group1' => ['read'],
      'group3' => ['write']
    ];
    $obj->setpermGroups($newPermGroups);
    $this->assertEquals($newPermGroups, $obj->getpermGroups());
  }

  /**
   * Test Permissions:getJSON()
   * Tests the getJSON method of the Permissions class.
   *  - # Check if the returned JSON matches the expected JSON data
   */
  public function testGetJSON()
  {
    $obj = $this->getPermissionsInfo()['obj'];
    $expectedJSON = json_encode($this->getPermissionsInfo()['v1']);
    $this->assertEquals($expectedJSON, $obj->getJSON());
  }
}
