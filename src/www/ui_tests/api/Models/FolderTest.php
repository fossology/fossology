<?php
/***************************************************************
 * Copyright (C) 2020 Siemens AG
 * Author: Gaurav Mishra <mishra.gaurav@siemens.com>
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 ***************************************************************/
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
