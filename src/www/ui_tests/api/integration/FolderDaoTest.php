<?php
/*
 SPDX-FileCopyrightText: © 2024 Valens Niyonsenga <valensniyonsenga2003@gmail.com>
 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @file
 * @brief Tests for  FolderDao
 */
namespace Fossology\UI\Api\Test\integration;

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\FolderDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Dao\UploadPermissionDao;
use Fossology\Lib\Dao\UserDao;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Test\TestPgDb;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;

class FolderDaoTest extends TestCase
{

  /** @var DbManager */
  private $dbManager;
  /** @var UserDao */
  private $userDao;
  /** @var UploadDao */
  private $uploadDao;
  /** @var Auth */
  private $auth;

  /**
   * @brief Sets up the necessary dependencies for the test cases.
   * Initializes mock objects for database connections, upload permissions, and user operations.
   */
  protected function setUp() : void
  {
    global $SysConf;
    $SysConf = [''];
    $logger = new Logger("FolderTest");
    $this->testDb = new TestPgDb("FolderTest");
    $this->dbManager = $this->testDb->getDbManager();
    $this->uploadPermDao = new UploadPermissionDao($this->dbManager, $logger);
    $this->userDao = new UserDao($this->dbManager, $logger, $this->uploadPermDao);
    $this->uploadDao = new UploadDao($this->dbManager, $logger, $this->uploadPermDao);
    $this->folderDao = new FolderDao($this->dbManager, $this->userDao, $this->uploadDao);
    $this->auth = \Mockery::mock('alias:Auth');
    $this->auth->shouldReceive('getUserId')->andReturn(1);
  }

  /**
   * @brief Tears down the test environment by releasing database connections and mock objects.
   */
  protected function tearDown() : void
  {
    $this->testDb->fullDestruct();
    $this->testDb = null;
    $this->dbManager = null;
    $this->folderDao = null;
  }

