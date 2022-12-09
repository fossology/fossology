<?php
/*
 SPDX-FileCopyrightText: © 2014-2017 Siemens AG
 Author: Steffen Weber

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Dao;

use Exception;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Test\TestPgDb;
use Mockery as M;

class FolderDaoTest extends \PHPUnit\Framework\TestCase
{
  /** @var TestPgDb */
  private $testDb;
  /** @var DbManager */
  private $dbManager;
  /** @var FolderDao */
  private $folderDao;

  protected function setUp() : void
  {
    $this->testDb = new TestPgDb();
    $this->dbManager = $this->testDb->getDbManager();
    $userDao = M::mock('Fossology\Lib\Dao\UserDao');
    $uploadDao = M::mock('Fossology\Lib\Dao\UploadDao');
    $this->folderDao = new FolderDao($this->dbManager, $userDao, $uploadDao);

    $this->testDb->createPlainTables(array('folder','foldercontents'));
    $this->testDb->createSequences(array('folder_folder_pk_seq','foldercontents_foldercontents_pk_seq'));
    $this->testDb->createConstraints(array('folder_pkey','foldercontents_pkey'));
    $this->testDb->alterTables(array('folder','foldercontents'));

    $this->assertCountBefore = \Hamcrest\MatcherAssert::getCount();
  }

  protected function tearDown() : void
  {
    $this->addToAssertionCount(\Hamcrest\MatcherAssert::getCount()-$this->assertCountBefore);
    $this->testDb = null;
    $this->dbManager = null;
  }

  public function testGetAllFolderIds()
  {
    $this->testDb->insertData(array('folder'));
    assertThat(sizeof($this->folderDao->getAllFolderIds())>0);
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

  public function testGetFolderChildFolders()
  {
    $this->folderDao->ensureTopLevelFolder();
    $folderA = $this->folderDao->insertFolder('A', '/A', FolderDao::TOP_LEVEL);
    $this->folderDao->insertFolder('B', '/A/B', $folderA);
    $this->folderDao->insertFolder('C', '/C', FolderDao::TOP_LEVEL);
    assertThat($this->folderDao->getFolderChildFolders(FolderDao::TOP_LEVEL),is(arrayWithSize(2)));
  }

  public function testMoveContent()
  {
    $this->folderDao->ensureTopLevelFolder();
    $folderA = $this->folderDao->insertFolder($folderName='A', '/A', FolderDao::TOP_LEVEL);
    $folderB = $this->folderDao->insertFolder($folderName='B', '/A/B', $folderA);
    $fc = $this->dbManager->getSingleRow('SELECT foldercontents_pk FROM foldercontents WHERE child_id=$1',
            array($folderB),__METHOD__.'.needs.the.foldercontent_pk');
    $this->folderDao->moveContent($fc['foldercontents_pk'], FolderDao::TOP_LEVEL);
    assertThat($this->folderDao->getFolderChildFolders(FolderDao::TOP_LEVEL),is(arrayWithSize(2)));
  }

  public function testMoveContentShouldFailIfCyclesAreProduced()
  {
    $this->expectException(Exception::class);
    $this->folderDao->ensureTopLevelFolder();
    $folderA = $this->folderDao->insertFolder($folderName='A', '/A', FolderDao::TOP_LEVEL);
    $folderB = $this->folderDao->insertFolder($folderName='B', '/A/B', $folderA);
    $fc = $this->dbManager->getSingleRow('SELECT foldercontents_pk FROM foldercontents WHERE child_id=$1',
            array($folderA),__METHOD__.'.needs.the.foldercontent_pk');
    $this->folderDao->moveContent($fc['foldercontents_pk'], $folderB);
  }

  public function testCopyContent()
  {
    $this->folderDao->ensureTopLevelFolder();
    $folderA = $this->folderDao->insertFolder($folderName='A', '/A', FolderDao::TOP_LEVEL);
    $folderB = $this->folderDao->insertFolder($folderName='B', '/A/B', $folderA);
    $this->folderDao->insertFolder($folderName='C', '/C', FolderDao::TOP_LEVEL);
    $fc = $this->dbManager->getSingleRow('SELECT foldercontents_pk FROM foldercontents WHERE child_id=$1',
            array($folderB),__METHOD__.'.needs.the.foldercontent_pk');
    $this->folderDao->copyContent($fc['foldercontents_pk'], FolderDao::TOP_LEVEL);
    assertThat($this->folderDao->getFolderChildFolders($folderA),is(arrayWithSize(1)));
    assertThat($this->folderDao->getFolderChildFolders(FolderDao::TOP_LEVEL),is(arrayWithSize(3)));
  }

  public function testGetRemovableContents()
  {
    $this->folderDao->ensureTopLevelFolder();
    $folderA = $this->folderDao->insertFolder($folderName='A', '/A', FolderDao::TOP_LEVEL);
    $this->folderDao->insertFolder('B', '/A/B', $folderA);
    $folderC = $this->folderDao->insertFolder('C', '/C', FolderDao::TOP_LEVEL);
    assertThat($this->folderDao->getRemovableContents($folderA),arrayWithSize(0));
    $this->dbManager->insertTableRow('foldercontents',array('foldercontents_mode'=> FolderDao::MODE_UPLOAD,'parent_fk'=>$folderA,'child_id'=>$folderC));
    assertThat($this->folderDao->getRemovableContents($folderA),arrayWithSize(0));
    $this->dbManager->insertTableRow('foldercontents',array('foldercontents_mode'=> FolderDao::MODE_FOLDER,'parent_fk'=>$folderA,'child_id'=>$folderC));
    assertThat($this->folderDao->getRemovableContents($folderA),arrayWithSize(1));
  }

  public function testGetFolder()
  {
    $this->folderDao->ensureTopLevelFolder();
    $goodFolder = $this->folderDao->getFolder(FolderDao::TOP_LEVEL);
    assertThat($goodFolder, is(anInstanceOf(\Fossology\Lib\Data\Folder\Folder::class)));
    assertThat($goodFolder->getId(), equalTo(FolderDao::TOP_LEVEL));
    $badFolder = $this->folderDao->getFolder(987);
    assertThat($badFolder, is(nullValue()));
  }
}
