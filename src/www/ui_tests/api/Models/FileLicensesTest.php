<?php
/*
 SPDX-FileCopyrightText: © 2025 Harshit Gandhi <gandhiharshit716@gmail.com>
 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @file
 * @brief Tests for FileLicenses model
 */

namespace Fossology\UI\Api\Test\Models;

use Fossology\UI\Api\Models\FileLicenses;
use Fossology\UI\Api\Models\Findings;
use Fossology\UI\Api\Models\ApiVersion;
use PHPUnit\Framework\TestCase;

/**
 * @class FileLicensesTest
 * @brief Tests for FileLicenses model
 */
class FileLicensesTest extends TestCase
{
  /** @var array $testData Test data */
  private $filePath = "/path/to/file.txt";
  private $findings;
  private $clearingStatus = "identified";

  /**
   * Set up test environment
   */
  protected function setUp(): void
  {
    parent::setUp();
    $this->findings = $this->createMock(Findings::class);
    $this->findings->method('getArray')->willReturn(['mock' => 'findings']);
  }

  ////// Constructor Tests //////

  /**
   * Tests that the FileLicenses constructor initializes an instance correctly.
   *
   * @return void
   */
  public function testConstructor()
  {
    $fileLicenses = new FileLicenses($this->filePath, $this->findings, $this->clearingStatus);
    $this->assertInstanceOf(FileLicenses::class, $fileLicenses);
  }

  ////// Getter Tests //////

  /**
   * @test
   * -# Test getter for filePath
   */
  public function testGetFilePath()
  {
    $fileLicenses = new FileLicenses($this->filePath, $this->findings, $this->clearingStatus);
    $this->assertEquals($this->filePath, $fileLicenses->getFilePath());
  }

  /**
   * @test
   * -# Test getter for findings
   */
  public function testGetFindings()
  {
    $fileLicenses = new FileLicenses($this->filePath, $this->findings, $this->clearingStatus);
    $this->assertEquals($this->findings, $fileLicenses->getFindings());
  }

  /**
   * @test
   * -# Test getter for clearingStatus
   */
  public function testGetClearingStatus()
  {
    $fileLicenses = new FileLicenses($this->filePath, $this->findings, $this->clearingStatus);
    $this->assertEquals($this->clearingStatus, $fileLicenses->getClearingStatus());
  }

  ////// Setter Tests //////

  /**
   * @test
   * -# Test setter for filePath
   */
  public function testSetFilePath()
  {
    $fileLicenses = new FileLicenses($this->filePath, $this->findings, $this->clearingStatus);
    $newPath = "/new/path/file.php";
    $fileLicenses->setFilePath($newPath);
    $this->assertEquals($newPath, $fileLicenses->getFilePath());
  }

  /**
   * @test
   * -# Test setter for findings
   */
  public function testSetFindings()
  {
    $fileLicenses = new FileLicenses($this->filePath, $this->findings, $this->clearingStatus);
    $newFindings = $this->createMock(Findings::class);
    $fileLicenses->setFindings($newFindings);
    $this->assertEquals($newFindings, $fileLicenses->getFindings());
  }

  /**
   * @test
   * -# Test setter for clearingStatus
   */
  public function testSetClearingStatus()
  {
    $fileLicenses = new FileLicenses($this->filePath, $this->findings, $this->clearingStatus);
    $newStatus = "cleared";
    $fileLicenses->setClearingStatus($newStatus);
    $this->assertEquals($newStatus, $fileLicenses->getClearingStatus());
  }

  /**
   * @test
   * -# Test setters with null values
   */
  public function testSettersWithNullValues()
  {
    $fileLicenses = new FileLicenses($this->filePath, $this->findings, $this->clearingStatus);
    
    $fileLicenses->setFilePath(null);
    $this->assertNull($fileLicenses->getFilePath());
    
    $fileLicenses->setFindings(null);
    $this->assertNull($fileLicenses->getFindings());
    
    $fileLicenses->setClearingStatus(null);
    $this->assertNull($fileLicenses->getClearingStatus());
  }

  /**
   * @test
   * -# Test method chaining for setters
   */
  public function testSetterMethodChaining()
  {
    $fileLicenses = new FileLicenses();
    
    $result = $fileLicenses->setFilePath($this->filePath)
                          ->setFindings($this->findings)
                          ->setClearingStatus($this->clearingStatus);
    
    $this->assertInstanceOf(FileLicenses::class, $result);
    $this->assertEquals($this->filePath, $fileLicenses->getFilePath());
    $this->assertEquals($this->findings, $fileLicenses->getFindings());
    $this->assertEquals($this->clearingStatus, $fileLicenses->getClearingStatus());
  }

  /**
   * @test
   * -# Test the data format returned by FileLicenses::getArray() for V1
   */
  public function testGetArrayV1()
  {
    $fileLicenses = new FileLicenses($this->filePath, $this->findings, $this->clearingStatus);
    
    $expectedArray = [
      'filePath' => $this->filePath,
      'findings' => ['mock' => 'findings'],
      'clearing_status' => $this->clearingStatus
    ];

    $this->assertEquals($expectedArray, $fileLicenses->getArray(ApiVersion::V1));
  }

  /**
   * @test
   * -# Test the data format returned by FileLicenses::getArray() for V2
   */
  public function testGetArrayV2()
  {
    $fileLicenses = new FileLicenses($this->filePath, $this->findings, $this->clearingStatus);
    
    $expectedArray = [
      'filePath' => $this->filePath,
      'findings' => ['mock' => 'findings'],
      'clearingStatus' => $this->clearingStatus
    ];

    $this->assertEquals($expectedArray, $fileLicenses->getArray(ApiVersion::V2));
  }
}