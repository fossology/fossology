<?php
/*
Copyright (C) 2014, Siemens AG

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
version 2 as published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License along
with this program; if not, write to the Free Software Foundation, Inc.,
51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/

namespace Fossology\Lib\Dao\Data;

use Fossology\Lib\Data\Tree\ItemTreeBounds;

class FileTreeBoundsTest extends \PHPUnit_Framework_TestCase
{

  /**
   * @var ItemTreeBounds
   */
  private $itemTreeBounds;

  private $uploadTreeTableName = "uploadTreeTable";

  private $uploadId = 43;

  private $uploadTreeId = 8;

  private $left = 12;

  private $right = 26;

  public function setUp()
  {
    $this->itemTreeBounds = new ItemTreeBounds($this->uploadTreeId, $this->uploadTreeTableName, $this->uploadId, $this->left, $this->right);
  }

  public function testGetUploadTreeTableName()
  {
    assertThat($this->itemTreeBounds->getUploadTreeTableName(), is($this->uploadTreeTableName));
  }

  public function testGetUploadTreeID()
  {
    assertThat($this->itemTreeBounds->getItemId(), is($this->uploadTreeId));
  }

  public function testGetUploadId()
  {
    assertThat($this->itemTreeBounds->getUploadId(), is($this->uploadId));
  }

  public function testGetLeft()
  {
    assertThat($this->itemTreeBounds->getLeft(), is($this->left));
  }

  public function testGetRight()
  {
    assertThat($this->itemTreeBounds->getRight(), is($this->right));
  }

  public function testContainsFiles()
  {
    assertThat($this->itemTreeBounds->containsFiles(), is(true));

    $this->itemTreeBounds = new ItemTreeBounds($this->uploadTreeId, $this->uploadTreeTableName, $this->uploadId, $this->left, $this->left + 2);

    assertThat($this->itemTreeBounds->containsFiles(), is(true));

    $this->itemTreeBounds = new ItemTreeBounds($this->uploadTreeId, $this->uploadTreeTableName, $this->uploadId, $this->left, $this->left + 1);

    assertThat($this->itemTreeBounds->containsFiles(), is(false));
  }
}
 