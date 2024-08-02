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
use PHPUnit\Framework\TestCase;

/**
 * @class UploadTest
 * @brief Test class for Upload model
 */
class UploadTest extends TestCase
{
  /**
   * @test
   * Test the data format returned by Upload::getArray($version) method when $version is V1
   */
  public function testDataFormatV1()
  {
    $this->testDataFormat(ApiVersion::V1);
  }

  /**
   * @test
   * Test the data format returned by Upload::getArray($version) method when $version is V2
   */
  public function testDataFormatV2()
  {
    $this->testDataFormat(ApiVersion::V2);
  }

  /**
   * Helper method to test the data format returned by Upload::getArray($version) method
   * @param $version version to test
   * @return void
   */
  private function testDataFormat($version)
  {
    $hash = new Hash('sha1checksum', 'md5checksum', 'sha256checksum', 123123);
    $expectedUpload = $this->getExpectedUpload($version, $hash);

    $actualUpload = new Upload(2, 'root', 3, '', 'my.tar.gz', '01-01-2020', 3, $hash);
    $actualUpload->setAssigneeDate("01-01-2020");
    $actualUpload->setClosingDate("01-01-2020");

    $this->assertEquals($expectedUpload, $actualUpload->getArray($version));
  }

  /**
   * Helper method to get the expected upload array based on version
   * @param $version version to test
   * @param Hash $hash Hash object
   * @return array
   */
  private function getExpectedUpload($version, $hash)
  {
    if ($version == ApiVersion::V1) {
      return [
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
    } else {
      return [
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
  }

  /**
   * @test
   * Test the getJSON method
   */
  public function testGetJSON()
  {
    $hash = new Hash('sha1checksum', 'md5checksum', 'sha256checksum', 123123);
    $upload = new Upload(2, 'root', 3, '', 'my.tar.gz', '01-01-2020', 3, $hash);
    $upload->setAssigneeDate("01-01-2020");
    $upload->setClosingDate("01-01-2020");

    $expectedJsonV1 = json_encode($this->getExpectedUpload(ApiVersion::V1, $hash));
    $expectedJsonV2 = json_encode($this->getExpectedUpload(ApiVersion::V2, $hash));

    $this->assertEquals($expectedJsonV1, $upload->getJSON(ApiVersion::V1));
    $this->assertEquals($expectedJsonV2, $upload->getJSON(ApiVersion::V2));
  }

  /**
   * @test
   * Test the setAssigneeDate method
   */
  public function testSetAssigneeDate()
  {
    $upload = new Upload(2, 'root', 3, '', 'my.tar.gz', '01-01-2020', 3, new Hash('', '', '', 0));
    $upload->setAssigneeDate("01-01-2020");
    $this->assertEquals("01-01-2020", $upload->getArray()[ 'assigneeDate']);
  }

  /**
   * @test
   * Test the setClosingDate method
   */
  public function testSetClosingDate()
  {
    $upload = new Upload(2, 'root', 3, '', 'my.tar.gz', '01-01-2020', 3, new Hash('', '', '', 0));
    $upload->setClosingDate("01-01-2020");
    $this->assertEquals("01-01-2020", $upload->getArray()[ 'closingDate']);
  }
}
