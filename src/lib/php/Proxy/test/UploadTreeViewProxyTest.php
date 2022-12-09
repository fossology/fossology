<?php
/*
 SPDX-FileCopyrightText: © 2014 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Proxy;

use Fossology\Lib\Data\Tree\ItemTreeBounds;
use Mockery as M;

class UploadTreeViewProxyTest extends \PHPUnit\Framework\TestCase
{
  private $viewSuffix = "<suffix>";

  /** @var ItemTreeBounds|M\MockInterface */
  private $itemTreeBounds;

  protected function setUp() : void
  {
    $this->itemTreeBounds = M::mock(ItemTreeBounds::class);
  }

  public function testDefaultViewName()
  {
    $this->itemTreeBounds->shouldReceive("getUploadTreeTableName")->once()->withNoArgs()->andReturn("foo");

    $uploadTreeView = new UploadTreeViewProxy($this->itemTreeBounds);

    assertThat($uploadTreeView->getDbViewName(), is("UploadTreeView"));
  }

  public function testViewName()
  {
    $this->itemTreeBounds->shouldReceive("getUploadTreeTableName")->once()->withNoArgs()->andReturn("foo");

    $uploadTreeView = new UploadTreeViewProxy($this->itemTreeBounds, array(), $this->viewSuffix);

    assertThat($uploadTreeView->getDbViewName(), is("UploadTreeView.<suffix>"));
  }

  public function testWithoutCondition()
  {
    $this->itemTreeBounds->shouldReceive("getUploadTreeTableName")->once()->withNoArgs()->andReturn("foo");

    $uploadTreeView = new UploadTreeViewProxy($this->itemTreeBounds);

    assertThat($uploadTreeView->getDbViewQuery(), is("SELECT * FROM foo"));
  }

  public function testWithoutConditionAndDefaultTable()
  {
    $this->itemTreeBounds->shouldReceive("getUploadTreeTableName")->once()->withNoArgs()->andReturn("uploadtree_a");
    $this->itemTreeBounds->shouldReceive("getUploadId")->once()->withNoArgs()->andReturn(23);

    $uploadTreeView = new UploadTreeViewProxy($this->itemTreeBounds);

    assertThat($uploadTreeView->getDbViewQuery(), is("SELECT * FROM uploadtree_a WHERE upload_fk = 23"));
  }

  public function testWithoutConditionAndMasterTable()
  {
    $this->itemTreeBounds->shouldReceive("getUploadTreeTableName")->once()->withNoArgs()->andReturn("uploadtree");
    $this->itemTreeBounds->shouldReceive("getUploadId")->once()->withNoArgs()->andReturn(23);

    $uploadTreeView = new UploadTreeViewProxy($this->itemTreeBounds);

    assertThat($uploadTreeView->getDbViewQuery(), is("SELECT * FROM uploadtree WHERE upload_fk = 23"));
  }

  public function testWithUploadCondition()
  {
    $this->itemTreeBounds->shouldReceive("getUploadTreeTableName")->once()->withNoArgs()->andReturn("foo");
    $this->itemTreeBounds->shouldReceive("getUploadId")->once()->withNoArgs()->andReturn(76);

    $uploadTreeView = new UploadTreeViewProxy($this->itemTreeBounds, array(UploadTreeViewProxy::CONDITION_UPLOAD));

    assertThat($uploadTreeView->getDbViewQuery(), is("SELECT * FROM foo WHERE upload_fk = 76"));
  }

  public function testWithDoubleUploadCondition()
  {
    $this->itemTreeBounds->shouldReceive("getUploadTreeTableName")->once()->withNoArgs()->andReturn("foo");
    $this->itemTreeBounds->shouldReceive("getUploadId")->once()->withNoArgs()->andReturn(76);

    $uploadTreeView = new UploadTreeViewProxy($this->itemTreeBounds, array(UploadTreeViewProxy::CONDITION_UPLOAD, UploadTreeViewProxy::CONDITION_UPLOAD));

    assertThat($uploadTreeView->getDbViewQuery(), is("SELECT * FROM foo WHERE upload_fk = 76"));
  }

  public function testWithRangeCondition()
  {
    $this->itemTreeBounds->shouldReceive("getUploadTreeTableName")->once()->withNoArgs()->andReturn("foo");
    $this->itemTreeBounds->shouldReceive("getLeft")->once()->withNoArgs()->andReturn(25);
    $this->itemTreeBounds->shouldReceive("getRight")->once()->withNoArgs()->andReturn(50);

    $uploadTreeView = new UploadTreeViewProxy($this->itemTreeBounds, array(UploadTreeViewProxy::CONDITION_RANGE));

    assertThat($uploadTreeView->getDbViewQuery(), is("SELECT * FROM foo WHERE lft BETWEEN 25 AND 50"));
  }

  public function testWithPlainFilesCondition()
  {
    $this->itemTreeBounds->shouldReceive("getUploadTreeTableName")->once()->withNoArgs()->andReturn("foo");

    $uploadTreeView = new UploadTreeViewProxy($this->itemTreeBounds, array(UploadTreeViewProxy::CONDITION_PLAIN_FILES));

    assertThat($uploadTreeView->getDbViewQuery(), is("SELECT * FROM foo WHERE ((ufile_mode & (3<<28))=0) AND pfile_fk != 0"));
  }

  public function testWithMultipleConditions()
  {
    $this->itemTreeBounds->shouldReceive("getUploadTreeTableName")->once()->withNoArgs()->andReturn("foo");
    $this->itemTreeBounds->shouldReceive("getUploadId")->once()->withNoArgs()->andReturn(5);
    $this->itemTreeBounds->shouldReceive("getLeft")->once()->withNoArgs()->andReturn(22);
    $this->itemTreeBounds->shouldReceive("getRight")->once()->withNoArgs()->andReturn(43);

    $uploadTreeView = new UploadTreeViewProxy($this->itemTreeBounds, array(UploadTreeViewProxy::CONDITION_UPLOAD, UploadTreeViewProxy::CONDITION_PLAIN_FILES, UploadTreeViewProxy::CONDITION_RANGE));

    assertThat($uploadTreeView->getDbViewQuery(), is("SELECT * FROM foo WHERE upload_fk = 5 AND ((ufile_mode & (3<<28))=0) AND pfile_fk != 0 AND lft BETWEEN 22 AND 43"));
  }

  public function testExcpetionWithUnknownConstraint()
  {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage("constraint bar is not defined");
    $this->itemTreeBounds->shouldReceive("getUploadTreeTableName")->once()->withNoArgs()->andReturn("foo");

    new UploadTreeViewProxy($this->itemTreeBounds, array('bar'));
  }
}
