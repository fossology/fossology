<?php
/*
 SPDX-FileCopyrightText: © 2020 Siemens AG
 Author: Gaurav Mishra <mishra.gaurav@siemens.com>

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @file
 * @brief Tests for Folder model
 */

namespace Fossology\UI\Api\Test\Models;

use Fossology\UI\Api\Models\Folder;

/**
 * @class FolderTest
 * @brief Tests for Folder model
 */
class FolderTest extends \PHPUnit\Framework\TestCase
{
  /**
   * @test
   * -# Test for keys in Folder model
   */
  public function testDataFormat()
  {
    $expectedParent = [
      'id' => 2,
      'name' => 'parent',
      'description' => 'Root folder',
      'parent' => null
    ];
    $expectedChild = [
      'id' => 3,
      'name' => 'folder-1',
      'description' => 'Folder 1',
      'parent' => 2
    ];

    $parentFolder = new Folder('2', 'parent', 'Root folder', null);
    $childFolder = new Folder('3', 'folder-1', 'Folder 1', '2');

    $this->assertEquals($expectedParent, $parentFolder->getArray());
    $this->assertEquals($expectedChild, $childFolder->getArray());
  }

  /**
   * Tests Folder::getId() and Folder::setId() methods.
   *
   * This method verifies that the setId method correctly updates the folder ID,
   * and that the getId method returns the correct value.
   */
  public function testSetAndGetId()
  {
    $folder = new Folder(1, 'name', 'description', null);
    $folder->setId(10);
    $this->assertEquals(10, $folder->getId());
  }

  /**
   * Tests Folder::getName() and Folder::setName() methods.
   *
   * This method verifies that the setName method correctly updates the folder name,
   * and that the getName method returns the correct value.
   */
  public function testSetAndGetName()
  {
    $folder = new Folder(1, 'name', 'description', null);
    $folder->setName('newName');
    $this->assertEquals('newName', $folder->getName());
  }

  /**
   * Tests Folder::getDescription() and Folder::setDescription() methods.
   *
   * This method verifies that the setDescription method correctly updates the folder description,
   * and that the getDescription method returns the correct value.
   */
  public function testSetAndGetDescription()
  {
    $folder = new Folder(1, 'name', 'description', null);
    $folder->setDescription('newDescription');
    $this->assertEquals('newDescription', $folder->getDescription());
  }

  /**
   * Tests Folder::getParent() and Folder::setParent() methods.
   *
   * This method verifies that the setParent method correctly updates the parent ID,
   * and that the getParent method returns the correct value.
   */
  public function testSetAndGetParent()
  {
    $folder = new Folder(1, 'name', 'description', null);
    $folder->setParent(5);
    $this->assertEquals(5, $folder->getParent());
  }
}
