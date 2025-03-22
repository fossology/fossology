<?php
/*
 SPDX-FileCopyrightText: © 2025 Harshit Gandhi <gandhiharshit716@gmail.com>
 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @file
 * @brief Tests for OneShot
 */
namespace Fossology\UI\Api\Test\Models;
use Fossology\UI\Api\Models\OneShot;
use Fossology\Lib\Data\Highlight;

use PHPUnit\Framework\TestCase;

/**
 * @class OneShotTest
 * @brief Tests for OneShot model
 */
class OneShotTest extends TestCase
{
  ////// Constructor Tests //////

  /**
   * Tests that the OneShot constructor initializes an instance correctly.
   *
   * @return void
   */
  public function testConstructor()
  {
    $testData = ["GPL-2.0", "MIT"];
    $testHighlight = new Highlight(0, 10, "test");
    $highlights = [$testHighlight];

    $oneShot = new OneShot($testData, $highlights);
    
    $this->assertInstanceOf(OneShot::class, $oneShot);
  }

  ////// Getter Tests //////

  /**
   * @test
   * -# Test getData getter
   */
  public function testGetData()
  {
    $testData = ["GPL-2.0", "MIT"];
    $oneShot = new OneShot($testData, []);
    $this->assertEquals($testData, $oneShot->getData());
  }

  /**
   * @test
   * -# Test getHighlights getter
   */
  public function testGetHighlights()
  {
    $testHighlight = new Highlight(0, 10, "test");
    $highlights = [$testHighlight];
    $oneShot = new OneShot([], $highlights);
    $this->assertEquals($highlights, $oneShot->getHighlights());
  }

  ////// Setters Tests //////

  /**
   * @test
   * -# Test setData setter
   */
  public function testSetData()
  {
    $oneShot = new OneShot([], []);
    $newData = ["Apache-2.0"];
    $oneShot->setData($newData);
    $this->assertEquals($newData, $oneShot->getData());
  }

  /**
   * @test
   * -# Test setHighlights setter
   */
  public function testSetHighlights()
  {
    $oneShot = new OneShot([], []);
    $newHighlight = new Highlight(5, 15, "new test");
    $newHighlights = [$newHighlight];
    $oneShot->setHighlights($newHighlights);
    $this->assertEquals($newHighlights, $oneShot->getHighlights());
  }

  /**
   * @test
   * -# Test getHighlightsArray method
   * -# Create highlights with known values
   * -# Verify the array representation matches expected format
   */
  public function testGetHighlightsArray()
  {
    $highlight1 = new Highlight(0, 10, "test1");
    $highlight2 = new Highlight(15, 25, "test2");
    $highlights = [$highlight1, $highlight2];

    $oneShot = new OneShot([], $highlights);

    $expectedArray = [
      $highlight1->getArray(),
      $highlight2->getArray()
    ];

    $this->assertEquals($expectedArray, $oneShot->getHighlightsArray());
  }

  /**
   * @test
   * -# Test getArray method with default dataType
   * -# Create OneShot object with test data
   * -# Verify the array structure matches expected format
   */
  public function testGetArray()
  {
    $testData = ["GPL-3.0"];
    $highlight = new Highlight(0, 7, "GPL");
    $highlights = [$highlight];

    $oneShot = new OneShot($testData, $highlights);

    $expectedArray = [
      'licenses' => $testData,
      'highlights' => [$highlight->getArray()]
    ];

    $this->assertEquals($expectedArray, $oneShot->getArray());
  }

  /**
   * @test
   * -# Test getArray method with custom dataType
   * -# Create OneShot object with test data
   * -# Verify the array structure with custom dataType
   */
  public function testGetArrayCustomDataType()
  {
    $testData = "Sample text";
    $highlight = new Highlight(0, 6, "Sample");
    $highlights = [$highlight];

    $oneShot = new OneShot($testData, $highlights);

    $expectedArray = [
      'text' => $testData,
      'highlights' => [$highlight->getArray()]
    ];

    $this->assertEquals($expectedArray, $oneShot->getArray('text'));
  }

  /**
   * @test
   * -# Test getJSON method
   * -# Create OneShot object with test data
   * -# Verify JSON output matches expected format
   */
  public function testGetJSON()
  {
    $testData = ["MIT"];
    $highlight = new Highlight(0, 3, "MIT");
    $highlights = [$highlight];

    $oneShot = new OneShot($testData, $highlights);

    $expectedArray = [
      'licenses' => $testData,
      'highlights' => [$highlight->getArray()]
    ];

    $this->assertEquals(json_encode($expectedArray), $oneShot->getJSON());
  }
}