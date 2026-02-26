<?php
/*
 SPDX-FileCopyrightText: © 2026 Harshit Gandhi <gandhiharshit716@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @file
 * Tests for CompatibilityDao class
 */
namespace Fossology\Lib\Dao;

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Test\TestPgDb;
use Mockery as M;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;

/**
 * @class CompatibilityDaoTest
 * Tests for CompatibilityDao class
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class CompatibilityDaoTest extends TestCase
{
  /** @var TestPgDb $testDb
   *       Test DB */
  private $testDb;

  /** @var DbManager $dbManager
   *       DB manager to use */
  private $dbManager;

  /** @var CompatibilityDao $compatibilityDao
   *       CompatibilityDao object for test */
  private $compatibilityDao;

  /** @var int $assertCountBefore */
  private $assertCountBefore;

  /** @var int $firstLicenseId
   *       Id of a license present in license_ref */
  private $firstLicenseId;

  /** @var int $secondLicenseId
   *       Id of another license present in license_ref */
  private $secondLicenseId;

  protected function setUp() : void
  {
    $this->testDb = new TestPgDb("compatibilitydao");
    $this->dbManager = $this->testDb->getDbManager();
    $this->assertCountBefore = \Hamcrest\MatcherAssert::getCount();

    $this->testDb->createPlainTables(["license_ref", "license_rules"]);
    $this->testDb->createSequences(["license_ref_rf_pk_seq",
      "license_rules_lr_pk_seq"]);
    $this->testDb->alterTables(["license_ref", "license_rules"]);
    $this->testDb->insertData_license_ref(2);

    $licenses = $this->dbManager->getRows(
      "SELECT rf_pk FROM license_ref ORDER BY rf_pk LIMIT 2;");
    $this->firstLicenseId = intval($licenses[0]['rf_pk']);
    $this->secondLicenseId = intval($licenses[1]['rf_pk']);

    $_SESSION[Auth::USER_LEVEL] = Auth::PERM_ADMIN;

    $this->compatibilityDao = new CompatibilityDao($this->dbManager,
      new Logger("test"), M::mock(LicenseDao::class), M::mock(AgentDao::class));
  }

  protected function tearDown() : void
  {
    $this->addToAssertionCount(
      \Hamcrest\MatcherAssert::getCount() - $this->assertCountBefore);
    M::close();
    $this->testDb = null;
    $this->dbManager = null;
  }

  /**
   * @brief Insert a rule to work on
   * @param string $comment Description of the rule
   * @param bool $result    Compatibility of the rule
   * @return int Id of the new rule
   */
  private function insertRule($comment, $result = true)
  {
    $ruleId = $this->compatibilityDao->insertRule($this->firstLicenseId, null,
      null, "Strong Copyleft", $comment, $result);
    $this->assertGreaterThan(0, $ruleId, "Unable to insert the test rule.");
    return $ruleId;
  }

  /**
   * @brief Test for CompatibilityDao::insertRule()
   * @test
   * -# Insert a rule with a boolean compatibility
   * -# Check if the values are stored as given
   */
  public function testInsertRule()
  {
    $ruleId = $this->insertRule("A rule", false);

    $rule = $this->compatibilityDao->getRuleById($ruleId);
    $this->assertEquals($this->firstLicenseId, intval($rule['first_rf_fk']));
    $this->assertNull($rule['second_rf_fk']);
    $this->assertNull($rule['first_type']);
    $this->assertEquals("Strong Copyleft", $rule['second_type']);
    $this->assertEquals("A rule", $rule['comment']);
    $this->assertEquals("f", $rule['compatibility']);
  }

  /**
   * @brief Test for CompatibilityDao::insertRule() with an empty description
   * @test
   * -# Insert a rule without a description
   * -# Check if the rule is rejected
   */
  public function testInsertRuleWithoutComment()
  {
    $this->assertEquals(-1, $this->compatibilityDao->insertRule(
      $this->firstLicenseId, null, null, "Strong Copyleft", "  ", true));
  }

  /**
   * @brief Test for CompatibilityDao::getRuleById()
   * @test
   * -# Fetch a rule which exists
   * -# Check if the license short name is resolved
   * -# Fetch a rule which does not exist
   * -# Check if null is returned
   */
  public function testGetRuleById()
  {
    $ruleId = $this->insertRule("A rule");

    $rule = $this->compatibilityDao->getRuleById($ruleId);
    $this->assertEquals($ruleId, intval($rule['lr_pk']));
    $this->assertNotEmpty($rule['first_rf_shortname']);
    $this->assertNull($rule['second_rf_shortname']);

    $this->assertNull($this->compatibilityDao->getRuleById($ruleId + 100));
  }

  /**
   * @brief Test for CompatibilityDao::getAllRules() with pagination
   * @test
   * -# Insert three rules
   * -# Fetch them one page at a time
   * -# Check if the rules are returned ordered by their id
   */
  public function testGetAllRulesPaginated()
  {
    $firstRule = $this->insertRule("First rule");
    $secondRule = $this->insertRule("Second rule");
    $this->insertRule("Third rule");

    $this->assertEquals(3, $this->compatibilityDao->getTotalRulesCount());

    $rules = $this->compatibilityDao->getAllRules(2, 0);
    $this->assertCount(2, $rules);
    $this->assertEquals($firstRule, intval($rules[0]['lr_pk']));
    $this->assertEquals($secondRule, intval($rules[1]['lr_pk']));

    $rules = $this->compatibilityDao->getAllRules(2, 2);
    $this->assertCount(1, $rules);
    $this->assertEquals("Third rule", $rules[0]['comment']);
  }

  /**
   * @brief Test for CompatibilityDao::getAllRules() with a search term
   * @test
   * -# Insert two rules with different descriptions
   * -# Search for one of them
   * -# Check if only the matching rule is returned and counted
   */
  public function testGetAllRulesWithSearchTerm()
  {
    $this->insertRule("Permissive with copyleft");
    $this->insertRule("Copyleft with copyleft");

    $this->assertEquals(1,
      $this->compatibilityDao->getTotalRulesCount("%permissive%"));
    $rules = $this->compatibilityDao->getAllRules(10, 0, "%permissive%");
    $this->assertCount(1, $rules);
    $this->assertEquals("Permissive with copyleft", $rules[0]['comment']);
  }

  /**
   * @brief Test that the search term is bound as a query parameter
   * @test
   * -# Search with a term holding a single quote
   * -# Check that the query does not break and matches nothing
   */
  public function testGetAllRulesWithQuoteInSearchTerm()
  {
    $this->insertRule("A rule");
    $searchTerm = "%' OR comment LIKE '%";

    $this->assertEquals(0,
      $this->compatibilityDao->getTotalRulesCount($searchTerm));
    $this->assertCount(0,
      $this->compatibilityDao->getAllRules(10, 0, $searchTerm));
  }

  /**
   * @brief Test for CompatibilityDao::updateRuleFromArray() with a boolean
   * @test
   * -# Update only the compatibility of a rule with a PHP boolean
   * -# Check if the new value is stored in the DB
   */
  public function testUpdateRuleCompatibilityFromBoolean()
  {
    $ruleId = $this->insertRule("A rule", true);

    $this->assertEquals(1, $this->compatibilityDao->updateRuleFromArray(
      [$ruleId => ["result" => false]]));
    $this->assertEquals("f",
      $this->compatibilityDao->getRuleById($ruleId)['compatibility']);

    $this->assertEquals(1, $this->compatibilityDao->updateRuleFromArray(
      [$ruleId => ["result" => true]]));
    $this->assertEquals("t",
      $this->compatibilityDao->getRuleById($ruleId)['compatibility']);
  }

  /**
   * @brief Test for CompatibilityDao::updateRuleFromArray() with the DB
   *        representation of a boolean
   * @test
   * -# Update the compatibility of a rule with 't' and 'f'
   * -# Check if the new value is stored in the DB
   */
  public function testUpdateRuleCompatibilityFromDbBoolean()
  {
    $ruleId = $this->insertRule("A rule", true);

    $this->assertEquals(1, $this->compatibilityDao->updateRuleFromArray(
      [$ruleId => ["result" => "f"]]));
    $this->assertEquals("f",
      $this->compatibilityDao->getRuleById($ruleId)['compatibility']);
  }

  /**
   * @brief Test for CompatibilityDao::updateRuleFromArray() on every field
   * @test
   * -# Update all the fields of a rule
   * -# Check if the new values are stored in the DB
   */
  public function testUpdateRuleFromArray()
  {
    $ruleId = $this->insertRule("A rule");

    $this->assertEquals(1, $this->compatibilityDao->updateRuleFromArray([
      $ruleId => [
        "firstLic" => null,
        "secondLic" => $this->secondLicenseId,
        "firstType" => "Permissive",
        "secondType" => null,
        "comment" => "An updated rule",
        "result" => false
      ]
    ]));

    $rule = $this->compatibilityDao->getRuleById($ruleId);
    $this->assertNull($rule['first_rf_fk']);
    $this->assertEquals($this->secondLicenseId, intval($rule['second_rf_fk']));
    $this->assertEquals("Permissive", $rule['first_type']);
    $this->assertNull($rule['second_type']);
    $this->assertEquals("An updated rule", $rule['comment']);
    $this->assertEquals("f", $rule['compatibility']);
  }

  /**
   * @brief Test for CompatibilityDao::updateRuleFromArray() with an unknown id
   * @test
   * -# Update a rule which does not exist
   * -# Check if UnexpectedValueException is thrown
   */
  public function testUpdateRuleWithUnknownId()
  {
    $this->expectException(\UnexpectedValueException::class);

    $this->compatibilityDao->updateRuleFromArray(
      [999 => ["comment" => "An updated rule"]]);
  }

  /**
   * @brief Test for CompatibilityDao::deleteRule()
   * @test
   * -# Delete an existing rule
   * -# Check if the rule is gone from the DB
   */
  public function testDeleteRule()
  {
    $ruleId = $this->insertRule("A rule");

    $this->assertTrue($this->compatibilityDao->deleteRule($ruleId));
    $this->assertNull($this->compatibilityDao->getRuleById($ruleId));
    $this->assertEquals(0, $this->compatibilityDao->getTotalRulesCount());
  }
}
