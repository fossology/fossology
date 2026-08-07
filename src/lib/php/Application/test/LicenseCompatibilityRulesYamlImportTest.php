<?php
/*
 SPDX-FileCopyrightText: © 2026 Harshit Gandhi <gandhiharshit716@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Application;

use Fossology\Lib\Dao\CompatibilityDao;
use Fossology\Lib\Dao\LicenseDao;
use Fossology\Lib\Dao\UserDao;
use Fossology\Lib\Data\License;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Test\Reflectory;
use Mockery as M;

/**
 * @class LicenseCompatibilityRulesYamlImportTest
 * @brief Test for LicenseCompatibilityRulesYamlImport
 */
class LicenseCompatibilityRulesYamlImportTest extends \PHPUnit\Framework\TestCase
{
  /** @var DbManager $dbManager
   *       DbManager mock */
  private $dbManager;

  /** @var LicenseDao $licenseDao
   *       LicenseDao mock */
  private $licenseDao;

  /** @var CompatibilityDao $compatibilityDao
   *       CompatibilityDao mock */
  private $compatibilityDao;

  /** @var LicenseCompatibilityRulesYamlImport $yamlImport
   *       Object under test */
  private $yamlImport;

  /** @var int $assertCountBefore */
  private $assertCountBefore;

  /**
   * @brief One time setup for test
   * @see PHPUnit::Framework::TestCase::setUp()
   */
  protected function setUp() : void
  {
    $this->assertCountBefore = \Hamcrest\MatcherAssert::getCount();
    $this->dbManager = M::mock(DbManager::class);
    $this->licenseDao = M::mock(LicenseDao::class);
    $this->compatibilityDao = M::mock(CompatibilityDao::class);
    $this->dbManager->shouldReceive('booleanFromDb')
      ->andReturnUsing(function ($value) {
        return $value === 't';
      });
    $this->yamlImport = new LicenseCompatibilityRulesYamlImport(
      $this->dbManager, M::mock(UserDao::class), $this->licenseDao,
      $this->compatibilityDao);
  }

  /**
   * @brief Close mockery
   * @see PHPUnit::Framework::TestCase::tearDown()
   */
  protected function tearDown() : void
  {
    $this->addToAssertionCount(
      \Hamcrest\MatcherAssert::getCount() - $this->assertCountBefore);
    M::close();
  }

  /**
   * @brief Make the DB return the given rule as the currently stored one
   * @param string $compatibility Stored compatibility, 't' or 'f'
   * @param string $comment       Stored description
   */
  private function whenStoredRuleIs($compatibility, $comment)
  {
    $this->dbManager->shouldReceive('getSingleRow')
      ->andReturn(["compatibility" => $compatibility, "comment" => $comment]);
  }

  /**
   * @brief Test for LicenseCompatibilityRulesYamlImport::updateLicense() when
   *        only the compatibility changed
   * @test
   * -# Import a rule whose compatibility differs from the stored one
   * -# Check if the DAO is asked to update the compatibility
   * -# Check if the change is reported
   */
  public function testUpdateLicenseWithOnlyCompatibilityChanged()
  {
    $this->whenStoredRuleIs('t', "A rule");
    $this->compatibilityDao->shouldReceive('updateRuleFromArray')
      ->once()->with([4 => ["result" => false]])->andReturn(1);

    $log = Reflectory::invokeObjectsMethodnameWith($this->yamlImport,
      'updateLicense',
      [["compatibility" => "false", "comment" => "A rule"], 4]);

    $this->assertEquals("updated compatibility", $log);
  }

  /**
   * @brief Test for LicenseCompatibilityRulesYamlImport::updateLicense() when
   *        only the description changed
   * @test
   * -# Import a rule whose description differs from the stored one
   * -# Check if the DAO is asked to update the description
   * -# Check if the change is reported
   */
  public function testUpdateLicenseWithOnlyCommentChanged()
  {
    $this->whenStoredRuleIs('t', "A rule");
    $this->compatibilityDao->shouldReceive('updateRuleFromArray')
      ->once()->with([4 => ["comment" => "An updated rule"]])->andReturn(1);

    $log = Reflectory::invokeObjectsMethodnameWith($this->yamlImport,
      'updateLicense',
      [["compatibility" => "true", "comment" => "An updated rule"], 4]);

    $this->assertEquals("updated comment", $log);
  }

