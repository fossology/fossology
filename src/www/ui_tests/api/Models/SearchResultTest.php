<?php
/***************************************************************
 * Copyright (C) 2020 Siemens AG
 * Author: Gaurav Mishra <mishra.gaurav@siemens.com>
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 ***************************************************************/
/**
 * @file
 * @brief Tests for SearchResult model
 */

namespace Fossology\UI\Api\Test\Models;

use Fossology\UI\Api\Models\Upload;
use Fossology\UI\Api\Models\SearchResult;
use Fossology\UI\Api\Models\Hash;

/**
 * @class SearchResultTest
 * @brief Test for SearchResult model
 */
class SearchResultTest extends \PHPUnit\Framework\TestCase
{
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
