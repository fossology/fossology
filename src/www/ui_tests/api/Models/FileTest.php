<?php
/*
 SPDX-FileCopyrightText: © 2020 Siemens AG
 Author: Gaurav Mishra <mishra.gaurav@siemens.com>

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @file
 * @brief Tests for File model
 */

namespace Fossology\UI\Api\Test\Models;

use Fossology\UI\Api\Models\File;
use Fossology\UI\Api\Models\Hash;
use Fossology\UI\Api\Models\Findings;

/**
 * @class FileTest
 * @brief Tests for File model
 */
class FileTest extends \PHPUnit\Framework\TestCase
{
  ////// Constructor Tests //////

  /**
   * Tests that the File constructor initializes an instance correctly.
   *
   * @return void
   */
  public function testConstructor()
  {
    $hash = new Hash();  
    $file = new File($hash);  

    $this->assertInstanceOf(File::class, $file);
  }

  /**
   * @test
   * -# Test for keys in File model
   */
  public function testDataFormat()
  {
    $expectedArray = [
      "hash" => [
        "sha1" => "sha1checksum",
        "md5" => "md5checksum",
        "sha256" => "sha256checksum",
        "size" => 123
      ],
      "findings"=> [
        "scanner" => [
          "License1"
        ],
        "conclusion" => [
          "License2"
        ],
        "copyright" => []
      ],
      "uploads" => []
    ];

    $hash = new Hash('sha1checksum', 'md5checksum', 'sha256checksum', 123);
    $findings = new Findings("License1", "License2", []);
    $object = new File($hash);
    $object->setFindings($findings);
    $object->setUploads([]);

    $this->assertEquals($expectedArray, $object->getArray());
  }
}
