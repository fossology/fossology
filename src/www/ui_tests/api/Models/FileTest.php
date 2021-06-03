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