  /**
   * @brief Test for LicenseCompatibilityRulesYamlImport::updateLicense() when
   *        both values changed
   * @test
   * -# Import a rule with a new compatibility and a new description
   * -# Check if the DAO is asked to update both
   */
  public function testUpdateLicenseWithBothChanged()
  {
    $this->whenStoredRuleIs('f', "A rule");
    $this->compatibilityDao->shouldReceive('updateRuleFromArray')
      ->once()->with([4 => ["result" => true,
        "comment" => "An updated rule"]])->andReturn(1);

    $log = Reflectory::invokeObjectsMethodnameWith($this->yamlImport,
      'updateLicense',
      [["compatibility" => "true", "comment" => "An updated rule"], 4]);

    $this->assertEquals("updated compatibility, updated comment", $log);
  }

  /**
   * @brief Test for LicenseCompatibilityRulesYamlImport::updateLicense() when
   *        nothing changed
   * @test
   * -# Import a rule which matches the stored one
   * -# Check if the DAO is not called
   * -# Check if nothing is reported
   */
  public function testUpdateLicenseWithNoChange()
  {
    $this->whenStoredRuleIs('t', "A rule");
    $this->compatibilityDao->shouldNotReceive('updateRuleFromArray');

    $log = Reflectory::invokeObjectsMethodnameWith($this->yamlImport,
      'updateLicense',
      [["compatibility" => "true", "comment" => "A rule"], 4]);

    $this->assertEmpty($log);
  }

  /**
   * @brief Test for LicenseCompatibilityRulesYamlImport::handleYaml() with a
   *        license unknown to the server
   * @test
   * -# Import a rule naming a license which is not in the DB
   * -# Check if UnexpectedValueException is thrown instead of a fatal error
   */
  public function testHandleYamlWithUnknownLicense()
  {
    $this->licenseDao->shouldReceive('getLicenseByShortName')
      ->with("NotALicense", null)->andReturn(null);
    $this->expectException(\UnexpectedValueException::class);

    Reflectory::invokeObjectsMethodnameWith($this->yamlImport, 'handleYaml',
      [[
        "firstname" => "NotALicense",
        "secondname" => null,
        "firsttype" => null,
        "secondtype" => "Strong Copyleft",
        "compatibility" => "true",
        "comment" => "A rule"
      ]]);
  }

  /**
   * @brief Test for LicenseCompatibilityRulesYamlImport::handleYaml() with a
   *        missing column
   * @test
   * -# Import a rule without the compatibility column
   * -# Check if UnexpectedValueException is thrown
   */
  public function testHandleYamlWithMissingColumn()
  {
    $this->expectException(\UnexpectedValueException::class);

    Reflectory::invokeObjectsMethodnameWith($this->yamlImport, 'handleYaml',
      [[
        "firstname" => "MIT",
        "secondname" => null,
        "firsttype" => null,
        "secondtype" => "Strong Copyleft",
        "comment" => "A rule"
      ]]);
  }

  /**
   * @brief Test for LicenseCompatibilityRulesYamlImport::handleYaml() resolving
   *        the license short names
   * @test
   * -# Import a rule naming two known licenses
   * -# Check if the short names are replaced by the license ids
   */
  public function testHandleYamlResolvesLicenseIds()
  {
    $firstLicense = M::mock(License::class);
    $firstLicense->shouldReceive('getId')->andReturn(306);
    $secondLicense = M::mock(License::class);
    $secondLicense->shouldReceive('getId')->andReturn(188);
    $this->licenseDao->shouldReceive('getLicenseByShortName')
      ->with("MIT", null)->andReturn($firstLicense);
    $this->licenseDao->shouldReceive('getLicenseByShortName')
      ->with("GPL-2.0-only", null)->andReturn($secondLicense);
    // No rule matches, so a new one is inserted with the resolved ids
    $this->dbManager->shouldReceive('getSingleRow')->andReturn(false);
    $this->compatibilityDao->shouldReceive('insertRule')
      ->once()->with(306, 188, null, null, "A rule", "true")->andReturn(9);

    $this->assertEquals(9,
      Reflectory::invokeObjectsMethodnameWith($this->yamlImport, 'handleYaml',
        [[
          "firstname" => "MIT",
          "secondname" => "GPL-2.0-only",
          "firsttype" => null,
          "secondtype" => null,
          "compatibility" => "true",
          "comment" => "A rule"
        ]]));
  }
}
