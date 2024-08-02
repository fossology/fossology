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
  private $uploadSummary;

  protected function setUp(): void
  {
    $this->uploadSummary = new UploadSummary();
  }

  /**
   * @test
   * Test the data format returned by UploadSummary::getArray() method
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

    $this->uploadSummary->setUploadId(5);
    $this->uploadSummary->setUploadName('test.tar.gz');
    $this->uploadSummary->setAssignee(3);
    $this->uploadSummary->setMainLicense('MIT');
    $this->uploadSummary->setUniqueLicenses(5);
    $this->uploadSummary->setTotalLicenses(25);
    $this->uploadSummary->setUniqueConcludedLicenses(1);
    $this->uploadSummary->setTotalConcludedLicenses(25);
    $this->uploadSummary->setFilesToBeCleared(0);
    $this->uploadSummary->setFilesCleared(25);
    $this->uploadSummary->setClearingStatus(UploadStatus::CLOSED);
    $this->uploadSummary->setCopyrightCount(10);
    $this->uploadSummary->setFileCount(25);
    $this->uploadSummary->setNoScannerLicenseFoundCount(0);
    $this->uploadSummary->setScannerUniqueLicenseCount(0);
    $this->uploadSummary->setConcludedNoLicenseFoundCount(0);

    $this->assertEquals($expected, $this->uploadSummary->getArray());
  }

  /**
   * @test
   * Test the JSON format returned by UploadSummary::getJSON() method
   */
  public function testGetJSON()
  {
    $this->uploadSummary->setUploadId(5);
    $this->uploadSummary->setUploadName('test.tar.gz');
    $this->uploadSummary->setAssignee(3);
    $this->uploadSummary->setMainLicense('MIT');
    $this->uploadSummary->setUniqueLicenses(5);
    $this->uploadSummary->setTotalLicenses(25);
    $this->uploadSummary->setUniqueConcludedLicenses(1);
    $this->uploadSummary->setTotalConcludedLicenses(25);
    $this->uploadSummary->setFilesToBeCleared(0);
    $this->uploadSummary->setFilesCleared(25);
    $this->uploadSummary->setClearingStatus(UploadStatus::CLOSED);
    $this->uploadSummary->setCopyrightCount(10);
    $this->uploadSummary->setFileCount(25);
    $this->uploadSummary->setNoScannerLicenseFoundCount(0);
    $this->uploadSummary->setScannerUniqueLicenseCount(0);
    $this->uploadSummary->setConcludedNoLicenseFoundCount(0);

    $expectedJson = json_encode($this->uploadSummary->getArray());
    $this->assertJsonStringEqualsJsonString($expectedJson, $this->uploadSummary->getJSON());
  }

  /**
   * @test
   * Test for UploadSummary::statusToString()
   */
  public function testStatusToString()
  {
    $this->assertEquals("Open", UploadSummary::statusToString(UploadStatus::OPEN));
    $this->assertEquals("InProgress", UploadSummary::statusToString(UploadStatus::IN_PROGRESS));
    $this->assertEquals("Closed", UploadSummary::statusToString(UploadStatus::CLOSED));
    $this->assertEquals("Rejected", UploadSummary::statusToString(UploadStatus::REJECTED));
    $this->assertEquals("NA", UploadSummary::statusToString(null));
    $this->assertEquals("NA", UploadSummary::statusToString('garbage'));
  }

  /**
   * @test
   * Test the setUploadId() and getUploadId() methods
   */
  public function testSetAndGetUploadId()
  {
    $this->uploadSummary->setUploadId(5);
    $this->assertEquals(5, $this->uploadSummary->getArray()['id']);
  }

  /**
   * @test
   * Test the setUploadName() and getUploadName() methods
   */
  public function testSetAndGetUploadName()
  {
    $this->uploadSummary->setUploadName('test.tar.gz');
    $this->assertEquals('test.tar.gz', $this->uploadSummary->getArray()['uploadName']);
  }

  /**
   * @test
   * Test the setAssignee() and getAssignee() methods
   */
  public function testSetAndGetAssignee()
  {
    $this->uploadSummary->setAssignee(3);
    $this->assertEquals(3, $this->uploadSummary->getArray()['assignee']);
  }

  /**
   * @test
   * Test the setMainLicense() and getMainLicense() methods
   */
  public function testSetAndGetMainLicense()
  {
    $this->uploadSummary->setMainLicense('MIT');
    $this->assertEquals('MIT', $this->uploadSummary->getArray()['mainLicense']);
  }

  /**
   * @test
   * Test the setUniqueLicenses() and getUniqueLicenses() methods
   */
  public function testSetAndGetUniqueLicenses()
  {
    $this->uploadSummary->setUniqueLicenses(5);
    $this->assertEquals(5, $this->uploadSummary->getArray()['uniqueLicenses']);
  }
  /**
   * @test
   * Test the setUniqueConcludedLicenses() and getUniqueConcludedLicenses() methods
   */
  public function testSetAndGetUniqueConcludedLicenses()
  {
    $this->uploadSummary->setUniqueConcludedLicenses(1);
    $this->assertEquals(1, $this->uploadSummary->getArray()['uniqueConcludedLicenses']);
  }

  /**
   * @test
   * Test the setTotalConcludedLicenses() and getTotalConcludedLicenses() methods
   */
  public function testSetAndGetTotalConcludedLicenses()
  {
    $this->uploadSummary->setTotalConcludedLicenses(25);
    $this->assertEquals(25, $this->uploadSummary->getArray()['totalConcludedLicenses']);
  }

  /**
   * @test
   * Test the setFilesToBeCleared() and getFilesToBeCleared() methods
   */
  public function testSetAndGetFilesToBeCleared()
  {
    $this->uploadSummary->setFilesToBeCleared(0);
    $this->assertEquals(0, $this->uploadSummary->getArray()['filesToBeCleared']);
  }

  /**
   * @test
   * Test the setFilesCleared() and getFilesCleared() methods
   */
  public function testSetAndGetFilesCleared()
  {
    $this->uploadSummary->setFilesCleared(25);
    $this->assertEquals(25, $this->uploadSummary->getArray()['filesCleared']);
  }

  /**
   * @test
   * Test the setClearingStatus() and getClearingStatus() methods
   */
  public function testSetAndGetClearingStatus()
  {
    $this->uploadSummary->setClearingStatus(UploadStatus::CLOSED);
    $this->assertEquals("Closed", $this->uploadSummary->getArray()['clearingStatus']);
  }

  /**
   * @test
   * Test the setCopyrightCount() and getCopyrightCount() methods
   */
  public function testSetAndGetCopyrightCount()
  {
    $this->uploadSummary->setCopyrightCount(10);
    $this->assertEquals(10, $this->uploadSummary->getArray()['copyrightCount']);
  }

  /**
   * @test
   * Test the setFileCount() and getFileCount() methods
   */
  public function testSetAndGetFileCount()
  {
    $this->uploadSummary->setFileCount(25);
    $this->assertEquals(25, $this->uploadSummary->getArray()['fileCount']);
  }

  /**
   * @test
   * Test the setNoScannerLicenseFoundCount() and getNoScannerLicenseFoundCount() methods
   */
  public function testSetAndGetNoScannerLicenseFoundCount()
  {
    $this->uploadSummary->setNoScannerLicenseFoundCount(0);
    $this->assertEquals(0, $this->uploadSummary->getArray()['noScannerLicenseFoundCount']);
  }

  /**
   * @test
   * Test the setScannerUniqueLicenseCount() and getScannerUniqueLicenseCount() methods
   */
  public function testSetAndGetScannerUniqueLicenseCount()
  {
    $this->uploadSummary->setScannerUniqueLicenseCount(0);
    $this->assertEquals(0, $this->uploadSummary->getArray()['scannerUniqueLicenseCount']);
  }

  /**
   * @test
   * Test the setConcludedNoLicenseFoundCount() and getConcludedNoLicenseFoundCount() methods
   */
  public function testSetAndGetConcludedNoLicenseFoundCount()
  {
    $this->uploadSummary->setConcludedNoLicenseFoundCount(0);
    $this->assertEquals(0, $this->uploadSummary->getArray()['concludedNoLicenseFoundCount']);
  }
}
