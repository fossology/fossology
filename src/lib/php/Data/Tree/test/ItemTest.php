<?php
/*
 SPDX-FileCopyrightText: © 2014 Siemens AG
 Author: Andreas Würl

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Data\Tree;

use Mockery as M;

require_once(__DIR__ . '/../../../common-dir.php');

class ItemTest extends \PHPUnit\Framework\TestCase
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

  protected function setUp() : void
  {
    $this->itemTreeBounds = M::mock(ItemTreeBounds::class);

    $this->item = new Item($this->itemTreeBounds, $this->parentId, $this->fileId, $this->fileMode, $this->fileName);
  }

  protected function tearDown() : void
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

  public function testHasParent()
  {
    $this->assertTrue($this->item->hasParent());
  }

  public function testHasNoParent()
  {
    $this->item = new Item($this->itemTreeBounds, null, $this->fileId,
      $this->fileMode, $this->fileName);
    $this->assertFalse($this->item->hasParent());
  }

  public function testIsContainer()
  {
    $this->assertFalse($this->item->isContainer());
  }

  public function testIsFile()
  {
    $this->assertTrue($this->item->isFile());
  }
}
