<?php

/*
 SPDX-FileCopyrightText: © 2024 Valens Niyonsenga <valensniyonsenga2003@gmail.com>
 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @file
 * @brief Tests for BulkHistory model
 */
namespace Fossology\UI\Api\Test\Models;

use Fossology\UI\Api\Models\BulkHistory;
use PHPUnit\Framework\TestCase;

class BulkHistoryTest extends TestCase
{
  /**
   * Provides test data and an instance of the BulkHistory class.
   *
   * @return array An associative array containing:
   *  - `expectedArray`: The expected array structure.
   *  - `obj`: The instance of BulkHistory being tested.
   */
  private function getBulkHistoryInfo()
  {
    $expectedArray = [
      "bulkId" => 4,
      "clearingEventId" => 3,
      "text" => "BulkHistory text",
      "matched" => false,
      "tried" => false,
      "addedLicenses" => [],
      "removedLicenses" => []
    ];
    $obj = new BulkHistory(4, 3, "BulkHistory text", false, false, [], []);
    return [
      'expectedArray' => $expectedArray,
      'obj' => $obj
    ];
  }

  /**
   * Tests BulkHistory::getArray() method.
   *
   * This method validates that the `getArray` method returns the correct data structure.
   */
  public function testDataFormat()
  {
    $expectedArray = $this->getBulkHistoryInfo()['expectedArray'];
    $bulkHistory = $this->getBulkHistoryInfo()['obj'];
    $this->assertEquals($expectedArray, $bulkHistory->getArray());
  }

  /**
   * Tests BulkHistory::setBulkId() and getBulkId() methods.
   *
   * This method verifies that the `setBulkId` method correctly updates the bulkId property,
   * and that the `getBulkId` method returns the updated value.
   */
  public function testSetAndGetBulkId()
  {
    $info = $this->getBulkHistoryInfo();
    $bulkHistory = $info['obj'];
    $bulkId = 5;
    $bulkHistory->setBulkId($bulkId);
    $this->assertEquals($bulkId, $bulkHistory->getBulkId());
  }

  /**
   * Tests BulkHistory::setClearingEventId() and getClearingEventId() methods.
   *
   * This method verifies that the `setClearingEventId` method correctly updates the clearingEventId property,
   * and that the `getClearingEventId` method returns the updated value.
   */
  public function testSetAndGetClearingEventId()
  {
    $info = $this->getBulkHistoryInfo();
    $bulkHistory = $info['obj'];
    $clearingEventId = 6;
    $bulkHistory->setClearingEventId($clearingEventId);
    $this->assertEquals($clearingEventId, $bulkHistory->getClearingEventId());
  }

  /**
   * Tests BulkHistory::setText() and getText() methods.
   *
   * This method verifies that the `setText` method correctly updates the text property,
   * and that the `getText` method returns the updated value.
   */
  public function testSetAndGetText()
  {
    $info = $this->getBulkHistoryInfo();
    $bulkHistory = $info['obj'];
    $text = "Updated text";
    $bulkHistory->setText($text);
    $this->assertEquals($text, $bulkHistory->getText());
  }

  /**
   * Tests BulkHistory::setMatched() and getMatched() methods.
   *
   * This method verifies that the `setMatched` method correctly updates the matched property,
   * and that the `getMatched` method returns the updated value.
   */
  public function testSetAndGetMatched()
  {
    $info = $this->getBulkHistoryInfo();
    $bulkHistory = $info['obj'];
    $matched = true;
    $bulkHistory->setMatched($matched);
    $this->assertEquals($matched, $bulkHistory->getMatched());
  }

  /**
   * Tests BulkHistory::setTried() and getTried() methods.
   *
   * This method verifies that the `setTried` method correctly updates the tried property,
   * and that the `getTried` method returns the updated value.
   */
  public function testSetAndGetTried()
  {
    $info = $this->getBulkHistoryInfo();
    $bulkHistory = $info['obj'];
    $tried = true;
    $bulkHistory->setTried($tried);
    $this->assertEquals($tried, $bulkHistory->getTried());
  }

  /**
   * Tests BulkHistory::setAddedLicenses() and getAddedLicenses() methods.
   *
   * This method verifies that the `setAddedLicenses` method correctly updates the addedLicenses property,
   * and that the `getAddedLicenses` method returns the updated value.
   */
  public function testSetAndGetAddedLicenses()
  {
    $info = $this->getBulkHistoryInfo();
    $bulkHistory = $info['obj'];
    $addedLicenses = ['LicenseA', 'LicenseB'];
    $bulkHistory->setAddedLicenses($addedLicenses);
    $this->assertEquals($addedLicenses, $bulkHistory->getAddedLicenses());
  }

  /**
   * Tests BulkHistory::setRemovedLicenses() and getRemovedLicenses() methods.
   *
   * This method verifies that the `setRemovedLicenses` method correctly updates the removedLicenses property,
   * and that the `getRemovedLicenses` method returns the updated value.
   */
  public function testSetAndGetRemovedLicenses()
  {
    $info = $this->getBulkHistoryInfo();
    $bulkHistory = $info['obj'];
    $removedLicenses = ['LicenseX', 'LicenseY'];
    $bulkHistory->setRemovedLicenses($removedLicenses);
    $this->assertEquals($removedLicenses, $bulkHistory->getRemovedLicenses());
  }
}
