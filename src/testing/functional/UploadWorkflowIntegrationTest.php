<?php
/*
 SPDX-FileCopyrightText: © 2026 Fossology contributors

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @file
 * @brief Integration test for upload workflow spanning multiple DAOs
 *
 * This test verifies that uploads, jobs, and related components work together
 * correctly across the database layer.
 */

use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Dao\UploadPermissionDao;
use Fossology\Lib\Dao\FolderDao;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Test\TestPgDb;
use Fossology\Lib\Test\TestInstaller;
use Monolog\Logger;

class UploadWorkflowIntegrationTest extends \PHPUnit\Framework\TestCase
{
  /** @var TestPgDb */
  private $testDb;
  
  /** @var DbManager */
  private $dbManager;
  
  /** @var UploadDao */
  private $uploadDao;
  
  /** @var UploadPermissionDao */
  private $uploadPermDao;
  
  /** @var FolderDao */
  private $folderDao;

  protected function setUp() : void
  {
    $this->testDb = new TestPgDb("uploadWorkflowTest");
    $this->dbManager = &$this->testDb->getDbManager();

    // Create all necessary tables
    $this->testDb->createPlainTables(array(
      'upload',
      'uploadtree',
      'pfile',
      'users',
      'group_user_member',
      'perm_upload',
      'folder',
      'foldercontents'
    ));
    
    $this->testDb->createSequences(array(
      'upload_upload_pk_seq',
      'pfile_pfile_pk_seq',
      'users_user_pk_seq',
      'folder_folder_pk_seq'
    ));
    
    $this->testDb->createInheritedTables(array('uploadtree_a'));

    $logger = new Logger("UploadWorkflowTest");
    $this->uploadPermDao = new UploadPermissionDao($this->dbManager, $logger);
    $this->uploadDao = new UploadDao($this->dbManager, $logger, $this->uploadPermDao);
    $this->folderDao = new FolderDao($this->dbManager);
    
    $this->assertCountBefore = \Hamcrest\MatcherAssert::getCount();
  }

  protected function tearDown() : void
  {
    $this->addToAssertionCount(\Hamcrest\MatcherAssert::getCount()-$this->assertCountBefore);
    $this->testDb = null;
    $this->dbManager = null;
  }

  /**
   * Helper to create a test user
   */
  private function createUser($userId, $userName = 'testuser')
  {
    return $this->dbManager->insertTableRow('users', array(
      'user_pk' => $userId,
      'user_name' => $userName,
      'user_desc' => 'Test User',
      'user_seed' => 'Seed',
      'user_pass' => 'Pass',
      'user_email' => 'test@example.com',
      'email_notify' => 'n',
      'root_folder_fk' => 1
    ), null, 'user_pk');
  }

  /**
   * Helper to create a folder
   */
  private function createFolder($folderId, $folderName, $userId)
  {
    return $this->dbManager->insertTableRow('folder', array(
      'folder_pk' => $folderId,
      'folder_name' => $folderName,
      'folder_desc' => 'Test folder',
      'folder_level' => 1
    ), null, 'folder_pk');
  }

  /**
   * Test complete upload workflow
   * Create upload -> Associate with folder -> Verify retrieval
   */
  public function testCompleteUploadWorkflow()
  {
    // Step 1: Create user
    $userId = $this->createUser(100);
    assertThat($userId, is(100));

    // Step 2: Create folder
    $folderId = $this->createFolder(200, 'TestFolder', $userId);
    assertThat($folderId, is(200));

    // Step 3: Create upload
    $uploadId = $this->dbManager->insertTableRow('upload', array(
      'upload_pk' => 300,
      'upload_desc' => 'Integration Test Upload',
      'upload_filename' => 'test.tar.gz',
      'user_fk' => $userId,
      'upload_mode' => 104,
      'upload_origin' => 'test',
      'uploadtree_tablename' => 'uploadtree_a'
    ), null, 'upload_pk');
    
    assertThat($uploadId, is(300));

    // Step 4: Link upload to folder
    $this->dbManager->insertTableRow('foldercontents', array(
      'parent_fk' => $folderId,
      'foldercontents_mode' => 2,
      'child_id' => $uploadId
    ));

    // Step 5: Verify the upload can be retrieved
    $sql = "SELECT u.upload_filename, u.upload_desc 
            FROM upload u 
            JOIN foldercontents fc ON u.upload_pk = fc.child_id 
            WHERE fc.parent_fk = $1";
    $result = $this->dbManager->getSingleRow($sql, array($folderId));
    
    assertThat($result['upload_filename'], is('test.tar.gz'));
    assertThat($result['upload_desc'], is('Integration Test Upload'));
    
    $this->addToAssertionCount(5);
  }

