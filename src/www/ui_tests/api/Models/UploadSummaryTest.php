<?php
/*
 SPDX-FileCopyrightText: © 2020 Siemens AG
 Author: Gaurav Mishra <mishra.gaurav@siemens.com>

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @file
 * @brief Tests for UploadSummary model
 */

namespace Fossology\UI\Api\Test\Models;

use Fossology\UI\Api\Models\UploadSummary;
use Fossology\Lib\Data\UploadStatus;

/**
 * @class UploadSummaryTest
 * @brief Test cases for UploadSummary model
 */
class UploadSummaryTest extends \PHPUnit\Framework\TestCase
{
  /**
   * @test
   * -# Test the data format returned by UploadSummary::getArray() model
   */
  public function testDataFormat()
  {
    $expected = [
      "id"                      => 5,
      "uploadName"              => 'test.tar.gz',
      "assignee"                => 3,
      "mainLicense"             => 'MIT',
      "uniqueLicenses"          => 5,
      "totalLicenses"           => 25,
      "uniqueConcludedLicenses" => 1,
      "totalConcludedLicenses"  => 25,
      "filesToBeCleared"        => 0,
      "filesCleared"            => 25,
      "clearingStatus"          => "Closed",
      "copyrightCount"          => 10,
      "fileCount"               => 25,
      "noScannerLicenseFoundCount" => 0,
      "scannerUniqueLicenseCount" => 0,
      'concludedNoLicenseFoundCount' => 0
    ];

    $actual = new UploadSummary();
    $actual->setUploadId(5);
    $actual->setUploadName('test.tar.gz');
    $actual->setAssignee(3);
    $actual->setMainLicense('MIT');
    $actual->setUniqueLicenses(5);
    $actual->setTotalLicenses(25);
    $actual->setUniqueConcludedLicenses(1);
    $actual->setTotalConcludedLicenses(25);
    $actual->setFilesToBeCleared(0);
    $actual->setFilesCleared(25);
    $actual->setClearingStatus(UploadStatus::CLOSED);
    $actual->setCopyrightCount(10);
    $actual->setFileCount(25);
    $actual->setNoScannerLicenseFoundCount(0);
    $actual->setScannerUniqueLicenseCount(0);
    $actual->setConcludedNoLicenseFoundCount(0);

    $this->assertEquals($expected, $actual->getArray());
  }

  /**
   * @test
   * -# Test for UploadSummary::statusToString()
   * -# Check if the function responds correct string for each UploadStatus
   *    member.
   * -# Also check if the function handles invalid values.
   */
  public function testStatusToString()
  {
    $expectedOpen = "Open";
    $expectedProgress = "InProgress";
    $expectedClosed = "Closed";
    $expectedRejected = "Rejected";
    $expectedDefault = "NA";

    $this->assertEquals($expectedOpen,
      UploadSummary::statusToString(UploadStatus::OPEN));
    $this->assertEquals($expectedProgress,
      UploadSummary::statusToString(UploadStatus::IN_PROGRESS));
    $this->assertEquals($expectedClosed,
      UploadSummary::statusToString(UploadStatus::CLOSED));
    $this->assertEquals($expectedRejected,
      UploadSummary::statusToString(UploadStatus::REJECTED));
    $this->assertEquals($expectedDefault, UploadSummary::statusToString(null));
    $this->assertEquals($expectedDefault,
      UploadSummary::statusToString('garbage'));
  }
}