  /**
   * @brief Creates and configures the necessary database tables for testing.
   * Prepares the required tables and sequences for Folder and related entities.
   */
  private function setUpTables()
  {
    $this->testDb->createPlainTables(array('group_user_member','groups','folder','upload_clearing','foldercontents','upload','uploadtree','license_ref','users'),false);
    $this->testDb->createSequences(array('users_user_pk_seq','folder_folder_pk_seq','foldercontents_foldercontents_pk_seq',),false);
    $this->testDb->createViews(array('folderlist'),false);
    $this->testDb->createConstraints(array('folder_pk',
      'user_pk',
      'foldercontents_pk',
      'parent_fk',
      'child_id',
      'upload_pk',
      'pfile_pk',
      'uploadtree_pk  ',
      'upload_fk',
      'group_pk',
      'group_fk',
      'group_user_member_pk',
      'rf_pk',
    ),false);
    $this->testDb->alterTables(array('folder'.'upload','foldercontents','users'),false);
    $this->testDb->createInheritedTables();
    $this->testDb->insertData(array('group','group_user_member','folder','foldercontents','upload','uploadtree','license_ref','users'), false);
    $this->testDb->insertData_license_ref();
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

  /**
   * @brief Retrieves removable contents of a folder by its ID.
   *
   * @param int $folderId The ID of the folder whose contents are to be retrieved.
   *
   * @return array List of removable contents.
   */
  public function getRemovableContents($folderId)
  {
    return $this->folderDao->getRemovableContents($folderId);
  }

  /**
   * @brief Tests if the FolderDao can detect if a top-level folder exists.
   *
   * @return void
   */
  public function testHasTopLevelFolder()
  {
    $this->setUpTables();
    $result = $this->folderDao->hasTopLevelFolder();
    $this->assertNotNull($result);
  }

  /**
   * @brief Tests the folder creation functionality.
   *
   * @return void
   */
  public function testCreateFolder()
  {
    $this->setUpTables();
    $folderName = "fossology";
    $folderDescription = "Storage for license compliance software";
    $result = $this->folderDao->createFolder($folderName, $folderDescription, 1);
    $this->assertNotNull($result);
  }

  /**
   * @brief Tests if FolderDao ensures the existence of a top-level folder.
   *
   * @return void
   */
  public function testEnsureTopLevelFolder()
  {
    $this->setUpTables();
    $this->assertEquals(null, $this->folderDao->ensureTopLevelFolder());
  }

  /**
   * @brief Tests if a folder structure contains reusable folders.
   *
   * @return void
   */
  public function testIsWithoutReusableFolders()
  {
    $folderStructure = [
      [
        'reuse' => [
          ['count' => 0]
        ]
      ],
    ];
    $isWithoutReusableFolders = $this->folderDao->isWithoutReusableFolders($folderStructure);
    $this->assertTrue($isWithoutReusableFolders);
  }

  /**
   * @brief Tests if removable folder contents can be retrieved.
   *
   * @return void
   */
  public function testGetRemovableContents()
  {
    $this->setUpTables();
    $folderId = $this->folderDao->createFolder('Master folder', 'folder for master contents', 1);
    $contents = $this->folderDao->getRemovableContents($folderId);
    $this->assertNotNull($contents);
  }
  /**
   * @brief Creates a master folder for testing purposes.
   *
   * @return int The ID of the newly created folder.
   */
  private function createFolder()
  {
    $this->setUpTables();
    $folderId = $this->folderDao->createFolder('Master folder', 'folder for master contents', 1);
    return $folderId;
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
   * @brief Tests inserting folder contents.
   */
  public function testInsertFolderContents()
  {
    $parent = $this->createFolder();
    $childId = $this->folderDao->createFolder("Child folder", "child folder for testing", $parent);
    $this->folderDao->insertFolderContents($parent, 1, $childId);
    $this->assertTrue(true); // Asserts that no exceptions are thrown
  }

  /**
   * @brief Tests retrieval of folder content.
   */
  public function testGetContent()
  {
    $parentId = $this->createFolder();
    $this->folderDao->createFolder('App Repo', 'The repository of my app', $parentId);
    $contentIds = $this->getRemovableContents($parentId);
    $content = $this->getContent($contentIds[0]);
    $this->assertNotNull($content);
    $this->assertEquals($contentIds[0], $content['foldercontents_pk']);
  }

  /**
   * @brief Tests if a content is removable from the folder.
   */
  public function testIsRemovableContent()
  {
    $parentId = $this->createFolder();
    $this->folderDao->createFolder('App Repo', 'The repository of my app', $parentId);
    $contentIds = $this->getRemovableContents($parentId);
    $content = $this->getContent($contentIds[0]);
    var_dump($content);
    $expected = $this->folderDao->isRemovableContent($content['child_id'], $content['foldercontents_mode']);
    $this->assertNotNull($expected);
  }

  /**
   * @brief Tests removing folder content by ID.
   */
  public function testRemoveContentById()
  {
    $parentId = $this->createFolder();
    $this->folderDao->createFolder('App Repo', 'The repository of my app', $parentId);
    $contentIds = $this->getRemovableContents($parentId);
    $content = $this->getContent($contentIds[0]);
    var_dump($content);
    $this->folderDao->removeContentById($content['child_id'], $content['parent_fk']);
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
   * @brief Tests retrieval of a folder by its ID.
   */
  public function testGetFolder()
  {
    $folderId = $this->createFolder();
    $folder = $this->folderDao->getFolder($folderId);
    $this->assertNotNull($folder);
    $this->assertEquals($folderId, $folder->getId());
    $this->assertEquals("Master folder", $folder->getName());
    $this->assertEquals("folder for master contents", $folder->getDescription());
  }

  /**
   * @brief Tests retrieval of folder content ID by child ID and content mode.
   */
  public function testGetFolderContentsId()
  {
    $parent = $this->createFolder();
    $this->folderDao->createFolder("Child folder", "child folder for testing", $parent);
    $contentIds = $this->getRemovableContents($parent);
    $content = $this->getContent($contentIds[0]);
    $result = $this->folderDao->getFolderContentsId($content['child_id'], $content['foldercontents_mode']);
    $this->assertNotNull($result);
  }

  /**
   * @brief Tests retrieval of null content when folder content does not exist.
   */
  public function testGetFolderContentsIdNullContent()
  {
    $this->setUpTables();
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

  /**
   * @brief Tests retrieval of uploads for a child folder.
   * @todo Add proper assertions.
   */
  public function testGetFolderChildUploads()
  {
    $parent = $this->createFolder();
    $child = $this->folderDao->createFolder("Child folder", "child folder for testing", $parent);
    $uploads = $this->folderDao->getFolderUploads($child);
    $this->assertNotNull($uploads);
  }

  /**
   * @brief Tests counting uploads in a folder based on user group mapping.
   * @todo Add proper assertions.
   */
  public function testCountFolderUploads()
  {
    $userGroupMap = [
      1 => 'Admin Group',
      2 => 'Editors Group',
      3 => 'Viewers Group'
    ];

    $parent = $this->createFolder();
    $this->folderDao->createFolder("Child folder", "child folder for testing", $parent);
    $uploads = $this->folderDao->countFolderUploads($parent, $userGroupMap);
    $this->assertNotNull($uploads);
  }

  /**
   * @brief Tests retrieval of the default folder for a specific user.
   * @todo Add proper assertions.
   */
  public function testGetDefaultFolder()
  {
    $this->setUpTables();
    $folder = $this->folderDao->getDefaultFolder(2);
    $this->assertNotNull($folder);
  }

  /**
   * @brief Tests retrieval of the root folder for a specific user.
   * @todo Add proper assertions.
   */
  public function testGetRootFolder()
  {
    $this->setUpTables();
    $folder = $this->folderDao->getRootFolder(2);
    $this->assertNotNull($folder);
  }
  /**
   * @brief Tests the retrieval of a folder's structure.
   */
  public function testGetFolderStructure()
  {
    $parent = $this->createFolder();
    $this->folderDao->createFolder("Child folder", "child folder for testing", $parent);
    $expectedFolderStructure = $this->folderDao->getFolderStructure($parent);

    // Display the folder object for debugging
    var_dump($expectedFolderStructure[0]['folder']);

    $this->assertNotNull($expectedFolderStructure);
    $this->assertEquals(5, $expectedFolderStructure[0]['folder']->getId());
    $this->assertEquals("child folder for testing", $expectedFolderStructure[0]['folder']->getDescription());
    $this->assertEquals("Child folder", $expectedFolderStructure[0]['folder']->getName());
  }

  /**
   * @brief Tests the retrieval of the folder tree structure using a Common Table Expression (CTE).
   */
  public function testGetFolderTreeCte()
  {
    $parent = $this->createFolder();
    $this->folderDao->createFolder("Child folder","child folder for testing",$parent);
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
}