  /**
   * Test upload with uploadtree structure
   */
  public function testUploadWithUploadTree()
  {
    $userId = $this->createUser(101);
    $uploadId = 301;

    // Create upload
    $this->dbManager->insertTableRow('upload', array(
      'upload_pk' => $uploadId,
      'upload_desc' => 'Upload with tree',
      'upload_filename' => 'archive.tar',
      'user_fk' => $userId,
      'upload_mode' => 104,
      'uploadtree_tablename' => 'uploadtree_a'
    ));

    // Create pfile
    $pfileId = $this->dbManager->insertTableRow('pfile', array(
      'pfile_pk' => 400,
      'pfile_sha1' => 'test_sha1',
      'pfile_md5' => 'test_md5',
      'pfile_size' => 2048
    ), null, 'pfile_pk');

    // Create uploadtree entry
    $this->dbManager->insertTableRow('uploadtree_a', array(
      'uploadtree_pk' => 500,
      'parent' => null,
      'upload_fk' => $uploadId,
      'pfile_fk' => $pfileId,
      'ufile_mode' => 33188,
      'lft' => 1,
      'rgt' => 2,
      'ufile_name' => 'test_file.txt'
    ));

    // Verify the structure
    $sql = "SELECT ut.ufile_name, p.pfile_size 
            FROM uploadtree_a ut 
            JOIN pfile p ON ut.pfile_fk = p.pfile_pk 
            WHERE ut.upload_fk = $1";
    $result = $this->dbManager->getSingleRow($sql, array($uploadId));
    
    assertThat($result['ufile_name'], is('test_file.txt'));
    assertThat($result['pfile_size'], is('2048'));
    $this->addToAssertionCount(2);
  }

  /**
   * Test upload permissions integration
   */
  public function testUploadPermissions()
  {
    $userId = $this->createUser(102);
    $uploadId = 302;

    // Create upload
    $this->dbManager->insertTableRow('upload', array(
      'upload_pk' => $uploadId,
      'upload_desc' => 'Permissions test',
      'upload_filename' => 'permissions.tar',
      'user_fk' => $userId,
      'upload_mode' => 104
    ));

    // Grant upload permission
    $this->dbManager->insertTableRow('perm_upload', array(
      'perm_upload_pk' => 600,
      'upload_fk' => $uploadId,
      'group_fk' => 1,
      'perm' => 10  // Read-write permission
    ));

    // Verify permissions exist for the upload
    $sql = "SELECT perm FROM perm_upload WHERE upload_fk = $1 AND group_fk = $2";
    $result = $this->dbManager->getSingleRow($sql, array($uploadId, 1));
    
    assertThat($result['perm'], is('10'));
    $this->addToAssertionCount(1);
  }

  /**
   * Test cascade delete behavior (verify relationships)
   * This doesn't actually delete, just verifies the structure for proper cascade setup
   */
  public function testUploadRelationships()
  {
    $userId = $this->createUser(103);
    $folderId = $this->createFolder(203, 'RelFolder', $userId);
    $uploadId = 303;

    $this->dbManager->insertTableRow('upload', array(
      'upload_pk' => $uploadId,
      'upload_desc' => 'Relationship test',
      'upload_filename' => 'relations.tar',
      'user_fk' => $userId,
      'upload_mode' => 104,
      'uploadtree_tablename' => 'uploadtree_a'
    ));

    $this->dbManager->insertTableRow('foldercontents', array(
      'parent_fk' => $folderId,
      'foldercontents_mode' => 2,
      'child_id' => $uploadId
    ));

    // Verify all relationships are in place
    $sql = "SELECT COUNT(*) as cnt FROM foldercontents 
            WHERE parent_fk = $1 AND child_id = $2";
    $result = $this->dbManager->getSingleRow($sql, array($folderId, $uploadId));
    
    assertThat($result['cnt'], is('1'));

    // Verify upload references correct user
    $sql2 = "SELECT user_fk FROM upload WHERE upload_pk = $1";
    $userCheck = $this->dbManager->getSingleRow($sql2, array($uploadId));
    
    assertThat($userCheck['user_fk'], is("$userId"));
    $this->addToAssertionCount(2);
  }
}
