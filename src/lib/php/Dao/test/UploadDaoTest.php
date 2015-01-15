<?php
/*
Copyright (C) 2014, Siemens AG
Author: Steffen Weber

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

use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Test\TestLiteDb;
use Fossology\Lib\Data\FileTreeBounds;
use Mockery as M;

class UploadDaoTest extends \PHPUnit_Framework_TestCase
{
  /** @var TestLiteDb */
  private $testDb;
  /** @var DbManager */
  private $dbManager;

  public function setUp()
  {
    $this->testDb = new TestLiteDb();
    $this->dbManager = $this->testDb->getDbManager();
  }
  
  public function tearDown()
  {
    $this->testDb = null;
    $this->dbManager = null;
  }

  public function testGetFileTreeBounds()
  {
    $this->testDb->createPlainTables(array('uploadtree'));
    $uploadDao = new UploadDao($this->dbManager);
    global $container;
    $container = M::mock('Symfony\Component\DependencyInjection\ContainerBuilder');
    $logger = M::mock('Monolog\Logger');
    $logger->shouldReceive('addWarning');
    $container->shouldReceive('get')->with('logger')->andReturn($logger);
    /** @var FileTreeBounds */
    $badFileTreeBounds = $uploadDao->getFileTreeBounds($uploadTreeId=103);
    $this->assertInstanceOf('Fossology\Lib\Data\FileTreeBounds', $badFileTreeBounds);
    $this->assertEquals(0, $badFileTreeBounds->getLeft());  
    
    $this->dbManager->queryOnce("INSERT INTO uploadtree (uploadtree_pk, parent, upload_fk, pfile_fk, ufile_mode, lft, rgt, ufile_name)"
            . " VALUES (103, NULL, 101, 1, 33792, 1, 2, 'WXwindows.txt');",
            __METHOD__.'.insert.data');
    $fileTreeBounds = $uploadDao->getFileTreeBounds($uploadTreeId=103);
    $this->assertInstanceOf('Fossology\Lib\Data\FileTreeBounds', $fileTreeBounds);
    $this->assertEquals($expected=101, $fileTreeBounds->getUploadId());    
  }
}
