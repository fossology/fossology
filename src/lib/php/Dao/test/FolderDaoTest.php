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
    global $SysConf;
    $SysConf = [''];
    $this->testDb = new TestPgDb();
    $this->dbManager = $this->testDb->getDbManager();
    $userDao = M::mock('Fossology\Lib\Dao\UserDao');
    $uploadDao = M::mock('Fossology\Lib\Dao\UploadDao');
    $this->folderDao = new FolderDao($this->dbManager, $userDao, $uploadDao);

    $this->testDb->createPlainTables(array('group_user_member','groups','folder','upload_clearing','foldercontents','upload','uploadtree','license_ref','users'));
    $this->testDb->createSequences(array('users_user_pk_seq','folder_folder_pk_seq','foldercontents_foldercontents_pk_seq',));
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
  /**
   * @brief Creates a master folder for testing purposes.
   *
   * @return int The ID of the newly created folder.
   */
  private function createFolder()
  {
    $folderId = $this->folderDao->createFolder('Master folder', 'folder for master contents', 1);
    return $folderId;
  }
  /**
   * @brief Retrieves removable contents of a folder by its ID.
   *
   * @param int $folderId The ID of the folder whose contents are to be retrieved.
   *
   * @return array List of removable contents.
   */
  private function getRemovableContents($folderId)
  {
    return $this->folderDao->getRemovableContents($folderId);
  }
  /**
   * @brief Retrieves specific folder content by content ID.
   *
   * @param int $folderContentId The ID of the folder content to retrieve.
   *
   * @return array|null The folder content or null if not found.
   */
  private function getContent($folderContentId)
  {
    return $this->folderDao->getContent($folderContentId);
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
  /**
   * @brief Tests the retrieval of the folder tree structure using a Common Table Expression (CTE).
   */
  public function testGetFolderTreeCte()
  {
    $parent = $this->createFolder();
    $this->folderDao->createFolder('Child folder', 'child folder example', $parent);
    $actual = $this->folderDao->getFolderTreeCte($parent);
    $expected =  'WITH RECURSIVE folder_tree(folder_pk, parent_fk, folder_name, folder_desc, folder_perm, id_path, name_path, depth, cycle_detected) AS (
  SELECT
    f.folder_pk, fc.parent_fk, f.folder_name, f.folder_desc, f.folder_perm,
    ARRAY [f.folder_pk]   AS id_path,
    ARRAY [f.folder_name] AS name_path,
    0                     AS depth,
    FALSE                 AS cycle_detected
  FROM folder f LEFT JOIN foldercontents fc ON fc.foldercontents_mode=1 AND f.folder_pk=fc.child_id
  WHERE folder_pk=$1
  UNION ALL
  SELECT
    f.folder_pk, fc.parent_fk, f.folder_name, f.folder_desc, f.folder_perm,
    id_path || f.folder_pk,
    name_path || f.folder_name,
    array_length(id_path, 1),
    f.folder_pk = ANY (id_path)
  FROM folder f, foldercontents fc, folder_tree ft
  WHERE f.folder_pk=fc.child_id AND foldercontents_mode=1 AND fc.parent_fk = ft.folder_pk AND NOT cycle_detected
)';
    $this->assertNotNull($actual);
    $this->assertEquals($expected, $actual);
  }
  /**
   * @brief Tests the retrieval of the folder tree structure using a Common Table Expression (CTE) with null parent ID.
   */
  public function testGetFolderTreeCteNullParentId()
  {
    $actual = $this->folderDao->getFolderTreeCte();
    $expected = 'WITH RECURSIVE folder_tree(folder_pk, parent_fk, folder_name, folder_desc, folder_perm, id_path, name_path, depth, cycle_detected) AS (
  SELECT
    f.folder_pk, fc.parent_fk, f.folder_name, f.folder_desc, f.folder_perm,
    ARRAY [f.folder_pk]   AS id_path,
    ARRAY [f.folder_name] AS name_path,
    0                     AS depth,
    FALSE                 AS cycle_detected
  FROM folder f LEFT JOIN foldercontents fc ON fc.foldercontents_mode=1 AND f.folder_pk=fc.child_id
  WHERE folder_pk=1
  UNION ALL
  SELECT
    f.folder_pk, fc.parent_fk, f.folder_name, f.folder_desc, f.folder_perm,
    id_path || f.folder_pk,
    name_path || f.folder_name,
    array_length(id_path, 1),
    f.folder_pk = ANY (id_path)
  FROM folder f, foldercontents fc, folder_tree ft
  WHERE f.folder_pk=fc.child_id AND foldercontents_mode=1 AND fc.parent_fk = ft.folder_pk AND NOT cycle_detected
)';
    $this->assertNotNull($actual);
    $this->assertEquals($expected,$actual);
  }
  /**
   * @brief Tests retrieval of folder ID using folder name and parent ID.
   */
  public function testGetFolderId()
  {
    $parent = $this->createFolder();
    $expected = $this->folderDao->createFolder("Child folder", "child folder for testing", $parent);
    $actual = $this->folderDao->getFolderId("Child folder", $parent);
    $this->assertEquals($expected, $actual);
  }
  /**
   * @brief Tests retrieval of child folders for a given parent folder.
   */
  public function testGetChildFolders()
  {
    $parentId = $this->createFolder();
    $this->folderDao->createFolder('App Repo', 'The repository of my app', $parentId);
    $folders = $this->folderDao->getFolderChildFolders($parentId);
    var_dump($folders);
    $this->assertNotNull($folders);
    $this->assertEquals(1, $folders[2]['foldercontents_mode']);
  }
  /**
   * @brief Tests retrieval of null content when folder content does not exist.
   */
  public function testGetFolderContentsIdNullContent()
  {
    $result = $this->folderDao->getFolderContentsId(10, 0);
    $this->assertNull($result);
  }
  /**
   * @brief Tests retrieval of uploads for a specific folder.
   * @todo Add proper assertions.
   */
  public function testGetFolderUploads()
  {
    $parent = $this->createFolder();
    $this->folderDao->createFolder("Child folder", "child folder for testing", $parent);
    $uploads = $this->folderDao->getFolderUploads($parent);
    $this->assertNotNull($uploads);
  }
}
