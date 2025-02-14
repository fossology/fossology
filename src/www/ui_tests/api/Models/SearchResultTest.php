<?php
/*
 SPDX-FileCopyrightText: © 2020 Siemens AG
 Author: Gaurav Mishra <mishra.gaurav@siemens.com>

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @file
 * @brief Tests for SearchResult model
 */

namespace Fossology\UI\Api\Test\Models;

use Fossology\UI\Api\Models\Upload;
use Fossology\UI\Api\Models\SearchResult;
use Fossology\UI\Api\Models\Hash;

use PHPUnit\Framework\TestCase;

/**
 * @class SearchResultTest
 * @brief Test for SearchResult model
 */
class SearchResultTest extends TestCase
{
  ////// Constructor Tests //////

  /**
   * Tests that the Permissions constructor initializes an instance correctly.
   *
   * @return void
   */
  public function testConstructor()
  {
    $hash = new Hash('sha1checksum', 'md5checksum', 'sha256checksum', 123123);
    $upload = new Upload(2, 'root', 3, '', 'my.tar.gz', '01-01-2020', null,
      $hash);
    $searchResult = new SearchResult($upload->getArray(), '12',
    'fileinupload.txt');
    $this->assertInstanceOf(SearchResult::class, $searchResult);
  }

  /**
   * @test
   * -# Test the data format returned by SearchResult::getArray() model
   */
  public function testDataFormat()
  {
    $hash = new Hash('sha1checksum', 'md5checksum', 'sha256checksum', 123123);
    $upload = new Upload(2, 'root', 3, '', 'my.tar.gz', '01-01-2020', null,
      $hash);
    $expectedResult = [
      'upload'        => $upload->getArray(),
      'uploadTreeId'  => 12,
      'filename'      => 'fileinupload.txt'
    ];

    $actualResult = new SearchResult($upload->getArray(), '12',
      'fileinupload.txt');

    $this->assertEquals($expectedResult, $actualResult->getArray());
  }
}
