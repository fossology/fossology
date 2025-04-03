<?php
/*
 SPDX-FileCopyrightText: © 2025 Harshit Gandhi <gandhiharshit716@gmail.com>
 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @file
 * @brief Tests for LicenseStandardComment model
 */

namespace Fossology\UI\Api\Test\Models;

use Fossology\UI\Api\Models\LicenseStandardComment;
use Fossology\UI\Api\Models\ApiVersion;
use PHPUnit\Framework\TestCase;

/**
 * @class LicenseStandardCommentTest
 * @brief Tests for LicenseStandardComment model
 */
class LicenseStandardCommentTest extends TestCase
{
  private $sampleData;

  /**
   * @brief Setup test data
   */
  protected function setUp(): void
  {
    $this->sampleData = [
      'id' => 1,
      'name' => 'Test Comment',
      'comment' => 'This is a test standard comment',
      'isEnabled' => true
    ];
  }

  ////// Constructor Tests //////

  /**
   * Tests that the LicenseStandardComment constructor initializes an instance correctly.
   *
   * @return void
   */
  public function testConstructor()
  {
    $standardComment = new LicenseStandardComment(
      $this->sampleData['id'],
      $this->sampleData['name'],
      $this->sampleData['comment'],
      $this->sampleData['isEnabled']
    );
    $this->assertInstanceOf(LicenseStandardComment::class, $standardComment);
  }

  ////// Getter Tests //////

  /**
   * @test
   * @brief Test getting the ID
   */
  public function testGetId()
  {
    $standardComment = new LicenseStandardComment($this->sampleData['id'], '', '', false);
    $this->assertEquals($this->sampleData['id'], $standardComment->getId());
  }


  /**
   * @test
   * @brief Test getting the name
   */
  public function testGetName()
  {
    $standardComment = new LicenseStandardComment(0, $this->sampleData['name'], '', false);
    $this->assertEquals($this->sampleData['name'], $standardComment->getName());
  }


  /**
   * @test
   * @brief Test getting the comment text
   */
  public function testGetComment()
  {
    $standardComment = new LicenseStandardComment(0, '', $this->sampleData['comment'], false);
    $this->assertEquals($this->sampleData['comment'], $standardComment->getComment());
  }


  /**
   * @test
   * @brief Test getting the isEnabled status
   */
  public function testGetIsEnabled()
  {
    $standardComment = new LicenseStandardComment(0, '', '', true);
    $this->assertTrue($standardComment->getIsEnabled());
  }

  ////// Setter Tests //////

  /**
   * @test
   * @brief Test setting the ID
   */
  public function testSetId()
  {
    $standardComment = new LicenseStandardComment(0, '', '', false);
    $standardComment->setId(2);
    $this->assertEquals(2, $standardComment->getId());
  }

  /**
   * @test
   * @brief Test setting the name
   */
  public function testSetName()
  {
    $standardComment = new LicenseStandardComment(0, '', '', false);
    $standardComment->setName('Updated Name');
    $this->assertEquals('Updated Name', $standardComment->getName());
  }

  /**
   * @test
   * @brief Test setting the comment text
   */
  public function testSetComment()
  {
    $standardComment = new LicenseStandardComment(0, '', '', false);
    $standardComment->setComment('Updated Comment');
    $this->assertEquals('Updated Comment', $standardComment->getComment());
  }

  /**
   * @test
   * @brief Test setting the isEnabled status
   */
  public function testSetIsEnabled()
  {
    $standardComment = new LicenseStandardComment(0, '', '', false);
    $standardComment->setIsEnabled(true);
    $this->assertTrue($standardComment->getIsEnabled());
  }

  /**
   * @test
   * @brief Test getting an array representation for API version 1
   */
  public function testGetArrayV1()
  {
    $standardComment = new LicenseStandardComment(
      $this->sampleData['id'],
      $this->sampleData['name'],
      $this->sampleData['comment'],
      $this->sampleData['isEnabled']
    );
    $expectedArray = [
      'id' => $this->sampleData['id'],
      'name' => $this->sampleData['name'],
      'comment' => $this->sampleData['comment'],
      'is_enabled' => $this->sampleData['isEnabled']
    ];
    $this->assertEquals($expectedArray, $standardComment->getArray(ApiVersion::V1));
  }

  /**
   * @test
   * @brief Test getting an array representation for API version 2
   */
  public function testGetArrayV2()
  {
    $standardComment = new LicenseStandardComment(
      $this->sampleData['id'],
      $this->sampleData['name'],
      $this->sampleData['comment'],
      $this->sampleData['isEnabled']
    );
    $expectedArray = [
      'id' => $this->sampleData['id'],
      'name' => $this->sampleData['name'],
      'comment' => $this->sampleData['comment'],
      'isEnabled' => $this->sampleData['isEnabled']
    ];
    $this->assertEquals($expectedArray, $standardComment->getArray(ApiVersion::V2));
  }

  /**
   * @test
   * @brief Test getting JSON representation for API version 1
   */
  public function testGetJSONV1()
  {
    $standardComment = new LicenseStandardComment(
      $this->sampleData['id'],
      $this->sampleData['name'],
      $this->sampleData['comment'],
      $this->sampleData['isEnabled']
    );
    $json = $standardComment->getJSON(ApiVersion::V1);
    $this->assertIsString($json);
    $this->assertEquals(json_decode($json, true), $standardComment->getArray(ApiVersion::V1));
  }

  /**
   * @test
   * @brief Test getting JSON representation for API version 2
   */
  public function testGetJSONV2()
  {
    $standardComment = new LicenseStandardComment(
      $this->sampleData['id'],
      $this->sampleData['name'],
      $this->sampleData['comment'],
      $this->sampleData['isEnabled']
    );
    $json = $standardComment->getJSON(ApiVersion::V2);
    $this->assertIsString($json);
    $this->assertEquals(json_decode($json, true), $standardComment->getArray(ApiVersion::V2));
  }

  /**
   * @test
   * @brief Test handling empty values
   */
  public function testEmptyValues()
  {
    $standardComment = new LicenseStandardComment(0, '', '', false);
    $this->assertEquals(0, $standardComment->getId());
    $this->assertEquals('', $standardComment->getName());
    $this->assertEquals('', $standardComment->getComment());
    $this->assertFalse($standardComment->getIsEnabled());
  }
}
