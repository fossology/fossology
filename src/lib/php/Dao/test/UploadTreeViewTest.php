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

namespace Fossology\Lib\Dao;

use Fossology\Lib\Data\Tree\ItemTreeBounds;
use Mockery as M;

class UploadTreeViewTest extends \PHPUnit_Framework_TestCase
{
  private $viewSuffix = "<suffix>";

  /** @var ItemTreeBounds|M\MockInterface */
  private $itemTreeBounds;

  public function setUp()
  {
    $this->itemTreeBounds = M::mock(ItemTreeBounds::classname());
  }

  public function testDefaultViewName()
  {
    $this->itemTreeBounds->shouldReceive("getUploadTreeTableName")->once()->withNoArgs()->andReturn("foo");

    $uploadTreeView = new UploadTreeView($this->itemTreeBounds);

    assertThat($uploadTreeView->getDbViewName(), is("UploadTreeView"));
  }

  public function testViewName()
  {
    $this->itemTreeBounds->shouldReceive("getUploadTreeTableName")->once()->withNoArgs()->andReturn("foo");

    $uploadTreeView = new UploadTreeView($this->itemTreeBounds, array(), $this->viewSuffix);

    assertThat($uploadTreeView->getDbViewName(), is("UploadTreeView.<suffix>"));
  }

  public function testWithoutCondition()
  {
    $this->itemTreeBounds->shouldReceive("getUploadTreeTableName")->once()->withNoArgs()->andReturn("foo");

    $uploadTreeView = new UploadTreeView($this->itemTreeBounds);

    assertThat($uploadTreeView->getDbViewQuery(), is("SELECT * FROM foo"));
  }

  public function testWithoutConditionAndDefaultTable()
  {
    $this->itemTreeBounds->shouldReceive("getUploadTreeTableName")->once()->withNoArgs()->andReturn("uploadtree_a");
    $this->itemTreeBounds->shouldReceive("getUploadId")->once()->withNoArgs()->andReturn(23);

    $uploadTreeView = new UploadTreeView($this->itemTreeBounds);

    assertThat($uploadTreeView->getDbViewQuery(), is("SELECT * FROM uploadtree_a WHERE upload_fk = 23"));
  }

  public function testWithoutConditionAndMasterTable()
  {
    $this->itemTreeBounds->shouldReceive("getUploadTreeTableName")->once()->withNoArgs()->andReturn("uploadtree");
    $this->itemTreeBounds->shouldReceive("getUploadId")->once()->withNoArgs()->andReturn(23);

    $uploadTreeView = new UploadTreeView($this->itemTreeBounds);

    assertThat($uploadTreeView->getDbViewQuery(), is("SELECT * FROM uploadtree WHERE upload_fk = 23"));
  }

  public function testWithUploadCondition()
  {
    $this->itemTreeBounds->shouldReceive("getUploadTreeTableName")->once()->withNoArgs()->andReturn("foo");
    $this->itemTreeBounds->shouldReceive("getUploadId")->once()->withNoArgs()->andReturn(76);

    $uploadTreeView = new UploadTreeView($this->itemTreeBounds, array(UploadTreeView::CONDITION_UPLOAD));

    assertThat($uploadTreeView->getDbViewQuery(), is("SELECT * FROM foo WHERE upload_fk = 76"));
  }

  public function testWithDoubleUploadCondition()
  {
    $this->itemTreeBounds->shouldReceive("getUploadTreeTableName")->once()->withNoArgs()->andReturn("foo");
    $this->itemTreeBounds->shouldReceive("getUploadId")->once()->withNoArgs()->andReturn(76);

    $uploadTreeView = new UploadTreeView($this->itemTreeBounds, array(UploadTreeView::CONDITION_UPLOAD, UploadTreeView::CONDITION_UPLOAD));

    assertThat($uploadTreeView->getDbViewQuery(), is("SELECT * FROM foo WHERE upload_fk = 76"));
  }

  public function testWithRangeCondition()
  {
    $this->itemTreeBounds->shouldReceive("getUploadTreeTableName")->once()->withNoArgs()->andReturn("foo");
    $this->itemTreeBounds->shouldReceive("getLeft")->once()->withNoArgs()->andReturn(25);
    $this->itemTreeBounds->shouldReceive("getRight")->once()->withNoArgs()->andReturn(50);

    $uploadTreeView = new UploadTreeView($this->itemTreeBounds, array(UploadTreeView::CONDITION_RANGE));

    assertThat($uploadTreeView->getDbViewQuery(), is("SELECT * FROM foo WHERE lft BETWEEN 25 AND 50"));
  }

  public function testWithPlainFilesCondition()
  {
    $this->itemTreeBounds->shouldReceive("getUploadTreeTableName")->once()->withNoArgs()->andReturn("foo");

    $uploadTreeView = new UploadTreeView($this->itemTreeBounds, array(UploadTreeView::CONDITION_PLAIN_FILES));

    assertThat($uploadTreeView->getDbViewQuery(), is("SELECT * FROM foo WHERE ((ufile_mode & (3<<28))=0) AND pfile_fk != 0"));
  }

  public function testWithMultipleConditions()
  {
    $this->itemTreeBounds->shouldReceive("getUploadTreeTableName")->once()->withNoArgs()->andReturn("foo");
    $this->itemTreeBounds->shouldReceive("getUploadId")->once()->withNoArgs()->andReturn(5);
    $this->itemTreeBounds->shouldReceive("getLeft")->once()->withNoArgs()->andReturn(22);
    $this->itemTreeBounds->shouldReceive("getRight")->once()->withNoArgs()->andReturn(43);

    $uploadTreeView = new UploadTreeView($this->itemTreeBounds, array(UploadTreeView::CONDITION_UPLOAD, UploadTreeView::CONDITION_PLAIN_FILES, UploadTreeView::CONDITION_RANGE));

    assertThat($uploadTreeView->getDbViewQuery(), is("SELECT * FROM foo WHERE upload_fk = 5 AND ((ufile_mode & (3<<28))=0) AND pfile_fk != 0 AND lft BETWEEN 22 AND 43"));
  }

  /**
   * @expectedException \InvalidArgumentException
   * @expectedExceptionMessage constraint bar is not defined
   */
  public function testExcpetionWithUnknownConstraint()
  {
    $this->itemTreeBounds->shouldReceive("getUploadTreeTableName")->once()->withNoArgs()->andReturn("foo");

    new UploadTreeView($this->itemTreeBounds, array('bar'));
  }
}
 