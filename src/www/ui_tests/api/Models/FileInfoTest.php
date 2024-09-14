<?php
/*
 SPDX-FileCopyrightText: © 2024 Valens NIYONSENGA <valensniyonsenga2003@gmail.com>
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
  /**
   * @test
   * -# Test for FileInfo::getArray() when API version is V1
   * -# Create expected array and update test object to match it
   * -# Get the array from object and match with expected array
   */
  public function testDataFormatV1()
  {
    $this->testDataFormat(ApiVersion::V1);
  }

  /**
   * @test
   * -# Test for FileInfo::getArray() when API version is V2
   * -# Create expected array and update test object to match it
   * -# Get the array from object and match with expected array
   */
  public function testDataFormatV2()
  {
    $this->testDataFormat(ApiVersion::V2);
  }

  /**
   * -# Test the data format returned by FileInfo::getArray($version) model
   * @param $version API version to test (V1 or V2)
   */
  private function testDataFormat($version)
  {
    $viewInfo = (object) ['key' => 'view_value'];
    $metaInfo = (object) ['key' => 'meta_value'];
    $packageInfo = (object) ['key' => 'package_value'];
    $tagInfo = (object) ['key' => 'tag_value'];
    $reuseInfo = (object) ['key' => 'reuse_value'];

    if ($version == ApiVersion::V1) {
      $expectedArray = [
        'view_info' => $viewInfo,
        'meta_info' => $metaInfo,
        'package_info' => $packageInfo,
        'tag_info' => $tagInfo,
        'reuse_info' => $reuseInfo
      ];
    } else {
      $expectedArray = [
        'viewInfo' => $viewInfo,
        'metaInfo' => $metaInfo,
        'packageInfo' => $packageInfo,
        'tagInfo' => $tagInfo,
        'reuseInfo' => $reuseInfo
      ];
    }

    $fileInfo = new FileInfo($viewInfo, $metaInfo, $packageInfo, $tagInfo, $reuseInfo);

    $this->assertEquals($expectedArray, $fileInfo->getArray($version));
  }

  /**
   * @test
   * -# Test for FileInfo::getJSON() when API version is V1
   * -# Check if the JSON representation matches the expected output
   */
  public function testGetJSONV1()
  {
    $this->testGetJSON(ApiVersion::V1);
  }

  /**
   * @test
   * -# Test for FileInfo::getJSON() when API version is V2
   * -# Check if the JSON representation matches the expected output
   */
  public function testGetJSONV2()
  {
    $this->testGetJSON(ApiVersion::V2);
  }

  /**
   * -# Test the JSON output from FileInfo::getJSON() method
   * @param $version API version to test (V1 or V2)
   */
  private function testGetJSON($version)
  {
    $viewInfo = (object) ['key' => 'view_value'];
    $metaInfo = (object) ['key' => 'meta_value'];
    $packageInfo = (object) ['key' => 'package_value'];
    $tagInfo = (object) ['key' => 'tag_value'];
    $reuseInfo = (object) ['key' => 'reuse_value'];

    if ($version == ApiVersion::V2) {
      $expectedJSON = json_encode([
        'viewInfo' => $viewInfo,
        'metaInfo' => $metaInfo,
        'packageInfo' => $packageInfo,
        'tagInfo' => $tagInfo,
        'reuseInfo' => $reuseInfo
      ]);
    } else {
      $expectedJSON = json_encode([
        'view_info' => $viewInfo,
        'meta_info' => $metaInfo,
        'package_info' => $packageInfo,
        'tag_info' => $tagInfo,
        'reuse_info' => $reuseInfo
      ]);
    }

    $fileInfo = new FileInfo($viewInfo, $metaInfo, $packageInfo, $tagInfo, $reuseInfo);

    $this->assertEquals($expectedJSON, $fileInfo->getJSON($version));
  }
}
