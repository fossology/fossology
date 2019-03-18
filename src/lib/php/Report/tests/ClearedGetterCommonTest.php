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

namespace Fossology\Lib\Report;

use Fossology\Lib\Dao\TreeDao;
use Fossology\Lib\Dao\UploadDao;
use Mockery as M;
use Mockery\MockInterface;

include_once(dirname(dirname(__DIR__))."/common-string.php");

class TestClearedGetter extends ClearedGetterCommon
{
  public function __construct($groupBy = "content")
  {
    parent::__construct($groupBy);
  }

  protected function getStatements($uploadId, $uploadTreeTableName, $userId = null, $groupId=null)
  {
    return array(
      array("licenseId" => "371", "risk" => "5", "content" => "1", "text" => "t1", "comments" => "c1", "uploadtree_pk" => 1),
      array("licenseId" => "213", "risk" => "4", "content" => "1", "text" => "t2", "comments" => "c1", "uploadtree_pk" => 2),
      array("licenseId" => "243", "risk" => "4", "content" => "2", "text" => "t3", "comments" => "c3", "uploadtree_pk" => 3),
    );
  }
}

class WeirdCharClearedGetter extends ClearedGetterCommon
{
  public function __construct($groupBy = "content")
  {
    parent::__construct($groupBy);
  }

  protected function getStatements($uploadId, $uploadTreeTableName, $userId = null, $groupId=null){}

  public function getCleared($uploadId, $groupId=null, $extended = true, $agentcall = NULL)
  {
    return array(
      array("good" => "漢", "esc" => "escape", "uml" => ' ü ')
    );
  }
}

class ClearedComonReportTest extends \PHPUnit\Framework\TestCase
{
  /** @var UploadDao|MockInterface */
  private $uploadDao;
  /** @var TreeDao|MockInterface */
  private $treeDao;

  protected function setUp()
  {
    $this->uploadDao = M::mock(UploadDao::class);
    $this->treeDao = M::mock(TreeDao::class);

    $container = M::mock('ContainerBuilder');
    $GLOBALS['container'] = $container;

    $container->shouldReceive('get')->with('dao.upload')->andReturn($this->uploadDao);
    $container->shouldReceive('get')->with('dao.tree')->andReturn($this->treeDao);
    $this->assertCountBefore = \Hamcrest\MatcherAssert::getCount();
  }

  protected function tearDown()
  {
    $this->addToAssertionCount(\Hamcrest\MatcherAssert::getCount()-$this->assertCountBefore);
  }

  public function testGetFileNames()
  {
    $this->clearedGetterTest = new TestClearedGetter();

    $uploadId = 1;
    $parentId = 112;
    $uploadTreeTableName = "ut";

    $this->uploadDao
         ->shouldReceive('getUploadtreeTableName')
         ->with($uploadId)
         ->andReturn($uploadTreeTableName);

    $this->treeDao
         ->shouldReceive('getMinimalCoveringItem')
         ->with($uploadId,$uploadTreeTableName)
         ->andReturn($parentId);

    $this->treeDao
         ->shouldReceive('getFullPath')
         ->with(1, $uploadTreeTableName, $parentId)
         ->andReturn("a/1");

    $this->treeDao
         ->shouldReceive('getFullPath')
         ->with(2, $uploadTreeTableName, $parentId)
         ->andReturn("a/2");

    $this->treeDao
         ->shouldReceive('getFullPath')
         ->with(3, $uploadTreeTableName, $parentId)
         ->andReturn("a/b/1");

    $statements = $this->clearedGetterTest->getCleared($uploadId);
    $expected = array(
      "statements" => array(
        array(
          "licenseId" => "371",
          "risk" => "5",
          "content" => "1",
          "text" => "t1",
          "comments" => "c1",
          "files" => array("a/1", "a/2")
        ),
        array(
          "licenseId" => "243",
          "risk" => "4",
          "content" => "2",
          "text" => "t3",
          "comments" => "c3",
          "files" => array("a/b/1")
        )
      )
    );
    $expected = arsort($expected);
    assertThat($expected, equalTo($statements));
  }

  public function testGetFileNamesGroupByText()
  {
    $this->clearedGetterTest = new TestClearedGetter();
    $uploadId = 1;
    $parentId = 112;
    $uploadTreeTableName = "ut";

    $this->uploadDao
         ->shouldReceive('getUploadtreeTableName')
         ->with($uploadId)
         ->andReturn($uploadTreeTableName);

    $this->treeDao
         ->shouldReceive('getMinimalCoveringItem')
         ->with($uploadId,$uploadTreeTableName)
         ->andReturn($parentId);

    $this->treeDao
         ->shouldReceive('getFullPath')
         ->with(1, $uploadTreeTableName, $parentId)
         ->andReturn("a/1");

    $this->treeDao
         ->shouldReceive('getFullPath')
         ->with(2, $uploadTreeTableName, $parentId)
         ->andReturn("a/2");

    $this->treeDao
         ->shouldReceive('getFullPath')
         ->with(3, $uploadTreeTableName, $parentId)
         ->andReturn("a/b/1");

    $tester = new TestClearedGetter("text");
    $statements = $tester->getCleared($uploadId);
    $expected = array(
      "statements" => array(
        array(
          "licenseId" => "371",
          "risk" => "5",
          "content" => "1",
          "text" => "t1",
          "comments" => "c1",
          "files" => array("a/1")
        ),
        array(
          "licenseId" => "213",
          "risk" => "4",
          "content" => "1",
          "text" => "t2",
          "comments" => "c1",
          "files" => array("a/2")
        ),
        array(
          "licenseId" => "243",
          "risk" => "4",
          "content" => "2",
          "text" => "t3",
          "comments" => "c3",
          "files" => array("a/b/1")
        )
      )
    );
    $expected = arsort($expected);
    assertThat($expected, equalTo($statements));
  }

  function testWeirdChars()
  {
    $weirdCharclearedGetter = new WeirdCharclearedGetter();
    $json = $weirdCharclearedGetter->cJson(0);
    assertThat($json, containsString('"good":"\\u6f22"'));
    assertThat($json, containsString('"esc":"escape"'));
    assertThat($json, containsString('"uml":" \\u00fc "'));
  }
}
