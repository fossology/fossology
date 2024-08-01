<?php

/*
 SPDX-FileCopyrightText: © 2024 Valens Niyonsenga <valensniyonsenga2003@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @file
 * @brief Tests for FileInfo
 */
namespace Fossology\UI\Api\Test\Models;

use Fossology\UI\Api\Models\ApiVersion;
use Fossology\UI\Api\Models\FileInfo;

class FileInfoTest extends \PHPUnit\Framework\TestCase
{

  /**
   * Provides dummy data and an instance of the FileInfo class.
   *
   * @return array An associative array containing dummy data and a FileInfo object.
   */
  public function getDummyData($version=ApiVersion::V2)
  {
    if ($version == ApiVersion::V2) {
      $data = [
        'viewInfo' => "File View info",
        'metaInfo' => "FIle meta Info",
        'packageInfo' => "File package info",
        'tagInfo' => "File tag info",
        'reuseInfo' => "File reuse info"
      ];
    } else {
      $data = [
        'view_info' => "File View info",
        'meta_info' => "FIle meta Info",
        'package_info' => "File package info",
        'tag_info' => "File tag info",
        'reuse_info' => "File reuse info"
      ];
    }

    $obj = new FileInfo("File View info","FIle meta Info","File package info","File tag info","File reuse info");

    return [
      "data" => $data,
      "obj" => $obj
    ];
  }

  /**
   * Test FileInfo:getArray() with ApiVersion::V2
   * Tests the getArray method of the FileInfo class with API version 2.
   *  - # Check if the returned array matches the expected data for version 2
   */
  public function testDataFormatV2()
  {
    $expectedArray = $this->getDummyData()['data'];
    $obj = $this->getDummyData()['obj'];
    $actual = $obj->getArray(ApiVersion::V2);
    self::assertEquals($expectedArray, $actual);
  }

  /**
   * Test FileInfo:getArray() with ApiVersion::V1
   * Tests the getArray method of the FileInfo class with API version 1.
   *  - # Check if the returned array matches the expected data for version 1
   */
  public function testDataFormatV1()
  {
    $expectedArray = $this->getDummyData(ApiVersion::V1)['data'];
    $obj = $this->getDummyData()['obj'];
    $actual = $obj->getArray(ApiVersion::V1);
    $this->assertEquals($expectedArray, $actual);
  }
}
