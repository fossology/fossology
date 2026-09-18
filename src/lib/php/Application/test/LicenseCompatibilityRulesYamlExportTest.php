<?php
/*
 SPDX-FileCopyrightText: © 2026 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Application;

use Fossology\Lib\Dao\CompatibilityDao;
use Fossology\Lib\Db\DbManager;
use Mockery as M;

if (!function_exists('yaml_emit')) {
  function yaml_emit($data) {
    return \Symfony\Component\Yaml\Yaml::dump($data);
  }
}

/**
 * @class LicenseCompatibilityRulesYamlExportTest
 * @brief Test for LicenseCompatibilityRulesYamlExport
 */
class LicenseCompatibilityRulesYamlExportTest extends \PHPUnit\Framework\TestCase
{
  /** @var DbManager $dbManager Mock DbManager */
  private $dbManager;

  /** @var CompatibilityDao $compatibilityDao Mock CompatibilityDao */
  private $compatibilityDao;

  /** @var LicenseCompatibilityRulesYamlExport $yamlExport Object under test */
  private $yamlExport;

  /** @var int $assertCountBefore */
  private $assertCountBefore;

  /**
   * @brief One time setup for test
   * @see PHPUnit::Framework::TestCase::setUp()
   */
  protected function setUp(): void
  {
    $this->assertCountBefore = \Hamcrest\MatcherAssert::getCount();
    $this->dbManager = M::mock(DbManager::class);
    $this->compatibilityDao = M::mock(CompatibilityDao::class);
    $this->yamlExport = new LicenseCompatibilityRulesYamlExport(
      $this->dbManager, $this->compatibilityDao);
  }

  /**
   * @brief Close mockery
   * @see PHPUnit::Framework::TestCase::tearDown()
   */
  protected function tearDown(): void
  {
    $this->addToAssertionCount(
      \Hamcrest\MatcherAssert::getCount() - $this->assertCountBefore);
    M::close();
  }

  /**
   * @brief Test for LicenseCompatibilityRulesYamlExport::createYaml() for all rules
   * @test
   * -# Call createYaml(0)
   * -# Check if rules are exported as a list
   */
  public function testCreateYamlAllRules()
  {
    $rows = [
      [
        "firstname" => "MIT",
        "secondname" => "GPL-2.0-only",
        "firsttype" => null,
        "secondtype" => null,
        "compatibility" => "true",
        "comment" => "Rule 1"
      ]
    ];
    $this->compatibilityDao->shouldReceive('getDefaultCompatibility')
      ->once()->andReturn(false);
    $this->dbManager->shouldReceive('getRows')
      ->once()->andReturn($rows);

    $actualYaml = $this->yamlExport->createYaml(0);
    $expectedYaml = yaml_emit(["default" => false, "rules" => $rows]);
    $this->assertEquals($expectedYaml, $actualYaml);
  }

  /**
   * @brief Test for LicenseCompatibilityRulesYamlExport::createYaml() for a single rule
   * @test
   * -# Call createYaml(5) with a specific rule ID
   * -# Verify that rules is wrapped in an array (list) and not a single row dictionary
   */
  public function testCreateYamlSingleRule()
  {
    $row = [
      "firstname" => "MIT",
      "secondname" => "GPL-2.0-only",
      "firsttype" => null,
      "secondtype" => null,
      "compatibility" => "true",
      "comment" => "Rule 1"
    ];
    $this->compatibilityDao->shouldReceive('getDefaultCompatibility')
      ->once()->andReturn(false);
    $this->dbManager->shouldReceive('getSingleRow')
      ->once()->andReturn($row);

    $actualYaml = $this->yamlExport->createYaml(5);
    $expectedYaml = yaml_emit(["default" => false, "rules" => [$row]]);
    $this->assertEquals($expectedYaml, $actualYaml);
  }

  /**
   * @brief Test for LicenseCompatibilityRulesYamlExport::createYaml() when single rule is not found
   * @test
   * -# Call createYaml(999) when row does not exist in DB
   * -# Verify that rules is an empty array
   */
  public function testCreateYamlSingleRuleNotFound()
  {
    $this->compatibilityDao->shouldReceive('getDefaultCompatibility')
      ->once()->andReturn(false);
    $this->dbManager->shouldReceive('getSingleRow')
      ->once()->andReturn(false);

    $actualYaml = $this->yamlExport->createYaml(999);
    $expectedYaml = yaml_emit(["default" => false, "rules" => []]);
    $this->assertEquals($expectedYaml, $actualYaml);
  }
}
