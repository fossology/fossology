<?php
/*
 SPDX-FileCopyrightText: © 2025 Harshit Gandhi <gandhiharshit716@gmail.com>
 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @file
 * @brief Tests for Conf model
 */

namespace Fossology\UI\Api\Test\Models;

use Fossology\UI\Api\Models\Conf;
use Fossology\Lib\Data\Package\ComponentType;
use PHPUnit\Framework\TestCase;

/**
 * @class ConfTest
 * @brief Tests for Conf model
 */
class ConfTest extends TestCase
{
  /** @var array $sampleData Sample data for testing */
  private $sampleData;

  /**
   * @brief Setup test data
   */
  protected function setUp(): void
  {
    $this->sampleData = [
      "ri_reviewed" => true,
      "ri_footer" => "Test Footer",
      "ri_report_rel" => "1.0.0",
      "ri_community" => "Test Community",
      "ri_component" => "Test Component",
      "ri_version" => "1.0",
      "ri_release_date" => "2025-02-15",
      "ri_sw360_link" => "https://example.com",
      "ri_component_type" => 1,
      "ri_component_id" => "TEST-123",
      "ri_general_assesment" => "Test Assessment",
      "ri_ga_additional" => "Additional Notes",
      "ri_ga_risk" => "Low",
      "ri_ga_checkbox_selection" => "Option 1",
      "ri_spdx_selection" => "MIT",
      "ri_excluded_obligations" => json_encode(["obligation1", "obligation2"]),
      "ri_department" => "Test Department",
      "ri_depnotes" => "Department Notes",
      "ri_exportnotes" => "Export Notes",
      "ri_copyrightnotes" => "Copyright Notes",
      "ri_unifiedcolumns" => json_encode(["col1", "col2"]),
      "ri_globaldecision" => 1
    ];
  }

  ////// Constructor Tests //////

  /**
   * Tests that the Conf constructor initializes an instance correctly.
   *
   * @return void
   */
  public function testConstructor()
  {
    $conf = new Conf();
    $this->assertInstanceOf(Conf::class, $conf);
  }

  /**
   * @test
   * -# Test the data format returned by Conf::getArray()
   */
  public function testGetArray()
  {
    $conf = new Conf($this->sampleData);
    $result = $conf->getArray();

    $this->assertIsArray($result);
    $this->assertEquals(true, $result['reviewed']);
    $this->assertEquals("Test Footer", $result['footer']);
    $this->assertEquals("1.0.0", $result['reportRel']);
    $this->assertEquals("Test Community", $result['community']);
    $this->assertEquals("Test Component", $result['component']);
    $this->assertEquals("1.0", $result['version']);
    $this->assertEquals("2025-02-15", $result['releaseDate']);
    $this->assertEquals("https://example.com", $result['sw360Link']);
    $this->assertEquals(ComponentType::TYPE_MAP[1], $result['componentType']);
    $this->assertEquals("TEST-123", $result['componentId']);
    $this->assertEquals("Test Assessment", $result['generalAssesment']);
    $this->assertEquals("Additional Notes", $result['gaAdditional']);
    $this->assertEquals("Low", $result['gaRisk']);
    $this->assertEquals("Option 1", $result['gaCheckbox']);
    $this->assertEquals("MIT", $result['spdxSelection']);
    $this->assertEquals(["obligation1", "obligation2"], $result['excludedObligations']);
    $this->assertEquals("Test Department", $result['department']);
    $this->assertEquals("Department Notes", $result['depNotes']);
    $this->assertEquals("Export Notes", $result['exportNotes']);
    $this->assertEquals("Copyright Notes", $result['copyrightNotes']);
    $this->assertEquals(["col1", "col2"], $result['unifiedColumns']);
    $this->assertEquals(true, $result['globalDecision']);
  }

  /**
   * @test
   * -# Test JSON encoding through getJSON() method
   */
  public function testGetJson()
  {
    $conf = new Conf($this->sampleData);
    $result = $conf->getJSON();
    
    $this->assertIsString($result);
    $this->assertJson($result);
    
    $decodedJson = json_decode($result, true);
    $this->assertEquals($conf->getArray(), $decodedJson);
  }

  /**
   * @test
   * -# Test getKeyColumnName method
   */
  public function testGetKeyColumnName()
  {
    $conf = new Conf();
    
    $this->assertEquals("ri_reviewed", $conf->getKeyColumnName("reviewed"));
    $this->assertEquals("ri_footer", $conf->getKeyColumnName("footer"));
    $this->assertEquals("ri_component_type", $conf->getKeyColumnName("componentType"));
    $this->assertEquals("ri_globaldecision", $conf->getKeyColumnName("globalDecision"));
  }

  /**
   * @test
   * -# Test doesKeyExist method with valid keys
   */
  public function testDoesKeyExistValidKeys()
  {
    $conf = new Conf();
    
    $this->assertTrue($conf->doesKeyExist("reviewed"));
    $this->assertTrue($conf->doesKeyExist("footer"));
    $this->assertTrue($conf->doesKeyExist("componentType"));
    $this->assertTrue($conf->doesKeyExist("globalDecision"));
  }

  /**
   * @test
   * -# Test doesKeyExist method with invalid keys
   */
  public function testDoesKeyExistInvalidKeys()
  {
    $conf = new Conf();
    
    $this->assertFalse($conf->doesKeyExist("invalidKey"));
    $this->assertFalse($conf->doesKeyExist(""));
    $this->assertFalse($conf->doesKeyExist("123"));
  }
}