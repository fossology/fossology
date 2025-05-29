<?php
/*
 SPDX-FileCopyrightText: © 2025 Harshit Gandhi <gandhiharshit716@gmail.com>
 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @file
 * @brief Tests for FileInfo model
 */

namespace Fossology\UI\Api\Test\Models;

use Fossology\UI\Api\Models\FileInfo;
use Fossology\UI\Api\Models\ApiVersion;
use PHPUnit\Framework\TestCase;

/**
 * @class FileInfoTest
 * @brief Tests for FileInfo model
 */
class FileInfoTest extends TestCase
{
  /** @var array $sampleData Sample data for testing */
  private $viewInfo;
  private $metaInfo;
  private $packageInfo;
  private $tagInfo;
  private $reuseInfo;

  /**
   * @brief Setup test data
   */
  protected function setUp(): void
  {
    $this->viewInfo = [
      'item_id' => 123,
      'filename' => 'test.txt',
      'description' => 'Test file'
    ];

    $this->metaInfo = [
      'size' => 1024,
      'mime_type' => 'text/plain',
      'last_modified' => '2025-02-15'
    ];

    $this->packageInfo = [
      'name' => 'test-package',
      'version' => '1.0.0',
      'license' => 'GPL-2.0'
    ];

    $this->tagInfo = [
      'tag1' => 'value1',
      'tag2' => 'value2'
    ];

    $this->reuseInfo = [
      'reuse_status' => 'yes',
      'reuse_text' => 'Can be reused'
    ];
  }

  ////// Constructor Tests //////

  /**
   * Tests that the FilelInfo constructor initializes an instance correctly.
   *
   * @return void
   */
  public function testConstructor()
  {
    $fileInfo = new FileInfo(
      $this->viewInfo,
      $this->metaInfo,
      $this->packageInfo,
      $this->tagInfo,
      $this->reuseInfo
    );
    
    $this->assertInstanceOf(FileInfo::class, $fileInfo);
  }

  ////// Getter Tests //////

  /**
   * @test
   * -# Test the data format returned by FileInfo::getArray() for V1
   */
  public function testGetArrayV1()
  {
    $fileInfo = new FileInfo(
      $this->viewInfo,
      $this->metaInfo,
      $this->packageInfo,
      $this->tagInfo,
      $this->reuseInfo
    );

    $result = $fileInfo->getArray(ApiVersion::V1);

    $expectedArray = [
      'view_info' => $this->viewInfo,
      'meta_info' => $this->metaInfo,
      'package_info' => $this->packageInfo,
      'tag_info' => $this->tagInfo,
      'reuse_info' => $this->reuseInfo
    ];

    $this->assertEquals($expectedArray, $result);
  }

  /**
   * @test
   * -# Test the data format returned by FileInfo::getArray() for V2
   */
  public function testGetArrayV2()
  {
    $fileInfo = new FileInfo(
      $this->viewInfo,
      $this->metaInfo,
      $this->packageInfo,
      $this->tagInfo,
      $this->reuseInfo
    );

    $result = $fileInfo->getArray(ApiVersion::V2);

    $expectedArray = [
      'viewInfo' => $this->viewInfo,
      'metaInfo' => $this->metaInfo,
      'packageInfo' => $this->packageInfo,
      'tagInfo' => $this->tagInfo,
      'reuseInfo' => $this->reuseInfo
    ];

    $this->assertEquals($expectedArray, $result);
  }

  /**
   * @test
   * -# Test JSON encoding through getJSON() method for V1
   */
  public function testGetJsonV1()
  {
    $fileInfo = new FileInfo(
      $this->viewInfo,
      $this->metaInfo,
      $this->packageInfo,
      $this->tagInfo,
      $this->reuseInfo
    );
    
    $result = $fileInfo->getJSON(ApiVersion::V1);
    
    $this->assertIsString($result);
    $this->assertJson($result);
    
    $decodedJson = json_decode($result, true);
    $this->assertEquals($fileInfo->getArray(ApiVersion::V1), $decodedJson);
  }

  /**
   * @test
   * -# Test JSON encoding through getJSON() method for V2
   */
  public function testGetJsonV2()
  {
    $fileInfo = new FileInfo(
      $this->viewInfo,
      $this->metaInfo,
      $this->packageInfo,
      $this->tagInfo,
      $this->reuseInfo
    );
    
    $result = $fileInfo->getJSON(ApiVersion::V2);
    
    $this->assertIsString($result);
    $this->assertJson($result);
    
    $decodedJson = json_decode($result, true);
    $this->assertEquals($fileInfo->getArray(ApiVersion::V2), $decodedJson);
  }
}