<?php
/*
 SPDX-FileCopyrightText: © 2025 Harshit Gandhi <gandhiharshit716@gmail.com>
 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @file
 * @brief Tests for Findings model
 */

namespace Fossology\UI\Api\Test\Models;

use Fossology\UI\Api\Models\Findings;
use PHPUnit\Framework\TestCase;

/**
 * @class FindingsTest
 * @brief Tests for Findings model
 */
class FindingsTest extends TestCase
{
  /** @var array $testData Test data */
  private $scannerData = ["GPL-2.0", "MIT"];
  private $conclusionData = ["Apache-2.0"];
  private $copyrightData = ["Copyright 2020 Siemens AG", "Copyright 2021 Example Corp"];

  ////// Constructor Tests //////

  /**
   * Tests that the Findings constructor initializes an instance correctly.
   *
   * @return void
   */
  public function testConstructor()
  {
    $findings = new Findings($this->scannerData, $this->conclusionData, $this->copyrightData);
    $this->assertInstanceOf(Findings::class, $findings);
  }

  ////// Getter Tests //////

  /**
   * @test
   * -# Test getter for scanner
   */
  public function testGetScanner()
  {
    $findings = new Findings($this->scannerData);
    $this->assertEquals($this->scannerData, $findings->getScanner());
  }

  /**
   * @test
   * -# Test getter for conclusion
   */
  public function testGetConclusion()
  {
    $findings = new Findings(null, $this->conclusionData);
    $this->assertEquals($this->conclusionData, $findings->getConclusion());
  }

  /**
   * @test
   * -# Test getter for copyright
   */
  public function testGetCopyright()
  {
    $findings = new Findings(null, null, $this->copyrightData);
    $this->assertEquals($this->copyrightData, $findings->getCopyright());
  }

    ////// Setter Tests //////

  /**
   * @test
   * -# Test setter for scanner with array data
   */
  public function testSetScannerWithArrayData()
  {
    $findings = new Findings();
    $findings->setScanner($this->scannerData);
    $this->assertEquals($this->scannerData, $findings->getScanner());
  }

  /**
   * @test
   * -# Test setter for scanner with string data
   */
  public function testSetScannerWithStringData()
  {
    $findings = new Findings();
    $scanner = "GPL-2.0";
    $findings->setScanner($scanner);
    $this->assertEquals([$scanner], $findings->getScanner());
  }

  /**
   * @test
   * -# Test setter for conclusion with array data
   */
  public function testSetConclusionWithArrayData()
  {
    $findings = new Findings();
    $findings->setConclusion($this->conclusionData);
    $this->assertEquals($this->conclusionData, $findings->getConclusion());
  }

  /**
   * @test
   * -# Test setter for conclusion with string data
   */
  public function testSetConclusionWithStringData()
  {
    $findings = new Findings();
    $conclusion = "Apache-2.0";
    $findings->setConclusion($conclusion);
    $this->assertEquals([$conclusion], $findings->getConclusion());
  }

  /**
   * @test
   * -# Test setter for copyright with array data
   */
  public function testSetCopyrightWithArrayData()
  {
    $findings = new Findings();
    $findings->setCopyright($this->copyrightData);
    $this->assertEquals($this->copyrightData, $findings->getCopyright());
  }

  /**
   * @test
   * -# Test setter for copyright with string data
   */
  public function testSetCopyrightWithStringData()
  {
    $findings = new Findings();
    $copyright = "Copyright 2020 Siemens AG";
    $findings->setCopyright($copyright);
    $this->assertEquals([$copyright], $findings->getCopyright());
  }

  /**
   * @test
   * -# Test setters preserve existing values when null is passed and values exist
   */
  public function testSettersPreserveExistingValues()
  {
    $findings = new Findings();
    $findings->setScanner($this->scannerData);
    $findings->setConclusion($this->conclusionData);
    $findings->setCopyright($this->copyrightData);

    $findings->setScanner(null);
    $findings->setConclusion(null);
    $findings->setCopyright(null);

    $this->assertEquals($this->scannerData, $findings->getScanner());
    $this->assertEquals($this->conclusionData, $findings->getConclusion());
    $this->assertEquals($this->copyrightData, $findings->getCopyright());
  }

  ////// GetArray Tests //////

  /**
   * @test
   * -# Test getArray method with full data
   */
  public function testGetArrayWithFullData()
  {
    $findings = new Findings($this->scannerData, $this->conclusionData, $this->copyrightData);
    
    $expectedArray = [
      'scanner' => $this->scannerData,
      'conclusion' => $this->conclusionData,
      'copyright' => $this->copyrightData
    ];

    $this->assertEquals($expectedArray, $findings->getArray());
  }

  /**
   * @test
   * -# Test getArray method with null values
   */
  public function testGetArrayWithNullValues()
  {
    $findings = new Findings(null, null, null);
    
    $expectedArray = [
      'scanner' => null,
      'conclusion' => null,
      'copyright' => null
    ];

    $this->assertEquals($expectedArray, $findings->getArray());
  }

  /**
   * @test
   * -# Test getArray method with mixed data types
   */
  public function testGetArrayWithMixedData()
  {
    $findings = new Findings(
      "GPL-2.0",
      ["Apache-2.0", "MIT"],
      "Copyright 2020 Siemens AG"
    );
    
    $expectedArray = [
      'scanner' => ["GPL-2.0"],
      'conclusion' => ["Apache-2.0", "MIT"],
      'copyright' => ["Copyright 2020 Siemens AG"]
    ];

    $this->assertEquals($expectedArray, $findings->getArray());
  }
}