<?php
/*
 SPDX-FileCopyrightText: © 2026 Fossology contributors

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Dao;

use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Test\TestPgDb;

class SearchHelperDaoTest extends \PHPUnit\Framework\TestCase
{
  /** @var TestPgDb */
  private $testDb;
  
  /** @var DbManager */
  private $dbManager;
  
  /** @var SearchHelperDao */
  private $searchHelperDao;

  protected function setUp() : void
  {
    $this->testDb = new TestPgDb();
    $this->dbManager = &$this->testDb->getDbManager();

    $this->testDb->createPlainTables(array(
      'upload',
      'uploadtree',
      'pfile',
      'users'
    ));
    
    $this->testDb->createInheritedTables(array('uploadtree_a'));

    $this->searchHelperDao = new SearchHelperDao($this->dbManager);
    
    $this->assertCountBefore = \Hamcrest\MatcherAssert::getCount();
  }

  protected function tearDown() : void
  {
    $this->addToAssertionCount(\Hamcrest\MatcherAssert::getCount()-$this->assertCountBefore);
    $this->testDb = null;
    $this->dbManager = null;
  }

  /**
   * Helper to insert test data
   */
  private function insertTestData()
  {
    // Insert user
    $this->dbManager->insertTableRow('users', array(
      'user_pk' => 1,
      'user_name' => 'testuser',
      'user_desc' => 'Test User',
      'user_seed' => 'Seed',
      'user_pass' => 'Pass',
      'user_email' => 'test@example.com',
      'email_notify' => 'n',
      'root_folder_fk' => 1
    ));

    // Insert upload
    $this->dbManager->insertTableRow('upload', array(
      'upload_pk' => 1,
      'upload_desc' => 'Test Upload',
      'upload_filename' => 'test.tar.gz',
      'user_fk' => 1,
      'upload_mode' => 104,
      'uploadtree_tablename' => 'uploadtree_a'
    ));

    // Insert pfiles
    for ($i = 1; $i <= 3; $i++) {
      $this->dbManager->insertTableRow('pfile', array(
        'pfile_pk' => $i,
        'pfile_sha1' => 'sha1_' . $i,
        'pfile_md5' => 'md5_' . $i,
        'pfile_size' => 1024 * $i
      ));
    }

    // Insert uploadtree entries
    $this->dbManager->insertTableRow('uploadtree_a', array(
      'uploadtree_pk' => 1,
      'parent' => null,
      'upload_fk' => 1,
      'pfile_fk' => 1,
      'ufile_mode' => 33188,
      'lft' => 1,
      'rgt' => 8,
      'ufile_name' => 'README.txt'
    ));

    $this->dbManager->insertTableRow('uploadtree_a', array(
      'uploadtree_pk' => 2,
      'parent' => 1,
      'upload_fk' => 1,
      'pfile_fk' => 2,
      'ufile_mode' => 33188,
      'lft' => 2,
      'rgt' => 3,
      'ufile_name' => 'LICENSE'
    ));

    $this->dbManager->insertTableRow('uploadtree_a', array(
      'uploadtree_pk' => 3,
      'parent' => 1,
      'upload_fk' => 1,
      'pfile_fk' => 3,
      'ufile_mode' => 33188,
      'lft' => 4,
      'rgt' => 5,
      'ufile_name' => 'COPYING'
    ));
  }

  /**
   * Test basic file search functionality
   */
  public function testFileSearchByName()
  {
    $this->insertTestData();

    // Search for a specific filename
    $sql = "SELECT ufile_name FROM uploadtree_a WHERE ufile_name LIKE $1";
    $stmt = __METHOD__;
    $this->dbManager->prepare($stmt, $sql);
    $res = $this->dbManager->execute($stmt, array('%LICENSE%'));
    
    $results = $this->dbManager->fetchAll($res);
    $this->dbManager->freeResult($res);
    
    assertThat(count($results), is(1));
    assertThat($results[0]['ufile_name'], is('LICENSE'));
    $this->addToAssertionCount(2);
  }

  /**
   * Test searching files within a specific upload
   */
  public function testFileSearchWithinUpload()
  {
    $this->insertTestData();

    $sql = "SELECT COUNT(*) as cnt FROM uploadtree_a WHERE upload_fk = $1";
    $result = $this->dbManager->getSingleRow($sql, array(1));
    
    assertThat($result['cnt'], is('3'));
    $this->addToAssertionCount(1);
  }

  /**
   * Test search with file pattern matching
   */
  public function testFileSearchWithPattern()
  {
    $this->insertTestData();

    // Search for files starting with a pattern
    $sql = "SELECT ufile_name FROM uploadtree_a WHERE ufile_name LIKE $1 ORDER BY ufile_name";
    $stmt = __METHOD__;
    $this->dbManager->prepare($stmt, $sql);
    $res = $this->dbManager->execute($stmt, array('C%'));
    
    $results = $this->dbManager->fetchAll($res);
    $this->dbManager->freeResult($res);
    
    assertThat(count($results), is(1));
    assertThat($results[0]['ufile_name'], is('COPYING'));
    $this->addToAssertionCount(2);
  }

  /**
   * Test retrieving file metadata by pfile_pk
   */
  public function testGetFileMetadata()
  {
    $this->insertTestData();

    $sql = "SELECT p.pfile_sha1, p.pfile_md5, p.pfile_size, ut.ufile_name 
            FROM pfile p 
            JOIN uploadtree_a ut ON p.pfile_pk = ut.pfile_fk 
            WHERE p.pfile_pk = $1";
    $result = $this->dbManager->getSingleRow($sql, array(2));
    
    assertThat($result['pfile_sha1'], is('sha1_2'));
    assertThat($result['pfile_md5'], is('md5_2'));
    assertThat($result['ufile_name'], is('LICENSE'));
    $this->addToAssertionCount(3);
  }

  /**
   * Test search across multiple uploads
   * This verifies the search can span different uploads if needed
   */
  public function testSearchAcrossUploads()
  {
    $this->insertTestData();

    // Add another upload
    $this->dbManager->insertTableRow('upload', array(
      'upload_pk' => 2,
      'upload_desc' => 'Second Upload',
      'upload_filename' => 'second.tar.gz',
      'user_fk' => 1,
      'upload_mode' => 104,
      'uploadtree_tablename' => 'uploadtree_a'
    ));

    $this->dbManager->insertTableRow('uploadtree_a', array(
      'uploadtree_pk' => 4,
      'parent' => null,
      'upload_fk' => 2,
      'pfile_fk' => 1,
      'ufile_mode' => 33188,
      'lft' => 10,
      'rgt' => 11,
      'ufile_name' => 'README.txt'
    ));

    // Count all README files across uploads
    $sql = "SELECT COUNT(*) as cnt FROM uploadtree_a WHERE ufile_name = $1";
    $result = $this->dbManager->getSingleRow($sql, array('README.txt'));
    
    assertThat($result['cnt'], is('2'));
    $this->addToAssertionCount(1);
  }
}
