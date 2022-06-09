<?php
/*
 SPDX-FileCopyrightText: © 2014 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Dao\Data;

use Fossology\Lib\Data\Tree\ItemTreeBounds;

class ItemTreeBoundsTest extends \PHPUnit\Framework\TestCase
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

  protected function setUp() : void
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
