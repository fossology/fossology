<?php

/*
 SPDX-FileCopyrightText: © 2024 Valens Niyonsenga <valensniyonsenga2003@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @file
 * @brief Tests for Group
 */
namespace Fossology\UI\Api\Test\Models;
use Fossology\UI\Api\Models\Group;
use PHPUnit\Framework\TestCase;

/**
 * @class GroupTest
 * @brief Test for Group model
 */
class GroupTest extends TestCase
{
  /**
   * Provides test data and an instance of the Group class.
   * @return array An associative array containing test data and a Group object.
   */
  private function getGroupInfo()
  {
    return [
      'groupInfo' => [
        'id' => 4,
        'name' => "fossy"
      ],
      'obj' => new Group(4, 'fossy')
    ];
  }

  /**
   * Test Group::getArray()
   * Tests that the Group object's getArray method returns the correct data format.
   * - # Check if the group was initialized correctly and getArray returns the expected array.
   */
  public function testDataFormat()
  {
    $expectedArray = $this->getGroupInfo()['groupInfo'];
    $obj = $this->getGroupInfo()['obj'];
    $this->assertEquals($expectedArray, $obj->getArray());
  }

  /**
   * Test Group::setId()
   * Tests the setId method of the Group class.
   * - # Check if the id has changed to the new value.
   */
  public function testSetId()
  {
    $obj = $this->getGroupInfo()['obj'];
    $obj->setId(10);
    $this->assertEquals(10, $obj->getId());
  }

  /**
   * Test Group::setName()
   * Tests the setName method of the Group class.
   * - # Check if the name has changed to the new value.
   */
  public function testSetName()
  {
    $obj = $this->getGroupInfo()['obj'];
    $obj->setName("newGroupName");
    $this->assertEquals("newGroupName", $obj->getName());
  }

  /**
   * Test Group::getId()
   * Tests the getId method of the Group class.
   * - # Check if getId returns the correct id.
   */
  public function testGetId()
  {
    $obj = $this->getGroupInfo()['obj'];
    $this->assertEquals(4, $obj->getId());
  }

  /**
   * Test Group::getName()
   * Tests the getName method of the Group class.
   * - # Check if getName returns the correct name.
   */
  public function testGetName()
  {
    $obj = $this->getGroupInfo()['obj'];
    $this->assertEquals("fossy", $obj->getName());
  }

  /**
   * Test Group::getJSON()
   * Tests the getJSON method of the Group class.
   * - # Check if getJSON returns the correct JSON representation.
   */
  public function testGetJSON()
  {
    $expectedJson = json_encode($this->getGroupInfo()['groupInfo']);
    $obj = $this->getGroupInfo()['obj'];
    $this->assertJsonStringEqualsJsonString($expectedJson, $obj->getJSON());
  }
}
