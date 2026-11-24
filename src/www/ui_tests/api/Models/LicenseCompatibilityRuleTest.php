<?php
/*
 SPDX-FileCopyrightText: © 2026 Harshit Gandhi <gandhiharshit716@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @file
 * @brief Tests for LicenseCompatibilityRule model
 */

namespace Fossology\UI\Api\Test\Models;

use Fossology\UI\Api\Models\ApiVersion;
use Fossology\UI\Api\Models\LicenseCompatibilityRule;
use PHPUnit\Framework\TestCase;

/**
 * @class LicenseCompatibilityRuleTest
 * @brief Tests for LicenseCompatibilityRule model
 */
class LicenseCompatibilityRuleTest extends TestCase
{
  /**
   * @brief Get a rule row as returned by CompatibilityDao
   * @return array
   */
  private function getRuleRow()
  {
    return [
      'lr_pk' => "4",
      'first_rf_fk' => "306",
      'second_rf_fk' => null,
      'first_rf_shortname' => "MIT",
      'second_rf_shortname' => null,
      'first_type' => "Permissive",
      'second_type' => "Strong Copyleft",
      'comment' => "A permissive license is compatible with a copyleft license",
      'compatibility' => "t"
    ];
  }

  /**
   * @brief Get a new model object holding the values of getRuleRow()
   * @return LicenseCompatibilityRule
   */
  private function getRule()
  {
    return new LicenseCompatibilityRule(4, 306, "MIT", null, null,
      "Permissive", "Strong Copyleft",
      "A permissive license is compatible with a copyleft license", true);
  }

  /**
   * @test
   * -# Test the constructor of LicenseCompatibilityRule
   */
  public function testConstructor()
  {
    $this->assertInstanceOf(LicenseCompatibilityRule::class, $this->getRule());
  }

  /**
   * @test
   * -# Test LicenseCompatibilityRule::fromArray()
   * -# Check if the values of the DB row are casted as expected
   */
  public function testFromArray()
  {
    $rule = LicenseCompatibilityRule::fromArray($this->getRuleRow());

    $this->assertSame(4, $rule->getId());
    $this->assertSame(306, $rule->getFirstLicenseId());
    $this->assertEquals("MIT", $rule->getFirstLicenseName());
    $this->assertNull($rule->getSecondLicenseId());
    $this->assertNull($rule->getSecondLicenseName());
    $this->assertEquals("Permissive", $rule->getFirstType());
    $this->assertEquals("Strong Copyleft", $rule->getSecondType());
    $this->assertEquals(
      "A permissive license is compatible with a copyleft license",
      $rule->getComment());
    $this->assertTrue($rule->getCompatibility());
  }

  /**
   * @test
   * -# Test LicenseCompatibilityRule::fromArray() for an incompatible rule
   * -# Check if the compatibility is false
   */
  public function testFromArrayWithFalseCompatibility()
  {
    $row = $this->getRuleRow();
    $row['compatibility'] = "f";

    $this->assertFalse(
      LicenseCompatibilityRule::fromArray($row)->getCompatibility());
  }

  /**
   * @test
   * -# Test the setters of LicenseCompatibilityRule
   * -# Check if the getters return the new values
   */
  public function testSetters()
  {
    $rule = $this->getRule();
    $rule->setId(5);
    $rule->setFirstLicenseId(188);
    $rule->setFirstLicenseName("GPL-2.0-only");
    $rule->setSecondLicenseId(306);
    $rule->setSecondLicenseName("MIT");
    $rule->setFirstType("Strong Copyleft");
    $rule->setSecondType("Permissive");
    $rule->setComment("New description");
    $rule->setCompatibility(false);

    $this->assertEquals(5, $rule->getId());
    $this->assertEquals(188, $rule->getFirstLicenseId());
    $this->assertEquals("GPL-2.0-only", $rule->getFirstLicenseName());
    $this->assertEquals(306, $rule->getSecondLicenseId());
    $this->assertEquals("MIT", $rule->getSecondLicenseName());
    $this->assertEquals("Strong Copyleft", $rule->getFirstType());
    $this->assertEquals("Permissive", $rule->getSecondType());
    $this->assertEquals("New description", $rule->getComment());
    $this->assertFalse($rule->getCompatibility());
  }

  /**
   * @test
   * -# Test LicenseCompatibilityRule::getArray() for version 1
   * -# Check if the keys are in snake_case
   */
  public function testGetArrayV1()
  {
    $expected = [
      'id' => 4,
      'first_license_id' => 306,
      'first_license_name' => "MIT",
      'second_license_id' => null,
      'second_license_name' => null,
      'first_type' => "Permissive",
      'second_type' => "Strong Copyleft",
      'comment' => "A permissive license is compatible with a copyleft license",
      'compatibility' => true
    ];

    $this->assertEquals($expected,
      $this->getRule()->getArray(ApiVersion::V1));
  }

  /**
   * @test
   * -# Test LicenseCompatibilityRule::getArray() for version 2
   * -# Check if the keys are in camelCase
   */
  public function testGetArrayV2()
  {
    $expected = [
      'id' => 4,
      'firstLicenseId' => 306,
      'firstLicenseName' => "MIT",
      'secondLicenseId' => null,
      'secondLicenseName' => null,
      'firstType' => "Permissive",
      'secondType' => "Strong Copyleft",
      'comment' => "A permissive license is compatible with a copyleft license",
      'compatibility' => true
    ];

    $this->assertEquals($expected,
      $this->getRule()->getArray(ApiVersion::V2));
  }

  /**
   * @test
   * -# Test LicenseCompatibilityRule::getJSON()
   * -# Check if the JSON matches the array of the same version
   */
  public function testGetJSON()
  {
    $rule = $this->getRule();

    $this->assertEquals(json_encode($rule->getArray(ApiVersion::V2)),
      $rule->getJSON(ApiVersion::V2));
  }
}
