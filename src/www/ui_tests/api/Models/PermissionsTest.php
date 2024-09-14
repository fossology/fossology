<?php
/*
 SPDX-FileCopyrightText: © 2024 Valens Niyonsenga <valensniyonsenga2003@gmail.com>
 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @file
 * @brief Tests for Permissions model
 */
namespace Fossology\UI\Api\Test\Models;

use Fossology\UI\Api\Models\ApiVersion;
use Fossology\UI\Api\Models\Permissions;
use Monolog\Test\TestCase;

class PermissionsTest extends TestCase
{
  /**
   * Provides test data and an instance of the Permissions class.
   *
   * @return array An associative array containing:
   *  - `expectedArray`: The expected array structure.
   *  - `obj`: The instance of Permissions being tested.
   */
  private function getPermissionsInfo($version = ApiVersion::V2)
  {
    $expectedArray = null;
    $permGroups = [
      ['groupPk' => 1, 'perm' => 'read'],
      ['groupPk' => 2, 'perm' => 'write']
    ];

    if ($version == ApiVersion::V1)
    {
      $expectedArray = [
        "publicPerm" => "public",
        "permGroups" => $permGroups,
      ];
    } else {
      $expectedArray = [
        "publicPerm" => "public",
        "permGroups" => $permGroups,
      ];
    }
    $obj = new Permissions("public", $permGroups);
    return [
      'expectedArray' => $expectedArray,
      'obj' => $obj
    ];
  }

  /**
   * @test
   * -# Test data model returned by Permissions::getArray($version) when API version is V1
   */
  public function testDataFormatV1()
  {
    $this->testDataFormat(ApiVersion::V1);
  }

  /**
   * @test
   * -# Test data model returned by Permissions::getArray($version) when API version is V2
   */
  public function testDataFormatV2()
  {
    $this->testDataFormat(ApiVersion::V2);
  }

  /**
   * -# Test the data format returned by Permissions::getArray($version) model
   */
  private function testDataFormat($version)
  {
    $info = $this->getPermissionsInfo($version);
    $expectedArray = $info['expectedArray'];
    $permissions = $info['obj'];
    $this->assertEquals($expectedArray, $permissions->getArray($version));
  }

  /**
   * Tests Permissions::getpublicPerm() method.
   *
   * This method validates that the `getpublicPerm` method returns the correct public permission value.
   */
  public function testGetPublicPerm()
  {
    $permissions = $this->getPermissionsInfo(ApiVersion::V2)['obj'];
    $this->assertEquals("public", $permissions->getpublicPerm());
  }

  /**
   * Tests Permissions::setpublicPerm() method.
   *
   * This method validates that the `setpublicPerm` method correctly updates the public permission value.
   */
  public function testSetPublicPerm()
  {
    $permissions = $this->getPermissionsInfo(ApiVersion::V2)['obj'];
    $permissions->setpublicPerm("private");
    $this->assertEquals("private", $permissions->getpublicPerm());
  }

  /**
   * Tests Permissions::getpermGroups() method.
   *
   * This method validates that the `getpermGroups` method returns the correct permissions groups array.
   */
  public function testGetPermGroups()
  {
    $permGroups = [
      ['groupPk' => 1, 'perm' => 'read'],
      ['groupPk' => 2, 'perm' => 'write']
    ];
    $permissions = $this->getPermissionsInfo(ApiVersion::V2)['obj'];
    $this->assertEquals($permGroups, $permissions->getpermGroups());
  }

  /**
   * Tests Permissions::setpermGroups() method.
   *
   * This method validates that the `setpermGroups` method correctly updates the permissions groups array.
   */
  public function testSetPermGroups()
  {
    $permGroups = [
      ['groupPk' => 1, 'perm' => 'read'],
      ['groupPk' => 2, 'perm' => 'write']
    ];
    $permissions = $this->getPermissionsInfo(ApiVersion::V2)['obj'];
    $permissions->setpermGroups($permGroups);
    $this->assertEquals($permGroups, $permissions->getpermGroups());
  }
}
