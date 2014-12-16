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

class FolderDaoTest extends \PHPUnit_Framework_TestCase
{
  /** @var TestLiteDb */
  private $testDb;
  /** @var DbManager */
  private $dbManager;
  /** @var FolderDao */
  private $folderDao;

  public function setUp()
  {
    $this->testDb = new TestLiteDb();
    $this->dbManager = $this->testDb->getDbManager();
    $this->folderDao = new FolderDao($this->dbManager);
  }
  
  public function tearDown()
  {
    $this->testDb = null;
    $this->dbManager = null;
  }


  public function testHasTopLevelFolder_yes()
  {
    $this->testDb->createPlainTables(array('folder'));
    $this->testDb->insertData(array('folder'));
    $htlf = $this->folderDao->hasTopLevelFolder();
    assertThat($htlf, is(TRUE) );
  }
  
  public function testHasTopLevelFolder_no()
  {
    $this->testDb->createPlainTables(array('folder'));
    $htlf = $this->folderDao->hasTopLevelFolder();
    assertThat($htlf, is(FALSE) );
  }
  
  public function testInsertFolder() {
    $this->testDb->createPlainTables(array('folder'));
    $this->folderDao->insertFolder($folderId=3, $folderName='three', $folderDescription='floor(PI)');
    $folderInfo = $this->dbManager->getSingleRow('SELECT folder_name,folder_desc FROM folder WHERE folder_pk=$1',
            array($folderId), __METHOD__);
    assertThat($folderInfo, is(array('folder_name'=>$folderName,'folder_desc'=>$folderDescription)));
  }

  public function testInsertFolderContents() {
    $this->testDb->createPlainTables(array('foldercontents'));
    $this->folderDao->insertFolderContents($parentId=7, $foldercontentsMode=2, $childId=22);
    $contentsInfo = $this->dbManager->getSingleRow('SELECT foldercontents_mode, child_id FROM foldercontents WHERE parent_fk=$1',
            array($parentId), __METHOD__);
    assertThat($contentsInfo, is(equalTo(array('foldercontents_mode'=>$foldercontentsMode, 'child_id'=>$childId))));
  }
  
}
