<?php
/*
 SPDX-FileCopyrightText: © 2024 Valens Niyonsenga <valensniyonsenga2003@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @file
 * @brief Tests for GroupTest
 */
namespace Models;

use Fossology\UI\Api\Models\Group;

class GroupTest extends \PHPUnit\Framework\TestCase
{
  /**
   * Provides test data and an instance of the Group class.
   *
   * @return array An associative array containing test data and a Group object.
   */
  public function getGroupInfo()
  {
    return [
      'groupInfo' =>  [
        'id' => 1,
        'name' => 'Fossy'
      ],
      'obj' => new Group(1, 'Fossy')
    ];
  }

  /**
   * Test Group:getArray()
   * Tests the getArray method of the Group class.
   *  - # Check if the returned array matches the expected data
   */
  public function testDataFormat()
  {
    $obj = $this->getGroupInfo()['obj'];
    $expectedArray = $this->getGroupInfo()['groupInfo'];
    $this->assertEquals($expectedArray, $obj->getArray());
  }

  /**
   * Test Group:setId()
   * Tests the setId method of the Group class.
   *  - # Check if the group ID has changed to the new value
   */
  public function testSetId()
  {
    $obj = $this->getGroupInfo()['obj'];
    $obj->setId(2);
    $this->assertEquals(2, $obj->getId());
  }

  /**
   * Test Group:setName()
   * Tests the setName method of the Group class.
   *  - # Check if the group name has changed to the new value
   */
  public function testSetName()
  {
    $obj = $this->getGroupInfo()['obj'];
    $obj->setName('John Doe');
    $this->assertEquals('John Doe', $obj->getName());
  }

  /**
   * Test Group:getJSON()
   * Tests the getJSON method of the Group class.
   *  - # Check if the returned JSON matches the expected JSON data
   */
  public function testGetJSON()
  {
    $obj = $this->getGroupInfo()['obj'];
    $expectedJSON = json_encode($this->getGroupInfo()['groupInfo']);
    $this->assertEquals($expectedJSON, $obj->getJSON());
  }
}
