<?php
/*
 SPDX-FileCopyrightText: © 2019 Siemens AG
 Author: Gaurav Mishra <mishra.gaurav@siemens.com>

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @file
 * Tests for LicenseStdCommentDao class
 */
namespace Fossology\Lib\Dao;

use Fossology\Lib\Db\DbManager;
use PHPUnit\Framework\TestCase;
use Fossology\Lib\Test\TestPgDb;
use PHPUnit\Runner\Version as PHPUnitVersion;

/**
 * @class LicenseStdCommentDaoTest
 * Tests for LicenseStdCommentDao class
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class LicenseStdCommentDaoTest extends TestCase
{

  /**
   * @var integer COMMENTS_IN_DB
   * Number of rows in license standard comments table. */
  const COMMENTS_IN_DB = 7;

  /** @var TestPgDb $testDb
   *       Test DB */
  private $testDb;

  /** @var DbManager $dbManager
   *       DB manager to use */
  private $dbManager;

  /** @var LicenseStdCommentDao $licenseStdCommentDao
   *       LicenseStdCommentDao object for test */
  private $licenseStdCommentDao;

  /** @var int $assertCountBefore */
  private $assertCountBefore;

  private $authClass;

  protected function setUp() : void
  {
    $this->testDb = new TestPgDb("licensestdcommentdao");
    $this->dbManager = $this->testDb->getDbManager();
    $this->licenseStdCommentDao = new LicenseStdCommentDao($this->dbManager);
    $this->assertCountBefore = \Hamcrest\MatcherAssert::getCount();
    $this->testDb->createPlainTables(["users", "license_std_comment"]);
    $this->testDb->createSequences(["license_std_comment_lsc_pk_seq"]);
    $this->testDb->alterTables(["license_std_comment"]);
    $this->testDb->insertData(["users", "license_std_comment"]);
    $this->authClass = \Mockery::mock('alias:Fossology\Lib\Auth\Auth');
    $this->authClass->expects('getUserId')->andReturn(2);
  }

  protected function tearDown() : void
  {
    $this->addToAssertionCount(
      \Hamcrest\MatcherAssert::getCount() - $this->assertCountBefore);
    $this->testDb = null;
    $this->dbManager = null;
  }

  /**
   * @brief Test for LicenseStdCommentDao::getAllComments() with no skips
   * @test
   * -# Fetch all set and unset comments
   * -# Check the length of the array matches 7
   * -# Check some values
   */
  public function testGetAllCommentsEverything()
  {
    $allComments = $this->licenseStdCommentDao->getAllComments();
    $this->assertCount(self::COMMENTS_IN_DB, $allComments);
    $testData = [
      "lsc_pk" => 2,
      "name" => "Test comment #1",
      "comment" => "This will be your first comment!",
      "is_enabled" => "t"
    ];
    $this->assertTrue(in_array($testData, $allComments),
      'Missing test data 1 in DB.');
    $testData = [
      "lsc_pk" => 8,
      "name" => "not-set",
      "comment" => "This comment is not set!",
      "is_enabled" => "f"
    ];
    $this->assertTrue(in_array($testData, $allComments),
      'Missing test data 2 in DB.');
  }

  /**
   * @brief Test for LicenseStdCommentDao::getAllComments() with skips
   * @test
   * -# Fetch all set comments only
   * -# Check the length of the array matches 6
   * -# Check unset comment is not in the array
   */
  public function testGetAllCommentsWithSkips()
  {
    $filteredComments = $this->licenseStdCommentDao->getAllComments(true);
    $this->assertCount(self::COMMENTS_IN_DB - 1, $filteredComments);
    $testData = [
      "lsc_pk" => 2,
      "name" => "Test comment #1",
      "comment" => "This will be your first comment!",
      "is_enabled" => "t"
    ];
    $this->assertTrue(in_array($testData, $filteredComments),
      'Missing expected data in DB.');
    $testData = [
      "lsc_pk" => 8,
      "name" => "not-set",
      "comment" => "This comment is not set!",
      "is_enabled" => "f"
    ];
    $this->assertFalse(in_array($testData, $filteredComments),
      'Unexpected data returned for comments.');
  }

  /**
   * @brief Test for LicenseStdCommentDao::updateComment() as an admin
   * @test
   * -# Update a comment with some value
   * -# Check if the function returns true
   * -# Check if the values are actually updated in DB
   */
  public function testUpdateCommentAsAdmin()
  {
    $this->authClass->expects('isAdmin')->andReturn(true);

    $returnVal = $this->licenseStdCommentDao->updateComment(2,
      "Updated comment #1", "This comment is updated!");
    $this->assertTrue($returnVal);
    $sql = "SELECT * FROM license_std_comment WHERE lsc_pk = 2;";
    $row = $this->dbManager->getSingleRow($sql);
    $this->assertEquals("Updated comment #1", $row["name"]);
    $this->assertEquals("This comment is updated!", $row["comment"]);

    $pattern = "/^" . date('Y-m-d') . ".*/";
    if (intval(explode('.', PHPUnitVersion::id())[0]) >= 9) {
      $this->assertMatchesRegularExpression($pattern, $row['updated']);
    } else {
      $this->assertRegExp($pattern, $row['updated']);
    }
    $this->assertEquals(2, $row['user_fk']);
  }

  /**
   * @brief Test for LicenseStdCommentDao::updateComment() as non admin
   * @test
   * -# Update a comment with some value
   * -# Check if the function returns false
   * -# Check if the values are not updated in DB
   */
  public function testUpdateCommentAsNonAdmin()
  {
    $this->authClass->expects('isAdmin')->andReturn(false);

    $returnVal = $this->licenseStdCommentDao->updateComment(3,
      "Updated comment #2", "This comment is updated!");
    $this->assertFalse($returnVal);
    $sql = "SELECT name, comment FROM license_std_comment WHERE lsc_pk = 3;";
    $row = $this->dbManager->getSingleRow($sql);
    $this->assertNotEquals("Updated comment #2", $row["name"]);
    $this->assertNotEquals("This comment is updated!", $row["comment"]);
  }

  /**
   * @brief Test for LicenseStdCommentDao::updateComment() with invalid id
   * @test
   * -# Pass invalid comment id
   * -# Check if the function throws exception
   */
  public function testUpdateCommentAsAdminInvalidId()
  {
    $this->authClass->expects('isAdmin')->andReturn(true);

    $this->expectException(\UnexpectedValueException::class);
    $this->licenseStdCommentDao->updateComment(- 1, "Invalid comment #1",
      "This comment is invalid!");
  }

  /**
   * @brief Test for LicenseStdCommentDao::updateCommentFromArray() as admin
   * @test
   * -# Set user as admin
   * -# Update two comments
   * -# Check if the function returns 2
   * -# Check if the DB is updated
   */
  public function testUpdateCommentFromArrayAsAdmin()
  {
    $this->authClass->expects('isAdmin')->andReturn(true);

    $newValues = [];
    $newValues[3]['name'] = "Updated comment #2";
    $newValues[3]['comment'] = "This comment is updated!";
    $newValues[4]['name'] = "Updated comment #3";
    $newValues[4]['comment'] = "This comment is updated!";

    $returnVal = $this->licenseStdCommentDao->updateCommentFromArray($newValues);

    $this->assertEquals(2, $returnVal);
    $sql = "SELECT * FROM license_std_comment WHERE lsc_pk IN (3, 4);";
    $rows = $this->dbManager->getRows($sql);
    $id = 3;
    foreach ($rows as $row) {
      $this->assertEquals("Updated comment #" . ($id - 1), $row["name"]);
      $this->assertEquals("This comment is updated!", $row["comment"]);
      $pattern = "/^" . date('Y-m-d') . ".*/";
      if (intval(explode('.', PHPUnitVersion::id())[0]) >= 9) {
        $this->assertMatchesRegularExpression($pattern, $row['updated']);
      } else {
        $this->assertRegExp($pattern, $row['updated']);
      }
      $this->assertEquals(2, $row['user_fk']);
      $id++;
    }
  }

  /**
   * @brief Test for LicenseStdCommentDao::updateCommentFromArray() as non admin
   * @test
   * -# Set user as non admin
   * -# Call function
   * -# Check if the function returns false
   */
  public function testUpdateCommentFromArrayAsNonAdmin()
  {
    $this->authClass->expects('isAdmin')->andReturn(false);

    $newValues = [];
    $newValues[5]['name'] = "Updated comment #4";
    $newValues[5]['comment'] = "This comment is updated!";

    $returnVal = $this->licenseStdCommentDao->updateCommentFromArray($newValues);

    $this->assertFalse($returnVal);
  }

  /**
   * @brief Test for LicenseStdCommentDao::updateCommentFromArray() with
   *        invalid id
   * @test
   * -# Set user as admin
   * -# Call function with invalid data
   * -# Check if the function throws exception
   */
  public function testUpdateCommentFromArrayInvalidId()
  {
    $this->authClass->expects('isAdmin')->andReturn(true);

    $newValues = [];
    $newValues[-1]['name'] = "Updated comment #4";
    $newValues[-1]['comment'] = "This comment is updated!";

    $this->expectException(\UnexpectedValueException::class);

    $this->licenseStdCommentDao->updateCommentFromArray($newValues);
  }

  /**
   * @brief Test for LicenseStdCommentDao::updateCommentFromArray() with
   *        missing fields
   * @test
   * -# Set user as admin
   * -# Call function with missing fields
   * -# Check if the function throws exception
   */
  public function testUpdateCommentFromArrayInvalidFields()
  {
    $this->authClass->expects('isAdmin')->andReturn(true);

    $newValues = [];
    $newValues[5]['naaaaame'] = "Updated comment #4";
    $newValues[5]['commmmmment'] = "This comment is updated!";

    $this->expectException(\UnexpectedValueException::class);

    $this->licenseStdCommentDao->updateCommentFromArray($newValues);
  }

  /**
   * @brief Test for LicenseStdCommentDao::updateCommentFromArray() with
   *        empty array
   * @test
   * -# Set user as admin
   * -# Call function with empty array
   * -# Check if the function returns 0
   */
  public function testUpdateCommentFromArrayEmptyArray()
  {
    $this->authClass->expects('isAdmin')->andReturn(true);

    $newValues = [];

    $returnVal = $this->licenseStdCommentDao->updateCommentFromArray($newValues);
    $this->assertEquals(0, $returnVal);
  }

  /**
   * @brief Test for LicenseStdCommentDao::updateCommentFromArray() with
   *        empty values
   * @test
   * -# Set user as admin
   * -# Call function with empty values
   * -# Check if the function throws exception
   */
  public function testUpdateCommentFromArrayEmptyValues()
  {
    $this->authClass->expects('isAdmin')->andReturn(true);

    $newValues = [3 => []];

    $this->expectException(\UnexpectedValueException::class);

    $this->licenseStdCommentDao->updateCommentFromArray($newValues);
  }

  /**
   * @brief Test for LicenseStdCommentDao::getComment() with valid id
   * @test
   * -# Call function with valid id
   * -# Check if the function returns string or null
   * -# Check if the function returns correct value
   */
  public function testGetCommentValid()
  {
    $commentId = 7;
    $returnVal = $this->licenseStdCommentDao->getComment($commentId);

    $this->assertTrue(is_string($returnVal) || $returnVal === null);
    if ($returnVal !== null) {
      $this->assertEquals("This will be the sixth comment!", $returnVal);
    }
  }

  /**
   * @brief Test for LicenseStdCommentDao::getComment() with invalid id
   * @test
   * -# Call function with invalid id
   * -# Check if the function throws exception
   */
  public function testGetCommentInvalid()
  {
    $this->expectException(\UnexpectedValueException::class);

    $commentId = -1;
    $returnVal = $this->licenseStdCommentDao->getComment($commentId);
  }

  /**
   * @brief Test for LicenseStdCommentDao::insertComment() as an admin
   * @test
   * -# Add a new comment with some value
   * -# Check if the function returns integer
   * -# Check if the values are actually inserted in DB
   */
  public function testInsertCommentAsAdmin()
  {
    $this->authClass->expects('isAdmin')->andReturn(true);

    $returnVal = $this->licenseStdCommentDao->insertComment("Inserted comment #1",
      "This first inserted comment!");
    $this->assertTrue(is_numeric($returnVal));
    $this->assertEquals(1, $returnVal);
    $sql = "SELECT * FROM license_std_comment WHERE lsc_pk = $1;";
    $row = $this->dbManager->getSingleRow($sql, [$returnVal]);
    $this->assertEquals("Inserted comment #1", $row["name"]);
    $this->assertEquals("This first inserted comment!", $row["comment"]);
    $pattern = "/^" . date('Y-m-d') . ".*/";
    if (intval(explode('.', PHPUnitVersion::id())[0]) >= 9) {
      $this->assertMatchesRegularExpression($pattern, $row['updated']);
    } else {
      $this->assertRegExp($pattern, $row['updated']);
    }
    $this->assertEquals(2, $row['user_fk']);
    $this->assertEquals("t", $row["is_enabled"]);
  }

  /**
   * @brief Test for LicenseStdCommentDao::insertComment() as non admin
   * @test
   * -# Add a new comment with some value
   * -# Check if the function returns -1
   */
  public function testInsertCommentAsNonAdmin()
  {
    $this->authClass->expects('isAdmin')->andReturn(false);

    $returnVal = $this->licenseStdCommentDao->insertComment("Inserted comment #1",
      "This first inserted comment!");
    $this->assertEquals(-1, $returnVal);
  }

  /**
   * @brief Test for LicenseStdCommentDao::insertComment() with empty values
   * @test
   * -# Add a new comment with empty values
   * -# Check if the function returns -1
   */
  public function testInsertCommentEmptyValues()
  {
    $this->authClass->expects('isAdmin')->andReturn(true);

    $returnVal = $this->licenseStdCommentDao->insertComment("",
      "       ");
    $this->assertEquals(-1, $returnVal);
  }

  /**
   * @brief Test for LicenseStdCommentDao::toggleComment() as admin
   * @test
   * -# Toggle the comment
   * -# Check if the function returns true
   * -# Check if the values are updated in DB
   */
  public function testToggleCommentAsAdmin()
  {
    $this->authClass->expects('isAdmin')->andReturn(true);

    $returnVal = $this->licenseStdCommentDao->toggleComment(5);
    $this->assertTrue($returnVal);
    $sql = "SELECT is_enabled FROM license_std_comment WHERE lsc_pk = 5;";
    $row = $this->dbManager->getSingleRow($sql);
    $this->assertEquals("f", $row["is_enabled"]);
  }

  /**
   * @brief Test for LicenseStdCommentDao::toggleComment() as non admin
   * @test
   * -# Toggle the comment as non admin
   * -# Check if the function returns false
   */
  public function testToggleCommentAsNonAdmin()
  {
    $this->authClass->expects('isAdmin')->andReturn(false);

    $returnVal = $this->licenseStdCommentDao->toggleComment(5);
    $this->assertFalse($returnVal);
  }

  /**
   * @brief Test for LicenseStdCommentDao::toggleComment() bad id
   * @test
   * -# Toggle the comment with bad id
   * -# Check if the function throws an exception
   */
  public function testToggleCommentInvalidId()
  {
    $this->authClass->expects('isAdmin')->andReturn(true);

    $this->expectException(\UnexpectedValueException::class);

    $commentId = -1;
    $returnVal = $this->licenseStdCommentDao->toggleComment($commentId);
  }
}
