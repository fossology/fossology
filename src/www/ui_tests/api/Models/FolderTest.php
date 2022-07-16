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
}
