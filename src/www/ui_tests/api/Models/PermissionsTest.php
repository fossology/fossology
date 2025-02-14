<?php
/*
 SPDX-FileCopyrightText: © 2025 Harshit Gandhi <gandhiharshit716@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @file
 * @brief Tests for Permissions
 */

namespace Fossology\UI\Api\Test\Models;

use Fossology\UI\Api\Models\Permissions;
use Fossology\UI\Api\Models\ApiVersion;

use PHPUnit\Framework\TestCase;

/**
 * @class PermissionsTest
 * @brief Tests for Permissions model
 */
class PermissionsTest extends TestCase
{
  ////// Constructor Tests //////

  /**
   * Tests that the Permissions constructor initializes an instance correctly.
   *
   * @return void
   */
  public function testConstructor()
  {
    $publicPerm = "read";
    $permGroups = ["group1" => "write", "group2" => "read"];

    $permissions = new Permissions($publicPerm, $permGroups);

    $this->assertInstanceOf(Permissions::class, $permissions);
  }

  ////// Getters Tests //////

  /**
   * @test
   * -# Test getter for publicPerm
   */
  public function testGetPublicPerm()
  {
    $publicPerm = "read";
    $permissions = new Permissions($publicPerm, []);
    $this->assertEquals($publicPerm, $permissions->getpublicPerm());
  }

  /**
   * @test
   * -# Test getter for permGroups
   */
  public function testGetPermGroups()
  {
    $permGroups = ["group1" => "write", "group2" => "read"];
    $permissions = new Permissions("", $permGroups);
    $this->assertEquals($permGroups, $permissions->getpermGroups());
  }

  ////// Setters Tests //////

  /**
   * @test
   * -# Test setter for publicPerm
   */
  public function testSetPublicPerm()
  {
    $permissions = new Permissions("none", []);
    $newPublicPerm = "write";
    $permissions->setpublicPerm($newPublicPerm);
    $this->assertEquals($newPublicPerm, $permissions->getpublicPerm());
  }

  /**
   * @test
   * -# Test setter for permGroups
   */
  public function testSetPermGroups()
  {
    $permissions = new Permissions("", []);
    $newPermGroups = ["group3" => "admin"];
    $permissions->setpermGroups($newPermGroups);
    $this->assertEquals($newPermGroups, $permissions->getpermGroups());
  }

  /**
   * @test
   * -# Test getArray method with API version V1
   * -# Create Permissions object with test data
   * -# Verify the array structure matches expected format for V1
   */
  public function testGetArrayV1()
  {
    $publicPerm = "read";
    $permGroups = ["group1" => "write"];
    
    $permissions = new Permissions($publicPerm, $permGroups);

    $expectedArray = [
      'publicPerm' => $publicPerm,
      'permGroups' => $permGroups
    ];

    $this->assertEquals($expectedArray, $permissions->getArray(ApiVersion::V1));
  }

  /**
   * @test
   * -# Test getArray method with API version V2
   * -# Create Permissions object with test data
   * -# Verify the array structure matches expected format for V2
   */
  public function testGetArrayV2()
  {
    $publicPerm = "write";
    $permGroups = ["group1" => "admin", "group2" => "write"];
    
    $permissions = new Permissions($publicPerm, $permGroups);

    $expectedArray = [
      'publicPerm' => $publicPerm,
      'permGroups' => $permGroups
    ];

    $this->assertEquals($expectedArray, $permissions->getArray(ApiVersion::V2));
  }

  /**
   * @test
   * -# Test getJSON method with API version V1
   * -# Create Permissions object with test data
   * -# Verify JSON output matches expected format for V1
   */
  public function testGetJSONV1()
  {
    $publicPerm = "read";
    $permGroups = ["group1" => "write"];
    
    $permissions = new Permissions($publicPerm, $permGroups);

    $expectedArray = [
      'publicPerm' => $publicPerm,
      'permGroups' => $permGroups
    ];

    $this->assertEquals(json_encode($expectedArray), $permissions->getJSON(ApiVersion::V1));
  }

  /**
   * @test
   * -# Test getJSON method with API version V2
   * -# Create Permissions object with test data
   * -# Verify JSON output matches expected format for V2
   */
  public function testGetJSONV2()
  {
    $publicPerm = "write";
    $permGroups = ["group1" => "admin"];
    
    $permissions = new Permissions($publicPerm, $permGroups);

    $expectedArray = [
      'publicPerm' => $publicPerm,
      'permGroups' => $permGroups
    ];

    $this->assertEquals(json_encode($expectedArray), $permissions->getJSON(ApiVersion::V2));
  }
}