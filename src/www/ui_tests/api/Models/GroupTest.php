<?php
/*
 SPDX-FileCopyrightText: © 2024 Valens Niyonsenga <valensniyonsenga2003@gmail.com>
 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @file
 * @brief Tests for GroupTest model
 */
namespace Fossology\UI\Api\Test\Models;


use Fossology\UI\Api\Models\Group;
use PHPUnit\Framework\TestCase;

class GroupTest extends TestCase
{
  /**
   * Provides test data and an instance of the Group class.
   *
   * @return array An associative array containing:
   *  - `expectedArray`: The expected array structure.
   *  - `obj`: The instance of Group being tested.
   */
  private function getGroupInfo()
  {
    $expectedArray = [
      "id"  => 4,
      "name" => "fossy",
    ];
    $obj = new Group(4,"fossy");
    return [
      'expectedArray' => $expectedArray,
      'obj' => $obj
    ];
  }

  /**
   * Tests Group::getArray() method.
   *
   * This method validates that the `getArray` method returns the correct data structure.
   */
  public function testDataFormat()
  {
    $expectedArray = $this->getGroupInfo()['expectedArray'];
    $group = $this->getGroupInfo()['obj'];
    $this->assertEquals($expectedArray, $group->getArray());
  }

  /**
   * Tests Group::getId() method.
   *
   * This method validates that the `getId` method returns the correct ID value.
   */
  public function testGetId()
  {
    $group = new Group(4, "fossy");
    $this->assertEquals(4, $group->getId());
  }

  /**
   * Tests Group::setId() method.
   *
   * This method validates that the `setId` method correctly updates the ID value.
   */
  public function testSetId()
  {
    $group = new Group(4, "fossy");
    $group->setId(10);
    $this->assertEquals(10, $group->getId());
  }

  /**
   * Tests Group::getName() method.
   *
   * This method validates that the `getName` method returns the correct name value.
   */
  public function testGetName()
  {
    $group = new Group(4, "fossy");
    $this->assertEquals("fossy", $group->getName());
  }

  /**
   * Tests Group::setName() method.
   *
   * This method validates that the `setName` method correctly updates the name value.
   */
  public function testSetName()
  {
    $group = new Group(4, "fossy");
    $group->setName("newName");
    $this->assertEquals("newName", $group->getName());
  }
}
