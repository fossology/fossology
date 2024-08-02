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
use PHPUnit\Framework\TestCase;

/**
 * @class FolderTest
 * @brief Tests for Folder model
 */
class FolderTest extends TestCase
{
  /**
   * Provides test data and instances of the Folder class.
   * @return array An associative array containing test data and Folder objects.
   */
  private function getFolderInfo()
  {
    $parentFolder = new Folder(2, 'parent', 'Root folder', null);
    $childFolder = new Folder(3, 'folder-1', 'Folder 1', 2);

    return [
      'parentInfo' => [
        'id' => 2,
        'name' => 'parent',
        'description' => 'Root folder',
        'parent' => null
      ],
      'childInfo' => [
        'id' => 3,
        'name' => 'folder-1',
        'description' => 'Folder 1',
        'parent' => 2
      ],
      'parentObj' => $parentFolder,
      'childObj' => $childFolder
    ];
  }

  /**
   * Test Folder::getArray()
   * Tests that the Folder object's getArray method returns the correct data format.
   * - # Check if the folder was initialized correctly and getArray returns the expected array.
   */
  public function testDataFormat()
  {
    $data = $this->getFolderInfo();
    $this->assertEquals($data['parentInfo'], $data['parentObj']->getArray());
    $this->assertEquals($data['childInfo'], $data['childObj']->getArray());
  }

  /**
   * Test Folder::setId()
   * Tests the setId method of the Folder class.
   * - # Check if the id has changed to the new value.
   */
  public function testSetId()
  {
    $folder = $this->getFolderInfo()['parentObj'];
    $folder->setId(10);
    $this->assertEquals(10, $folder->getId());
  }

  /**
   * Test Folder::setName()
   * Tests the setName method of the Folder class.
   * - # Check if the name has changed to the new value.
   */
  public function testSetName()
  {
    $folder = $this->getFolderInfo()['parentObj'];
    $folder->setName('newFolderName');
    $this->assertEquals('newFolderName', $folder->getName());
  }

  /**
   * Test Folder::setDescription()
   * Tests the setDescription method of the Folder class.
   * - # Check if the description has changed to the new value.
   */
  public function testSetDescription()
  {
    $folder = $this->getFolderInfo()['parentObj'];
    $folder->setDescription('New description');
    $this->assertEquals('New description', $folder->getDescription());
  }

  /**
   * Test Folder::setParent()
   * Tests the setParent method of the Folder class.
   * - # Check if the parent has changed to the new value.
   */
  public function testSetParent()
  {
    $folder = $this->getFolderInfo()['childObj'];
    $folder->setParent(5);
    $this->assertEquals(5, $folder->getParent());
  }

  /**
   * Test Folder::getId()
   * Tests the getId method of the Folder class.
   * - # Check if getId returns the correct id.
   */
  public function testGetId()
  {
    $folder = $this->getFolderInfo()['parentObj'];
    $this->assertEquals(2, $folder->getId());
  }

  /**
   * Test Folder::getName()
   * Tests the getName method of the Folder class.
   * - # Check if getName returns the correct name.
   */
  public function testGetName()
  {
    $folder = $this->getFolderInfo()['parentObj'];
    $this->assertEquals('parent', $folder->getName());
  }

  /**
   * Test Folder::getDescription()
   * Tests the getDescription method of the Folder class.
   * - # Check if getDescription returns the correct description.
   */
  public function testGetDescription()
  {
    $folder = $this->getFolderInfo()['parentObj'];
    $this->assertEquals('Root folder', $folder->getDescription());
  }

  /**
   * Test Folder::getParent()
   * Tests the getParent method of the Folder class.
   * - # Check if getParent returns the correct parent.
   */
  public function testGetParent()
  {
    $folder = $this->getFolderInfo()['childObj'];
    $this->assertEquals(2, $folder->getParent());
  }

  /**
   * Test Folder::getJSON()
   * Tests the getJSON method of the Folder class.
   * - # Check if getJSON returns the correct JSON representation.
   */
  public function testGetJSON()
  {
    $data = $this->getFolderInfo();
    $this->assertJsonStringEqualsJsonString(json_encode($data['parentInfo']), $data['parentObj']->getJSON());
    $this->assertJsonStringEqualsJsonString(json_encode($data['childInfo']), $data['childObj']->getJSON());
  }
}
