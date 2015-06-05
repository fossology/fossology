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
use Fossology\Lib\Test\TestPgDb;

class FolderDaoTest extends \PHPUnit_Framework_TestCase
{
  /** @var TestPgDb */
  private $testDb;
  /** @var DbManager */
  private $dbManager;
  /** @var FolderDao */
  private $folderDao;

  public function setUp()
  {
    $this->testDb = new TestPgDb();
    $this->dbManager = $this->testDb->getDbManager();
    $this->folderDao = new FolderDao($this->dbManager);
    
    $this->testDb->createPlainTables(array('folder','foldercontents'));
    $this->testDb->createSequences(array('folder_folder_pk_seq','foldercontents_foldercontents_pk_seq'));
    $this->testDb->createConstraints(array('folder_pkey','foldercontents_pkey'));
    $this->testDb->alterTables(array('folder','foldercontents'));
  }

  public function tearDown()
  {
    $this->testDb = null;
    $this->dbManager = null;
  }


  public function testHasTopLevelFolder_yes()
  {
    $this->testDb->insertData(array('folder'));
    $htlf = $this->folderDao->hasTopLevelFolder();
    assertThat($htlf, is(TRUE));
  }

  public function testHasTopLevelFolder_no()
  {
    $htlf = $this->folderDao->hasTopLevelFolder();
    assertThat($htlf, is(FALSE));
  }

  
  public function testInsertFolder()
  {
    $folderId = $this->folderDao->insertFolder($folderName = 'three', $folderDescription = 'floor(PI)');
    assertThat($folderId, equalTo(FolderDao::TOP_LEVEL));
    $folderInfo = $this->dbManager->getSingleRow('SELECT folder_name,folder_desc FROM folder WHERE folder_pk=$1',
      array($folderId), __METHOD__);
    assertThat($folderInfo, is(array('folder_name' => $folderName, 'folder_desc' => $folderDescription)));
    
    $folderIdPlusOne = $this->folderDao->insertFolder($folderName = 'four', $folderDescription = 'ceil(PI)');
    assertThat($folderIdPlusOne, equalTo(FolderDao::TOP_LEVEL+1));

  }

  public function testInsertFolderContents()
  {
    $this->folderDao->insertFolderContents($parentId = 7, $foldercontentsMode = 2, $childId = 22);
    $contentsInfo = $this->dbManager->getSingleRow('SELECT foldercontents_mode, child_id FROM foldercontents WHERE parent_fk=$1',
      array($parentId), __METHOD__);
    assertThat($contentsInfo, is(equalTo(array('foldercontents_mode' => $foldercontentsMode, 'child_id' => $childId))));
  }


  public function testGetFolderPK()
  {
    $folderId = $this->folderDao->insertFolder($folderName = 'three', $folderDescription = 'floor(PI)');

    assertThat($this->folderDao->getFolderId('three'), is($folderId));
  }

  public function testGetFolderPK_Null()
  {
    assertThat($this->folderDao->getFolderId('three'), is(null));
  }

  public function testGetFolderWithWrongParent()
  {
    $this->folderDao->insertFolder($folderName = 'three', $folderDescription = 'floor(PI)+Epsilon',2);
    assertThat($this->folderDao->getFolderId('three'), is(null));
  }

  public function testEnsureTopLevelFolder()
  {
    $htlfFresh = $this->folderDao->hasTopLevelFolder();
    assertThat($htlfFresh, is(false));
    $this->folderDao->ensureTopLevelFolder();
    $htlfFixed = $this->folderDao->hasTopLevelFolder();
    assertThat($htlfFixed, is(true));
    $this->folderDao->ensureTopLevelFolder();
    $folders = $this->dbManager->getSingleRow('SELECT count(*) FROM folder');
    assertThat($folders['count'],is(1));
  }
  
  public function testIsWithoutReusableFolders()
  {
    assertThat($this->folderDao->isWithoutReusableFolders(array()),is(true));
    $filledFolder = array(FolderDao::REUSE_KEY=>array(1=>array('group_id'=>1,'count'=>12,'group_name'=>'one')));
    assertThat($this->folderDao->isWithoutReusableFolders(array($filledFolder)),is(false));
    $emptyFolder = array(FolderDao::REUSE_KEY=>array(1=>array('group_id'=>1,'count'=>0,'group_name'=>'one')));
    assertThat($this->folderDao->isWithoutReusableFolders(array($emptyFolder)),is(true));
    $multiAccessibleFolder = array(FolderDao::REUSE_KEY=>array(1=>array('group_id'=>1,'count'=>0,'group_name'=>'one'),
        2=>array('group_id'=>2,'count'=>20,'group_name'=>'two')));
    assertThat($this->folderDao->isWithoutReusableFolders(array($multiAccessibleFolder)),is(false));

    assertThat($this->folderDao->isWithoutReusableFolders(array($filledFolder,$emptyFolder)),is(false));
  }
}
