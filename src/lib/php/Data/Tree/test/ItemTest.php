<?php
/*
Copyright (C) 2014, Siemens AG
Author: Andreas Würl

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

namespace Fossology\Lib\Data\Tree;

use Mockery as M;

require_once(__DIR__ . '/../../../common-dir.php');

class ItemTest extends \PHPUnit_Framework_TestCase
{
  private $id = 234;
  private $parentId = 432;
  private $fileId = 123;
  private $fileMode = 21;
  private $fileName = "<fileName>";
  /** @var ItemTreeBounds|M\MockInterface */
  private $itemTreeBounds;
  /** @var Item */
  private $item;

  public function setUp()
  {
    $this->itemTreeBounds = M::mock(ItemTreeBounds::classname());

    $this->item = new Item($this->itemTreeBounds, $this->parentId, $this->fileId, $this->fileMode, $this->fileName);
  }

  public function tearDown()
  {
    M::close();
  }

  public function testGetId()
  {
    $this->itemTreeBounds->shouldReceive("getItemId")->once()->withNoArgs()->andReturn($this->id);

    assertThat($this->item->getId(), is($this->id));
  }

  public function testGetParentId()
  {
    assertThat($this->item->getParentId(), is($this->parentId));
  }

  public function testGetFileMode()
  {
    assertThat($this->item->getFileMode(), is($this->fileMode));
  }

  public function testGetFileName()
  {
    assertThat($this->item->getFileName(), is($this->fileName));
  }

  public function testGetFileId()
  {
    assertThat($this->item->getFileId(), is($this->fileId));
  }

  public function testGetItemTreeBounds()
  {
    assertThat($this->item->getItemTreeBounds(), is($this->itemTreeBounds));
  }

  public function testContainsFileTreeItems()
  {
    $this->itemTreeBounds->shouldReceive("containsFiles")->withNoArgs()->andReturn(true);

    $this->assertTrue($this->item->containsFileTreeItems());
  }

  public function testDoesNotContainFileTreeItems()
  {
    $this->itemTreeBounds->shouldReceive("containsFiles")->withNoArgs()->andReturn(false);

    $this->assertFalse($this->item->containsFileTreeItems());
  }

  public function testHasParent() {
    $this->assertTrue($this->item->hasParent());
  }

  public function testHasNoParent() {
    $this->item = new Item($this->itemTreeBounds, null, $this->fileId, $this->fileMode, $this->fileName);
    $this->assertFalse($this->item->hasParent());
  }

  public function testIsContainer() {
    $this->assertFalse($this->item->isContainer());
  }

  public function testIsFile() {
    $this->assertTrue($this->item->isFile());
  }

}
 