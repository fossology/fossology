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
use Monolog\Logger;

class FolderDaoTest extends \PHPUnit\Framework\TestCase
{
  /** @var TestPgDb */
  private $testDb;
  /** @var DbManager */
  private $dbManager;
  /** @var FolderDao */
  private $folderDao;
  /** @var UploadPermissionDao */
  private $uploadPermissionsDao;
  /** @var UploadDao */
  private $uploadDao;

  protected function setUp() : void
  {
    global $SysConf;
    $SysConf = ['auth' => [
      'UserId' => 1
    ]];
    $logger = new Logger("FolderTest");

    $this->testDb = new TestPgDb();
    $this->dbManager = $this->testDb->getDbManager();
    $userDao = new UserDao($this->dbManager,$logger);
    $uploadPermissionsDao = new UploadPermissionDao($this->dbManager,$logger);
    $this->uploadDao = new UploadDao($this->dbManager,$logger,$uploadPermissionsDao);
    $this->folderDao = new FolderDao($this->dbManager, $userDao, $this->uploadDao);

    $this->testDb->createPlainTables(array('folder','foldercontents','groups','group_user_member','upload','upload_clearing'));
    $this->testDb->createSequences(array('folder_folder_pk_seq','foldercontents_foldercontents_pk_seq','group_user_member_group_user_member_pk_seq','group_group_pk_seq','upload_upload_pk_seq'));
    $this->testDb->createConstraints(array('folder_pkey','foldercontents_pkey'));
    $this->testDb->alterTables(array('folder','foldercontents','groups','group_user_member','upload','upload_clearing'));

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
  /**
   * @test
   * -# Test to ensure that moving content that would create a cycle produces an exception
   *    FolderDao::moveContent()
   * -# Ensure that the top-level folder and some subfolders are set up
   * -# Try moving content in a way that should produce a cycle
   * -# Assert that an exception is thrown
   */
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
  /**
   * @test
   * -# Test to copy folder content to another folder
   *    FolderDao::copyContent()
   * -# Ensure that the top-level folder and some subfolders are set up
   * -# Copy content from one folder to another
   * -# Check if the content has been copied correctly by verifying folder structures
   */
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
  /**
   * @test
   * -# Test to retrieve the contents that are removable from a specified folder
   *    FolderDao::getRemovableContents()
   * -# Ensure that initially no contents are removable from the folder
   * -# Verify that contents are still not removable after adding an upload type
   * -# Confirm that contents become removable when a folder type is added
   */
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
  /**
   * @test
   * -# Test to retrieve a folder by its ID
   *    FolderDao::getFolder()
   * -# Ensure that retrieving a valid folder returns an instance of the Folder class with the correct ID
   * -# Verify that retrieving an invalid folder ID returns null
   */
  public function testGetFolder()
  {
    $this->folderDao->ensureTopLevelFolder();
    $goodFolder = $this->folderDao->getFolder(FolderDao::TOP_LEVEL);
    assertThat($goodFolder, is(anInstanceOf(\Fossology\Lib\Data\Folder\Folder::class)));
    assertThat($goodFolder->getId(), equalTo(FolderDao::TOP_LEVEL));
    $badFolder = $this->folderDao->getFolder(987);
    assertThat($badFolder, is(nullValue()));
  }
  public function testGetFolderTreeCte()
  {
    $expected = "WITH RECURSIVE folder_tree(folder_pk, parent_fk, folder_name, folder_desc, folder_perm, id_path, name_path, depth, cycle_detected) AS (
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
)";
    $actual = $this->folderDao->getFolderTreeCte(2);
    $this->assertNotNull($actual);
    $this->assertEquals($expected,$actual);
  }
  /**
   * @test
   * -# Test to retrieve the folder tree using a recursive CTE with no specified parent ID
   *    FolderDao::getFolderTreeCte()
   * -# Verify that the generated SQL query matches the expected CTE structure
   * -# Ensure that the actual query is not null and matches the expected result
   */
  public function testGetFolderTreeCteWithNoParentId()
  {
    $expected = "WITH RECURSIVE folder_tree(folder_pk, parent_fk, folder_name, folder_desc, folder_perm, id_path, name_path, depth, cycle_detected) AS (
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
)";
    $actual = $this->folderDao->getFolderTreeCte();
    $this->assertNotNull($actual);
    $this->assertEquals($expected,$actual);
  }
  /**
   * @test
   * -# Test to retrieve the folder structure at a specific level
   *    FolderDao::getFolderStructure()
   * -# Insert a new folder and retrieve its structure
   * -# Verify that the folder structure is not null
   * -# Ensure that the folder's name and description match the expected values
   */
  public function testGetFolderStructure()
  {
    $folderId = $this->folderDao->insertFolder($folderName = 'three', $folderDescription = 'floor(PI)');
    $folderStructure = $this->folderDao->getFolderStructure(FolderDao::TOP_LEVEL);
    $folderInfo = $this->dbManager->getSingleRow('SELECT folder_name,folder_desc FROM folder WHERE folder_pk=$1',
      array($folderId), __METHOD__);
    $this->assertNotNull($folderStructure);
    $this->assertEquals($folderInfo['folder_name'], $folderStructure[0]['folder']->getName());
    $this->assertEquals($folderInfo['folder_desc'], $folderStructure[0]['folder']->getDescription());
  }
  /**
   * @test
   * -# Test to count folder uploads by user group
   *    FolderDao::countFolderUploads()
   * -# Insert data into foldercontents, upload, and upload_clearing tables
   * -# Verify the count of uploads for each user group and ensure the result matches expected values
   */
  public function testCountFolderUploads()
  {
    $parentId = 1;
    $userGroupMap = [
      1 => 'fossy',
      2 => 'default Group',
      3 => 'Group1'
    ];
    $statementName = __METHOD__;

    // Insert data into foldercontents
    $this->dbManager->prepare($statementName, "INSERT INTO foldercontents (parent_fk, child_id, foldercontents_mode) VALUES ($1, $2, $3)");
    $this->dbManager->execute($statementName, array(1, 1, 2));

    // Insert data into upload with correct upload_mode
    $this->dbManager->prepare($statementName . 'upload', "INSERT INTO upload (upload_pk, upload_mode, upload_filename) VALUES ($1, $2, $3)");
    $this->dbManager->execute($statementName . 'upload', array(1, 100, "fossology-master.zip")); // upload_mode should be 100 or 104

    // Insert data into upload_clearing
    $this->dbManager->prepare($statementName . 'upload_clearing', "INSERT INTO upload_clearing (upload_fk, group_fk) VALUES ($1, $2)");
    $this->dbManager->execute($statementName . 'upload_clearing', array(1, 1));

    $result = $this->folderDao->countFolderUploads($parentId, $userGroupMap);
    $this->assertNotNull($result);
    $this->assertEquals(1, $result['fossy']['count']);
    $this->assertEquals(1, $result['fossy']['group_id']);
    $this->assertEquals('fossy', $result['fossy']['group_name']);
  }
  /**
   * @test
   * -# Test to retrieve child uploads for a specific folder
   *    FolderDao::getFolderChildUploads()
   * -# Insert data into foldercontents, upload, and upload_clearing tables
   * -# Check if the result matches the expected structure and values
   */
  public function testGetFolderChildUploads()
  {
    $parentId = 1;
    $trustGroupId = 1;

    $statementName = __METHOD__;

    // Insert data into foldercontents
    $this->dbManager->prepare($statementName . '_foldercontents', "INSERT INTO foldercontents (parent_fk, child_id, foldercontents_mode) VALUES ($1, $2, $3)");
    $this->dbManager->execute($statementName . '_foldercontents', array($parentId, 1, FolderDao::MODE_UPLOAD));

    // Insert data into upload
    $this->dbManager->prepare($statementName . '_upload', "INSERT INTO upload (upload_pk, upload_mode, upload_filename) VALUES ($1, $2, $3)");
    $this->dbManager->execute($statementName . '_upload', array(1, 100, 'sample-upload.zip'));

    // Insert data into upload_clearing
    $this->dbManager->prepare($statementName . '_upload_clearing', "INSERT INTO upload_clearing (upload_fk, group_fk) VALUES ($1, $2)");
    $this->dbManager->execute($statementName . '_upload_clearing', array(1, $trustGroupId));

    // Call the method to test
    $result = $this->folderDao->getFolderChildUploads($parentId, $trustGroupId);

    // Expected result structure (simplified for example)
    $expectedResult = [
       array(
         'upload_pk' => '1',
         'upload_mode' => '100',
         'upload_filename' => 'sample-upload.zip',
         'group_fk' => '1',
         'foldercontents_pk' => '1',
         'upload_desc' => null,
         'user_fk' => null,
         'upload_ts' => $result[0]['upload_ts'],
         'pfile_fk' => null,
         'upload_origin' => null,
         'uploadtree_tablename' => 'uploadtree_a',
         'expire_date' => null,
         'expire_action' => null,
         'public_perm' => null,
         'upload_fk' => '1',
         'assignee' => '1',
         'status_fk' => '1',
         'status_comment' => null,
         'priority' => null
       )
    ];
    $this->assertEquals($expectedResult, $result);
  }
  /**
   * @test
   * -# Test to create a new folder
   *    FolderDao::createFolder()
   * -# Check if the folder is created with the correct name and description
   * -# Ensure that the folder information is not empty
   */
  public function testCreateFolder()
  {
    $folderId = $this->folderDao->createFolder("Test repo","Testing repository",FolderDao::TOP_LEVEL);
    $folderInfo = $this->dbManager->getSingleRow('SELECT folder_name,folder_desc FROM folder WHERE folder_pk=$1',
      array($folderId), __METHOD__);
    $this->assertEquals(array('folder_name' => 'Test repo', 'folder_desc' => 'Testing repository'), $folderInfo);
    $this->assertNotEmpty($folderInfo);
  }
  /**
   * @test
   * -# Test to get folder content by ID
   *    FolderDao::getContent()
   * -# Insert a folder content entry
   * -# Check if the retrieved content is not null
   * -# Check if the retrieved content matches the expected values
   */
  public function testGetContent()
  {
    $statementName = __METHOD__;

    // Insert data into foldercontents
    $this->dbManager->prepare($statementName . '_foldercontents', "INSERT INTO foldercontents (parent_fk, child_id, foldercontents_mode) VALUES ($1, $2, $3) returning foldercontents_pk");
    $res = $this->dbManager->execute($statementName . '_foldercontents', array(1, 1, FolderDao::MODE_UPLOAD));
    $row = $this->dbManager->fetchArray($res);
    $content = $this->folderDao->getContent($row['foldercontents_pk']);
    $this->assertNotNull($content);
    $this->assertEquals(array('foldercontents_pk' => 1, "parent_fk" => 1, "foldercontents_mode" =>2, "child_id" => 1), $content);
  }
  /**
   * @test
   * -# Test to successfully remove a removable folder content
   *    FolderDao::removeContent()
   * -# Insert a removable folder content entry
   * -# Check if the content is successfully removed by asserting true
   */
  public function testRemoveContentRemovable()
  {
    $statementName = __METHOD__;
    $this->dbManager->prepare($statementName, "INSERT INTO foldercontents (parent_fk, child_id, foldercontents_mode) VALUES ($1, $2, $3) returning foldercontents_pk");
    $this->dbManager->execute($statementName , array(1, 1, FolderDao::MODE_UPLOAD));

    $this->dbManager->prepare($statementName . '_foldercontents', "INSERT INTO foldercontents (parent_fk, child_id, foldercontents_mode) VALUES ($1, $2, $3) returning foldercontents_pk");
    $res = $this->dbManager->execute($statementName . '_foldercontents', array(1, 1, FolderDao::MODE_UPLOAD));
    $row = $this->dbManager->fetchArray($res);
    $result = $this->folderDao->removeContent($row['foldercontents_pk']);
    $this->assertTrue($result);
    $this->assertTrue($result);
  }
  /**
   * @test
   * -# Test to attempt removing a non-removable folder content
   *    FolderDao::removeContent()
   * -# Insert a folder content entry and attempt to remove it
   * -# Check if the removal fails and returns false
   */
  public function testRemoveContentNotRemovable()
  {
    $statementName = __METHOD__;
    $this->dbManager->prepare($statementName, "INSERT INTO foldercontents (parent_fk, child_id, foldercontents_mode) VALUES ($1, $2, $3) returning foldercontents_pk");
    $res = $this->dbManager->execute($statementName, array(1, 1, FolderDao::MODE_UPLOAD));
    $row = $this->dbManager->fetchArray($res);
    $result = $this->folderDao->removeContent($row['foldercontents_pk']);
    $this->assertFalse($result);
  }
  /**
   * @test
   * -# Test to remove folder content by ID
   *    FolderDao::removeContentById()
   * -# Insert a folder content entry and then remove it
   * -# Check if an exception is thrown when trying to access the removed content
   */
  public function testRemoveContentById()
  {
    $statementName = __METHOD__;
    $this->dbManager->prepare($statementName , "INSERT INTO foldercontents (parent_fk, child_id, foldercontents_mode) VALUES ($1, $2, $3) returning foldercontents_pk");
    $res = $this->dbManager->execute($statementName, array(1, 2, FolderDao::MODE_UPLOAD));
    $row = $this->dbManager->fetchArray($res);
    $this->folderDao->removeContentById(2,1);
    $this->expectException('Exception');
    $this->folderDao->getContent($row['foldercontents_pk']);
  }

  /**
   * @test
   * -# Test to get the folder content ID
   *    FolderDao::getFolderContentsId()
   * -# Check if the folder content ID is not null after insertion
   * -# Check if the returned content ID matches the expected value
   */
  public function testGetFolderContentsId()
  {
    $folderId = $this->folderDao->createFolder("Child Folder","Child folder for testing",FolderDao::TOP_LEVEL);
    $this->folderDao->insertFolderContents(1,FolderDao::MODE_UPLOAD,$folderId);
    $contentId = $this->folderDao->getFolderContentsId($folderId, FolderDao::MODE_UPLOAD);
    $this->assertNotNull($contentId);
    $this->assertEquals(2, $contentId);
  }
  /**
   * @test
   * -# Test to get folder content ID when content is not found
   *    FolderDao::getFolderContentsId()
   * -# Check if the returned content ID is null when no matching record exists
   */
  public function testGetFolderContentsIdNotFound()
  {
    $contentId = $this->folderDao->getFolderContentsId(10, FolderDao::MODE_UPLOAD);
    $this->assertNull($contentId);
  }
  /**
   * @test
   * -# Test to get all folder parent id
   *    FolderDao::getFolderParentId()
   * -# Check if the output is not null
   * -$ Check of the returned parent Id is valid
   */
  public function testFetFolderParentId()
  {
    $this->folderDao->insertFolderContents(4,FolderDao::MODE_FOLDER,FolderDao::TOP_LEVEL);
    $parent = $this->folderDao->getFolderParentId(FolderDao::TOP_LEVEL);
    $this->assertNOtNull($parent);
    $this->assertEquals(4, $parent);
  }
}
