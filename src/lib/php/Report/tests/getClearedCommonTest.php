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

use DateTime;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Dao\TreeDao;
use Mockery as M;
use Mockery\MockInterface;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Logger;

class ClearedGetterTest extends ClearedGetterCommon
{
  public function __construct($groupBy = "content")
  {
    parent::__construct($groupBy);
  }

  protected function getStatements($uploadId, $uploadTreeTableName, $userId = null)
  {
    return array(
      array("content" => "1", "text" => "t1", "uploadtree_pk" => 1),
      array("content" => "1", "text" => "t2", "uploadtree_pk" => 2),
      array("content" => "2", "text" => "t3", "uploadtree_pk" => 3),
    );
  }
}

class ClearedComonReportTest extends \PHPUnit_Framework_TestCase
{
  /** @var UploadDao|MockInterface */
  private $uploadDao;
  /** @var TreeDao|MockInterface */
  private $treeDao;

  /** @var ClearedGetterTest */
  private $clearedGetterTest;

  public function setUp()
  {
    $this->uploadDao = M::mock(UploadDao::classname());
    $this->treeDao = M::mock(TreeDao::classname());

    $container = M::mock('ContainerBuilder');
    $GLOBALS['container'] = $container;

    $container->shouldReceive('get')->with('dao.upload')->andReturn($this->uploadDao);
    $container->shouldReceive('get')->with('dao.tree')->andReturn($this->treeDao);

    $this->clearedGetterTest = new ClearedGetterTest();
  }

  public function testGetFileNames()
  {
    $uploadId = 1;
    $parentId = 112;
    $uploadTreeTableName = "ut";

    $this->uploadDao
         ->shouldReceive('getUploadtreeTableName')
         ->withArgs(array($uploadId))
         ->andReturn($uploadTreeTableName);

    $this->treeDao
         ->shouldReceive('getRealParent')
         ->withArgs(array($uploadId,$uploadTreeTableName))
         ->andReturn($parentId);

    $this->treeDao
         ->shouldReceive('getFullPath')
         ->withArgs(array(1, $uploadTreeTableName, $parentId))
         ->andReturn("a/1");

    $this->treeDao
         ->shouldReceive('getFullPath')
         ->withArgs(array(2, $uploadTreeTableName, $parentId))
         ->andReturn("a/2");

    $this->treeDao
         ->shouldReceive('getFullPath')
         ->withArgs(array(3, $uploadTreeTableName, $parentId))
         ->andReturn("a/b/1");

    $statements = $this->clearedGetterTest->getCleared($uploadId);
    $this->assertEquals(array(), $statements);
  }
}
