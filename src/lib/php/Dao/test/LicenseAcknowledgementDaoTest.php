<?php
/*
 SPDX-FileCopyrightText: © 2022 Siemens AG
 Author: Gaurav Mishra <mishra.gaurav@siemens.com>

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @file
 * Tests for LicenseAcknowledgementDao class
 */
namespace Fossology\Lib\Dao;

use Fossology\Lib\Db\DbManager;
use PHPUnit\Framework\TestCase;
use Fossology\Lib\Test\TestPgDb;
use PHPUnit\Runner\Version as PHPUnitVersion;

/**
 * @class LicenseAcknowledgementDaoTest
 * Tests for LicenseAcknowledgementDao class
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class LicenseAcknowledgementDaoTest extends TestCase
{

  /**
   * @var integer ACKNOWLEDGEMENTS_IN_DB
   * Number of rows in license standard acknowledgements table. */
  const ACKNOWLEDGEMENTS_IN_DB = 7;

  /** @var TestPgDb $testDb
   *       Test DB */
  private $testDb;

  /** @var DbManager $dbManager
   *       DB manager to use */
  private $dbManager;

  /** @var LicenseAcknowledgementDao $licenseAcknowledgementDao
   *       LicenseAcknowledgementDao object for test */
  private $licenseAcknowledgementDao;

  /** @var int $assertCountBefore */
  private $assertCountBefore;

  private $authClass;

  protected function setUp() : void
  {
    $this->testDb = new TestPgDb("licenseacknowledgementdao");
    $this->dbManager = $this->testDb->getDbManager();
    $this->licenseAcknowledgementDao = new LicenseAcknowledgementDao($this->dbManager);
    $this->assertCountBefore = \Hamcrest\MatcherAssert::getCount();
    $this->testDb->createPlainTables(["users", "license_std_acknowledgement"]);
    $this->testDb->createSequences(["license_std_acknowledgement_la_pk_seq"]);
    $this->testDb->alterTables(["license_std_acknowledgement"]);
    $this->testDb->insertData(["users", "license_std_acknowledgement"]);
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
   * @brief Test for LicenseAcknowledgementDao::getAllAcknowledgements() with no skips
   * @test
   * -# Fetch all set and unset acknowledgements
   * -# Check the length of the array matches 7
   * -# Check some values
   */
  public function testGetAllAcknowledgementsEverything()
  {
    $allAcknowledgements = $this->licenseAcknowledgementDao->getAllAcknowledgements();
    $this->assertCount(self::ACKNOWLEDGEMENTS_IN_DB, $allAcknowledgements);
    $testData = [
      "la_pk" => 2,
      "name" => "Acknowledgement #1",
      "acknowledgement" => "This is the first acknowledgement!",
      "is_enabled" => "t"
    ];
    $this->assertTrue(in_array($testData, $allAcknowledgements),
      'Missing test data 1 in DB.');
    $testData = [
      "la_pk" => 8,
      "name" => "not-set",
      "acknowledgement" => "This acknowledgement is not set!",
      "is_enabled" => "f"
    ];
    $this->assertTrue(in_array($testData, $allAcknowledgements),
      'Missing test data 2 in DB.');
  }

  /**
   * @brief Test for LicenseAcknowledgementDao::getAllAcknowledgements() with skips
   * @test
   * -# Fetch all set acknowledgements only
   * -# Check the length of the array matches 6
   * -# Check unset acknowledgement is not in the array
   */
  public function testGetAllAcknowledgementsWithSkips()
  {
    $filteredAcknowledgements = $this->licenseAcknowledgementDao->getAllAcknowledgements(true);
    $this->assertCount(self::ACKNOWLEDGEMENTS_IN_DB - 1, $filteredAcknowledgements);
    $testData = [
      "la_pk" => 2,
      "name" => "Acknowledgement #1",
      "acknowledgement" => "This is the first acknowledgement!",
      "is_enabled" => "t"
    ];
    $this->assertTrue(in_array($testData, $filteredAcknowledgements),
      'Missing expected data in DB.');
    $testData = [
      "la_pk" => 8,
      "name" => "not-set",
      "acknowledgement" => "This acknowledgement is not set!",
      "is_enabled" => "f"
    ];
    $this->assertFalse(in_array($testData, $filteredAcknowledgements),
      'Unexpected data returned for acknowledgements.');
  }

  /**
   * @brief Test for LicenseAcknowledgementDao::updateAcknowledgement() as an admin
   * @test
   * -# Update an acknowledgement with some value
   * -# Check if the function returns true
   * -# Check if the values are actually updated in DB
   */
  public function testUpdateAcknowledgementAsAdmin()
  {
    $this->authClass->expects('isAdmin')->andReturn(true);

    $returnVal = $this->licenseAcknowledgementDao->updateAcknowledgement(2,
      "Updated acknowledgement #1", "This acknowledgement is updated!");
    $this->assertTrue($returnVal);
    $sql = "SELECT * FROM license_std_acknowledgement WHERE la_pk = 2;";
    $row = $this->dbManager->getSingleRow($sql);
    $this->assertEquals("Updated acknowledgement #1", $row["name"]);
    $this->assertEquals("This acknowledgement is updated!", $row["acknowledgement"]);

    $pattern = "/^" . date('Y-m-d') . ".*/";
    if (intval(explode('.', PHPUnitVersion::id())[0]) >= 9) {
      $this->assertMatchesRegularExpression($pattern, $row['updated']);
    } else {
      $this->assertRegExp($pattern, $row['updated']);
    }
    $this->assertEquals(2, $row['user_fk']);
  }

  /**
   * @brief Test for LicenseAcknowledgementDao::updateAcknowledgement() as non admin
   * @test
   * -# Update an acknowledgement with some value
   * -# Check if the function returns false
   * -# Check if the values are not updated in DB
   */
  public function testUpdateAcknowledgementAsNonAdmin()
  {
    $this->authClass->expects('isAdmin')->andReturn(false);

    $returnVal = $this->licenseAcknowledgementDao->updateAcknowledgement(3,
      "Updated acknowledgement #2", "This acknowledgement is updated!");
    $this->assertFalse($returnVal);
    $sql = "SELECT name, acknowledgement FROM license_std_acknowledgement WHERE la_pk = 3;";
    $row = $this->dbManager->getSingleRow($sql);
    $this->assertNotEquals("Updated acknowledgement #2", $row["name"]);
    $this->assertNotEquals("This acknowledgement is updated!", $row["acknowledgement"]);
  }

  /**
   * @brief Test for LicenseAcknowledgementDao::updateAcknowledgement() with invalid id
   * @test
   * -# Pass invalid acknowledgement id
   * -# Check if the function throws exception
   */
  public function testUpdateAcknowledgementAsAdminInvalidId()
  {
    $this->authClass->expects('isAdmin')->andReturn(true);

    $this->expectException(\UnexpectedValueException::class);
    $this->licenseAcknowledgementDao->updateAcknowledgement(-1,
      "Invalid acknowledgement #1", "This acknowledgement is invalid!");
  }

  /**
   * @brief Test for LicenseAcknowledgementDao::updateAcknowledgementFromArray() as admin
   * @test
   * -# Set user as admin
   * -# Update two acknowledgements
   * -# Check if the function returns 2
   * -# Check if the DB is updated
   */
  public function testUpdateAcknowledgementFromArrayAsAdmin()
  {
    $this->authClass->expects('isAdmin')->andReturn(true);

    $newValues = [];
    $newValues[3]['name'] = "Updated acknowledgement #2";
    $newValues[3]['acknowledgement'] = "This acknowledgement is updated!";
    $newValues[4]['name'] = "Updated acknowledgement #3";
    $newValues[4]['acknowledgement'] = "This acknowledgement is updated!";

    $returnVal = $this->licenseAcknowledgementDao->updateAcknowledgementFromArray($newValues);

    $this->assertEquals(2, $returnVal);
    $sql = "SELECT * FROM license_std_acknowledgement WHERE la_pk IN (3, 4);";
    $rows = $this->dbManager->getRows($sql);
    $id = 3;
    foreach ($rows as $row) {
      $this->assertEquals("Updated acknowledgement #" . ($id - 1), $row["name"]);
      $this->assertEquals("This acknowledgement is updated!", $row["acknowledgement"]);
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
   * @brief Test for LicenseAcknowledgementDao::updateAcknowledgementFromArray() as non admin
   * @test
   * -# Set user as non admin
   * -# Call function
   * -# Check if the function returns false
   */
  public function testUpdateAcknowledgementFromArrayAsNonAdmin()
  {
    $this->authClass->expects('isAdmin')->andReturn(false);

    $newValues = [];
    $newValues[5]['name'] = "Updated acknowledgement #4";
    $newValues[5]['acknowledgement'] = "This acknowledgement is updated!";

    $returnVal = $this->licenseAcknowledgementDao->updateAcknowledgementFromArray($newValues);

    $this->assertFalse($returnVal);
  }

  /**
   * @brief Test for LicenseAcknowledgementDao::updateAcknowledgementFromArray()
   *        with invalid id
   * @test
   * -# Set user as admin
   * -# Call function with invalid data
   * -# Check if the function throws exception
   */
  public function testUpdateAcknowledgementFromArrayInvalidId()
  {
    $this->authClass->expects('isAdmin')->andReturn(true);

    $newValues = [];
    $newValues[-1]['name'] = "Updated acknowledgement #4";
    $newValues[-1]['acknowledgement'] = "This acknowledgement is updated!";

    $this->expectException(\UnexpectedValueException::class);

    $this->licenseAcknowledgementDao->updateAcknowledgementFromArray($newValues);
  }

  /**
   * @brief Test for LicenseAcknowledgementDao::updateAcknowledgementFromArray()
   *        with missing fields
   * @test
   * -# Set user as admin
   * -# Call function with missing fields
   * -# Check if the function throws exception
   */
  public function testUpdateAcknowledgementFromArrayInvalidFields()
  {
    $this->authClass->expects('isAdmin')->andReturn(true);

    $newValues = [];
    $newValues[5]['naaaaame'] = "Updated acknowledgement #4";
    $newValues[5]['acccccknowledgement'] = "This acknowledgement is updated!";

    $this->expectException(\UnexpectedValueException::class);

    $this->licenseAcknowledgementDao->updateAcknowledgementFromArray($newValues);
  }

  /**
   * @brief Test for LicenseAcknowledgementDao::updateAcknowledgementFromArray()
   *        with empty array
   * @test
   * -# Set user as admin
   * -# Call function with empty array
   * -# Check if the function returns 0
   */
  public function testUpdateAcknowledgementFromArrayEmptyArray()
  {
    $this->authClass->expects('isAdmin')->andReturn(true);

    $newValues = [];

    $returnVal = $this->licenseAcknowledgementDao->updateAcknowledgementFromArray($newValues);
    $this->assertEquals(0, $returnVal);
  }

  /**
   * @brief Test for LicenseAcknowledgementDao::updateAcknowledgementFromArray()
   *        with empty values
   * @test
   * -# Set user as admin
   * -# Call function with empty values
   * -# Check if the function throws exception
   */
  public function testUpdateAcknowledgementFromArrayEmptyValues()
  {
    $this->authClass->expects('isAdmin')->andReturn(true);

    $newValues = [3 => []];

    $this->expectException(\UnexpectedValueException::class);

    $this->licenseAcknowledgementDao->updateAcknowledgementFromArray($newValues);
  }

  /**
   * @brief Test for LicenseAcknowledgementDao::getAcknowledgement() with valid id
   * @test
   * -# Call function with valid id
   * -# Check if the function returns string or null
   * -# Check if the function returns correct value
   */
  public function testGetAcknowledgementValid()
  {
    $acknowledgementId = 7;
    $returnVal = $this->licenseAcknowledgementDao->getAcknowledgement($acknowledgementId);

    $this->assertTrue(is_string($returnVal) || $returnVal === null);
    if ($returnVal !== null) {
      $this->assertEquals("This is the sixth acknowledgement!", $returnVal);
    }
  }

  /**
   * @brief Test for LicenseAcknowledgementDao::getAcknowledgement() with invalid id
   * @test
   * -# Call function with invalid id
   * -# Check if the function throws exception
   */
  public function testGetAcknowledgementInvalid()
  {
    $this->expectException(\UnexpectedValueException::class);

    $acknowledgementId = -1;
    $this->licenseAcknowledgementDao->getAcknowledgement($acknowledgementId);
  }

  /**
   * @brief Test for LicenseAcknowledgementDao::insertAcknowledgement() as an admin
   * @test
   * -# Add a new acknowledgement with some value
   * -# Check if the function returns integer
   * -# Check if the values are actually inserted in DB
   */
  public function testInsertAcknowledgementAsAdmin()
  {
    $this->authClass->expects('isAdmin')->andReturn(true);

    $returnVal = $this->licenseAcknowledgementDao->insertAcknowledgement(
      "Inserted acknowledgement #1", "This first inserted acknowledgement!");
    $this->assertTrue(is_numeric($returnVal));
    $this->assertEquals(1, $returnVal);
    $sql = "SELECT * FROM license_std_acknowledgement WHERE la_pk = $1;";
    $row = $this->dbManager->getSingleRow($sql, [$returnVal]);
    $this->assertEquals("Inserted acknowledgement #1", $row["name"]);
    $this->assertEquals("This first inserted acknowledgement!", $row["acknowledgement"]);
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
   * @brief Test for LicenseAcknowledgementDao::insertAcknowledgement() as non admin
   * @test
   * -# Add a new acknowledgement with some value
   * -# Check if the function returns -1
   */
  public function testInsertAcknowledgementAsNonAdmin()
  {
    $this->authClass->expects('isAdmin')->andReturn(false);

    $returnVal = $this->licenseAcknowledgementDao->insertAcknowledgement(
      "Inserted acknowledgement #1", "This first inserted acknowledgement!");
    $this->assertEquals(-1, $returnVal);
  }

  /**
   * @brief Test for LicenseAcknowledgementDao::insertAcknowledgement() with empty values
   * @test
   * -# Add a new acknowledgement with empty values
   * -# Check if the function returns -1
   */
  public function testInsertAcknowledgementEmptyValues()
  {
    $this->authClass->expects('isAdmin')->andReturn(true);

    $returnVal = $this->licenseAcknowledgementDao->insertAcknowledgement("",
      "       ");
    $this->assertEquals(-1, $returnVal);
  }

  /**
   * @brief Test for LicenseAcknowledgementDao::toggleAcknowledgement() as admin
   * @test
   * -# Toggle the acknowledgement
   * -# Check if the function returns true
   * -# Check if the values are updated in DB
   */
  public function testToggleAcknowledgementAsAdmin()
  {
    $this->authClass->expects('isAdmin')->andReturn(true);

    $returnVal = $this->licenseAcknowledgementDao->toggleAcknowledgement(5);
    $this->assertTrue($returnVal);
    $sql = "SELECT is_enabled FROM license_std_acknowledgement WHERE la_pk = 5;";
    $row = $this->dbManager->getSingleRow($sql);
    $this->assertEquals("f", $row["is_enabled"]);
  }

  /**
   * @brief Test for LicenseAcknowledgementDao::toggleAcknowledgement() as non admin
   * @test
   * -# Toggle the acknowledgement as non admin
   * -# Check if the function returns false
   */
  public function testToggleAcknowledgementAsNonAdmin()
  {
    $this->authClass->expects('isAdmin')->andReturn(false);

    $returnVal = $this->licenseAcknowledgementDao->toggleAcknowledgement(5);
    $this->assertFalse($returnVal);
  }

  /**
   * @brief Test for LicenseAcknowledgementDao::toggleAcknowledgement() bad id
   * @test
   * -# Toggle the acknowledgement with bad id
   * -# Check if the function throws an exception
   */
  public function testToggleAcknowledgementInvalidId()
  {
    $this->authClass->expects('isAdmin')->andReturn(true);

    $this->expectException(\UnexpectedValueException::class);

    $acknowledgementId = -1;
    $this->licenseAcknowledgementDao->toggleAcknowledgement($acknowledgementId);
  }
}
