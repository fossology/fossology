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

use Fossology\UI\Api\Models\Hash;
use Fossology\UI\Api\Models\Upload;
use Fossology\UI\Api\Models\ApiVersion;


/**
 * @class UploadTest
 * @brief Test for Upload model
 */
class UploadTest extends \PHPUnit\Framework\TestCase
{
  /**
   * @test
   * -# Test the data format returned by Upload::getArray($version) model when $version is V1
   */
  public function testDataFormatV1()
  {
    $this->testDataFormat(ApiVersion::V1);
  }
  /**
   * @test
   * -# Test the data format returned by Upload::getArray($version) model when $version is V2
   */
  public function testDataFormatV2()
  {
    $this->testDataFormat(ApiVersion::V2);
  }
  /**
   * @param $version version to test
   * @return void
   * -# Test the data format returned by Upload::getArray($version) model 
   */
  private function testDataFormat($version)
  {
    $hash = new Hash('sha1checksum', 'md5checksum', 'sha256checksum', 123123);
    if($version==ApiVersion::V1){
      $expectedUpload = [
        "folderid"    => 2,
        "foldername"  => 'root',
        "id"          => 3,
        "description" => '',
        "uploadname"  => 'my.tar.gz',
        "uploaddate"  => '01-01-2020',
        "assignee"    => 3,
        "assigneeDate" => '01-01-2020',
        "closingDate" => '01-01-2020',
        "hash"        => $hash->getArray()
      ];
    } else{
      $expectedUpload = [
        "folderId"    => 2,
        "folderName"  => 'root',
        "id"          => 3,
        "description" => '',
        "uploadName"  => 'my.tar.gz',
        "uploadDate"  => '01-01-2020',
        "assignee"    => 3,
        "assigneeDate" => '01-01-2020',
        "closingDate" => '01-01-2020',
        "hash"        => $hash->getArray()
      ];
    }

    $actualUpload = new Upload(2, 'root', 3, '', 'my.tar.gz', '01-01-2020', 3,
      $hash);
    $actualUpload->setAssigneeDate("01-01-2020");
    $actualUpload->setClosingDate("01-01-2020");

    $this->assertEquals($expectedUpload, $actualUpload->getArray($version));
  }
}
