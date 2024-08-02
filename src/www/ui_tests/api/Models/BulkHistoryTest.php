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

/**
 * @class BulkHistoryTest
 * @brief Tests for BulkHistory model
 */
class BulkHistoryTest extends TestCase
{
  /**
   * Provides test data and instances of the BulkHistory class.
   * @return array An associative array containing test data and BulkHistory objects.
   */
  private function getBulkHistoryInfo()
  {
    return [
      'bulkHistoryInfo' => [
        "bulkId" => 10,
        "clearingEventId" => 12,
        "text" => "bulkHistoryInfo",
        "matched" => false,
        "tried" => false,
        "addedLicenses" => [],
        "removedLicenses" => []
      ],
      'obj' => new BulkHistory(10, 12, "bulkHistoryInfo", false, false, [], [])
    ];
  }

  /**
   * Test BulkHistory::getArray()
   * Tests that the BulkHistory object's getArray method returns the correct data format.
   * - # Check if the BulkHistory was initialized correctly and getArray returns the expected array.
   */
  public function testDataFormat()
  {
    $expected = $this->getBulkHistoryInfo()['bulkHistoryInfo'];
    $bulkHistory = $this->getBulkHistoryInfo()['obj'];
    $this->assertEquals($expected, $bulkHistory->getArray());
  }

  /**
   * Test BulkHistory::getBulkId()
   * Tests the getBulkId method of the BulkHistory class.
   * - # Check if the bulkId property is correctly returned.
   */
  public function testGetBulkId()
  {
    $bulkHistory = $this->getBulkHistoryInfo()['obj'];
    $this->assertEquals(10, $bulkHistory->getBulkId());
  }

  /**
   * Test BulkHistory::getClearingEventId()
   * Tests the getClearingEventId method of the BulkHistory class.
   * - # Check if the clearingEventId property is correctly returned.
   */
  public function testGetClearingEventId()
  {
    $bulkHistory = $this->getBulkHistoryInfo()['obj'];
    $this->assertEquals(12, $bulkHistory->getClearingEventId());
  }

  /**
   * Test BulkHistory::getText()
   * Tests the getText method of the BulkHistory class.
   * - # Check if the text property is correctly returned.
   */
  public function testGetText()
  {
    $bulkHistory = $this->getBulkHistoryInfo()['obj'];
    $this->assertEquals("bulkHistoryInfo", $bulkHistory->getText());
  }

  /**
   * Test BulkHistory::getMatched()
   * Tests the getMatched method of the BulkHistory class.
   * - # Check if the matched property is correctly returned.
   */
  public function testGetMatched()
  {
    $bulkHistory = $this->getBulkHistoryInfo()['obj'];
    $this->assertFalse($bulkHistory->getMatched());
  }

  /**
   * Test BulkHistory::getTried()
   * Tests the getTried method of the BulkHistory class.
   * - # Check if the tried property is correctly returned.
   */
  public function testGetTried()
  {
    $bulkHistory = $this->getBulkHistoryInfo()['obj'];
    $this->assertFalse($bulkHistory->getTried());
  }

  /**
   * Test BulkHistory::getAddedLicenses()
   * Tests the getAddedLicenses method of the BulkHistory class.
   * - # Check if the addedLicenses property is correctly returned.
   */
  public function testGetAddedLicenses()
  {
    $bulkHistory = $this->getBulkHistoryInfo()['obj'];
    $this->assertEquals([], $bulkHistory->getAddedLicenses());
  }

  /**
   * Test BulkHistory::getRemovedLicenses()
   * Tests the getRemovedLicenses method of the BulkHistory class.
   * - # Check if the removedLicenses property is correctly returned.
   */
  public function testGetRemovedLicenses()
  {
    $bulkHistory = $this->getBulkHistoryInfo()['obj'];
    $this->assertEquals([], $bulkHistory->getRemovedLicenses());
  }

  /**
   * Test BulkHistory::setBulkId()
   * Tests the setBulkId method of the BulkHistory class.
   * - # Check if the bulkId property is correctly set.
   */
  public function testSetBulkId()
  {
    $bulkHistory = $this->getBulkHistoryInfo()['obj'];
    $bulkHistory->setBulkId(20);
    $this->assertEquals(20, $bulkHistory->getBulkId());
  }

  /**
   * Test BulkHistory::setClearingEventId()
   * Tests the setClearingEventId method of the BulkHistory class.
   * - # Check if the clearingEventId property is correctly set.
   */
  public function testSetClearingEventId()
  {
    $bulkHistory = $this->getBulkHistoryInfo()['obj'];
    $bulkHistory->setClearingEventId(25);
    $this->assertEquals(25, $bulkHistory->getClearingEventId());
  }

  /**
   * Test BulkHistory::setText()
   * Tests the setText method of the BulkHistory class.
   * - # Check if the text property is correctly set.
   */
  public function testSetText()
  {
    $bulkHistory = $this->getBulkHistoryInfo()['obj'];
    $bulkHistory->setText("newText");
    $this->assertEquals("newText", $bulkHistory->getText());
  }

  /**
   * Test BulkHistory::setMatched()
   * Tests the setMatched method of the BulkHistory class.
   * - # Check if the matched property is correctly set.
   */
  public function testSetMatched()
  {
    $bulkHistory = $this->getBulkHistoryInfo()['obj'];
    $bulkHistory->setMatched(true);
    $this->assertTrue($bulkHistory->getMatched());
  }

  /**
   * Test BulkHistory::setTried()
   * Tests the setTried method of the BulkHistory class.
   * - # Check if the tried property is correctly set.
   */
  public function testSetTried()
  {
    $bulkHistory = $this->getBulkHistoryInfo()['obj'];
    $bulkHistory->setTried(true);
    $this->assertTrue($bulkHistory->getTried());
  }

  /**
   * Test BulkHistory::setAddedLicenses()
   * Tests the setAddedLicenses method of the BulkHistory class.
   * - # Check if the addedLicenses property is correctly set.
   */
  public function testSetAddedLicenses()
  {
    $bulkHistory = $this->getBulkHistoryInfo()['obj'];
    $bulkHistory->setAddedLicenses(["license1"]);
    $this->assertEquals(["license1"], $bulkHistory->getAddedLicenses());
  }

  /**
   * Test BulkHistory::setRemovedLicenses()
   * Tests the setRemovedLicenses method of the BulkHistory class.
   * - # Check if the removedLicenses property is correctly set.
   */
  public function testSetRemovedLicenses()
  {
    $bulkHistory = $this->getBulkHistoryInfo()['obj'];
    $bulkHistory->setRemovedLicenses(["license2"]);
    $this->assertEquals(["license2"], $bulkHistory->getRemovedLicenses());
  }
}
