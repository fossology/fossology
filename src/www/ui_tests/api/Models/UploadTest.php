<?php
/*
 SPDX-FileCopyrightText: © 2020 Siemens AG
 Author: Gaurav Mishra <mishra.gaurav@siemens.com>

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @file
 * @brief Tests for Upload model
 */

namespace Fossology\UI\Api\Test\Models;

use Fossology\UI\Api\Models\Upload;
use Fossology\UI\Api\Models\Hash;

/**
 * @class UploadTest
 * @brief Test for Upload model
 */
class UploadTest extends \PHPUnit\Framework\TestCase
{
  /**
   * @test
   * -# Test the data format returned by Upload::getArray() model
   */
  public function testDataFormat()
  {
    $hash = new Hash('sha1checksum', 'md5checksum', 'sha256checksum', 123123);
    $upload = new Upload(2, 'root', 3, '', 'my.tar.gz', '01-01-2020', 3, $hash);
    $expectedUpload = [
      "folderid"    => 2,
      "foldername"  => 'root',
      "id"          => 3,
      "description" => '',
      "uploadname"  => 'my.tar.gz',
      "uploaddate"  => '01-01-2020',
      "assignee"    => 3,
      "hash"        => $hash->getArray()
    ];

    $actualUpload = new Upload(2, 'root', 3, '', 'my.tar.gz', '01-01-2020', 3,
      $hash);

    $this->assertEquals($expectedUpload, $actualUpload->getArray());
  }
}
