<?php
/*
 SPDX-FileCopyrightText: © 2025 Harshit Gandhi <gandhiharshit716@gmail.com>
 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @file
 * @brief Tests for ClearingHistory model
 */

namespace Fossology\UI\Api\Test\Models;

use Fossology\UI\Api\Models\ClearingHistory;

use PHPUnit\Framework\TestCase;

/**
 * @class ClearingHistoryTest
 * @brief Tests for ClearingHistory model
 */
class ClearingHistoryTest extends TestCase
{
  ////// Constructor Tests //////

  /**
   * Tests that the ClearingHistory constructor initializes an instance correctly.
   *
   * @return void
   */
  public function testConstructor()
  {
    $clearingHistory = new ClearingHistory(
      "2023-01-01",
      "testuser",
      "local",
      "concluded",
      ["GPL-2.0", "MIT"],
      ["Apache-2.0"]
    );
    $this->assertInstanceOf(ClearingHistory::class, $clearingHistory);
  }

  /**
   * @test
   * -# Test the data format returned by ClearingHistory::getArray()
   */
  public function testDataFormat()
  {
    $expectedArray = [
      'date' => "2023-01-01",
      'username' => "testuser",
      'scope' => "local",
      'type' => "concluded",
      'addedLicenses' => ["GPL-2.0", "MIT"],
      'removedLicenses' => ["Apache-2.0"]
    ];

    $clearingHistory = new ClearingHistory(
      $expectedArray['date'],
      $expectedArray['username'],
      $expectedArray['scope'],
      $expectedArray['type'],
      $expectedArray['addedLicenses'],
      $expectedArray['removedLicenses']
    );

    $this->assertEquals($expectedArray, $clearingHistory->getArray());
  }

  ////// Getter Tests //////

  /**
   * @test
   * -# Test getter for date
   */
  public function testGetDate()
  {
    $date = "2023-01-01";
    $clearingHistory = new ClearingHistory($date, "testuser", "local", "concluded", [], []);
    $this->assertEquals($date, $clearingHistory->getDate());
  }

  /**
   * @test
   * -# Test getter for username
   */
  public function testGetUsername()
  {
    $username = "testuser";
    $clearingHistory = new ClearingHistory("2023-01-01", $username, "local", "concluded", [], []);
    $this->assertEquals($username, $clearingHistory->getUsername());
  }

  /**
   * @test
   * -# Test getter for scope
   */
  public function testGetScope()
  {
    $scope = "local";
    $clearingHistory = new ClearingHistory("2023-01-01", "testuser", $scope, "concluded", [], []);
    $this->assertEquals($scope, $clearingHistory->getScope());
  }

  /**
   * @test
   * -# Test getter for type
   */
  public function testGetType()
  {
    $type = "concluded";
    $clearingHistory = new ClearingHistory("2023-01-01", "testuser", "local", $type, [], []);
    $this->assertEquals($type, $clearingHistory->getType());
  }

  /**
   * @test
   * -# Test getter for addedLicenses
   */
  public function testGetAddedLicenses()
  {
    $addedLicenses = ["GPL-2.0", "MIT"];
    $clearingHistory = new ClearingHistory("2023-01-01", "testuser", "local", "concluded", $addedLicenses, []);
    $this->assertEquals($addedLicenses, $clearingHistory->getAddedLicenses());
  }

  /**
   * @test
   * -# Test getter for removedLicenses
   */
  public function testGetRemovedLicenses()
  {
    $removedLicenses = ["Apache-2.0"];
    $clearingHistory = new ClearingHistory("2023-01-01", "testuser", "local", "concluded", [], $removedLicenses);
    $this->assertEquals($removedLicenses, $clearingHistory->getRemovedLicenses());
  }

  ////// Setter Tests //////

  /**
   * @test
   * -# Test setter for date
   */
  public function testSetDate()
  {
    $clearingHistory = new ClearingHistory("2023-01-01", "testuser", "local", "concluded", [], []);
    $newDate = "2023-02-01";
    $clearingHistory->setDate($newDate);
    $this->assertEquals($newDate, $clearingHistory->getDate());
  }

  /**
   * @test
   * -# Test setter for username
   */
  public function testSetUsername()
  {
    $clearingHistory = new ClearingHistory("2023-01-01", "testuser", "local", "concluded", [], []);
    $newUsername = "newuser";
    $clearingHistory->setUsername($newUsername);
    $this->assertEquals($newUsername, $clearingHistory->getUsername());
  }

  /**
   * @test
   * -# Test setter for scope
   */
  public function testSetScope()
  {
    $clearingHistory = new ClearingHistory("2023-01-01", "testuser", "local", "concluded", [], []);
    $newScope = "global";
    $clearingHistory->setScope($newScope);
    $this->assertEquals($newScope, $clearingHistory->getScope());
  }

  /**
   * @test
   * -# Test setter for type
   */
  public function testSetType()
  {
    $clearingHistory = new ClearingHistory("2023-01-01", "testuser", "local", "concluded", [], []);
    $newType = "candidate";
    $clearingHistory->setType($newType);
    $this->assertEquals($newType, $clearingHistory->getType());
  }

  /**
   * @test
   * -# Test setter for addedLicenses
   */
  public function testSetAddedLicenses()
  {
    $clearingHistory = new ClearingHistory("2023-01-01", "testuser", "local", "concluded", [], []);
    $newAddedLicenses = ["BSD-3-Clause", "LGPL-2.1"];
    $clearingHistory->setAddedLicenses($newAddedLicenses);
    $this->assertEquals($newAddedLicenses, $clearingHistory->getAddedLicenses());
  }

  /**
   * @test
   * -# Test setter for removedLicenses
   */
  public function testSetRemovedLicenses()
  {
    $clearingHistory = new ClearingHistory("2023-01-01", "testuser", "local", "concluded", [], []);
    $newRemovedLicenses = ["GPL-3.0", "MPL-2.0"];
    $clearingHistory->setRemovedLicenses($newRemovedLicenses);
    $this->assertEquals($newRemovedLicenses, $clearingHistory->getRemovedLicenses());
  }
}