<?php
/*
 SPDX-FileCopyrightText: © 2024 Valens Niyonsenga <valensniyonsenga2003@gmail.com>
 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @file
 * @brief Tests for Group model
 */
namespace Fossology\UI\Api\Test\Models;

use Fossology\UI\Api\Models\Group;
use Monolog\Test\TestCase;

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
      'id' => 1,
      'name' => 'TestGroup'
    ];
    $obj = new Group(1, 'TestGroup');
    return [
      'expectedArray' => $expectedArray,
      'obj' => $obj
    ];
  }

  /**
   * @test
   * -# Test data model returned by Group::getArray()
   */
  public function testDataFormat()
  {
    $info = $this->getGroupInfo();
    $expectedArray = $info['expectedArray'];
    $group = $info['obj'];
    $this->assertEquals($expectedArray, $group->getArray());
  }

  /**
   * Tests Group::getId() method.
   *
   * This method validates that the `getId` method returns the correct group ID.
   */
  public function testGetId()
  {
    $group = $this->getGroupInfo()['obj'];
    $this->assertEquals(1, $group->getId());
  }

  /**
   * Tests Group::setId() method.
   *
   * This method validates that the `setId` method correctly updates the group ID.
   */
  public function testSetId()
  {
    $group = $this->getGroupInfo()['obj'];
    $group->setId(2);
    $this->assertEquals(2, $group->getId());
  }

  /**
   * Tests Group::getName() method.
   *
   * This method validates that the `getName` method returns the correct group name.
   */
  public function testGetName()
  {
    $group = $this->getGroupInfo()['obj'];
    $this->assertEquals('TestGroup', $group->getName());
  }

  /**
   * Tests Group::setName() method.
   *
   * This method validates that the `setName` method correctly updates the group name.
   */
  public function testSetName()
  {
    $group = $this->getGroupInfo()['obj'];
    $group->setName('NewGroupName');
    $this->assertEquals('NewGroupName', $group->getName());
  }

  /**
   * Tests Group::getJSON() method.
   *
   * This method validates that the `getJSON` method returns the correct JSON representation of the group.
   */
  public function testGetJSON()
  {
    $group = $this->getGroupInfo()['obj'];
    $expectedJson = json_encode([
      'id' => 1,
      'name' => 'TestGroup'
    ]);
    $this->assertEquals($expectedJson, $group->getJSON());
  }
}
