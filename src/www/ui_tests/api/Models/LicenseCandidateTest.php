<?php
/*
 SPDX-FileCopyrightText: © 2025 Harshit Gandhi <gandhiharshit716@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @file
 * @brief Tests for LicenseCandidate model
 */

namespace Fossology\UI\Api\Test\Models;

use Fossology\UI\Api\Models\LicenseCandidate;
use PHPUnit\Framework\TestCase;

/**
 * @class LicenseCandidateTest
 * @brief Tests for LicenseCandidate model
 */
class LicenseCandidateTest extends TestCase
{
  /** @var LicenseCandidate $license LicenseCandidate instance for testing */
  private $license;

  /**
   * @brief Setup test data before each test
   */
  protected function setUp(): void
  {
    $this->license = new LicenseCandidate(
      1,
      'GPL-2.0',
      'GPL-2.0-only',
      'GNU General Public License 2.0',
      'Sample license text',
      'TestGroup',
      101
    );
  }

  /**
   * Tests that the LicenseCandidate constructor initializes an instance correctly.
   *
   * @return void
   */
  public function testConstructor()
  {
    $this->assertInstanceOf(LicenseCandidate::class, $this->license);
  }

  ////// Getter Tests //////

  /**
   * @test
   * @brief Test getter for ID
   */
  public function testGetId()
  {
    $this->assertEquals(1, $this->license->getId());
  }

  /**
   * @test
   * @brief Test getter for shortname
   */
  public function testGetShortname()
  {
    $this->assertEquals('GPL-2.0', $this->license->getShortname());
  }

  /**
   * @test
   * @brief Test getter for SPDX ID
   */
  public function testGetSpdxid()
  {
    $this->assertEquals('GPL-2.0-only', $this->license->getSpdxid());
  }

  /**
   * @test
   * @brief Test getter for full name
   */
  public function testGetFullname()
  {
    $this->assertEquals('GNU General Public License 2.0', $this->license->getFullname());
  }

  /**
   * @test
   * @brief Test getter for license text
   */
  public function testGetText()
  {
    $this->assertEquals('Sample license text', $this->license->getText());
  }

  /**
   * @test
   * @brief Test getter for group name
   */
  public function testGetGroupName()
  {
    $this->assertEquals('TestGroup', $this->license->getGroupName());
  }

  /**
   * @test
   * @brief Test getter for group ID
   */
  public function testGetGroupId()
  {
    $this->assertEquals(101, $this->license->getGroupId());
  }

  ////// Setter Tests //////

  /**
   * @test
   * @brief Test setter for ID
   */
  public function testSetId()
  {
    $this->license->setId(2);
    $this->assertEquals(2, $this->license->getId());
  }

  /**
   * @test
   * @brief Test setter for shortname
   */
  public function testSetShortname()
  {
    $this->license->setShortname('MIT');
    $this->assertEquals('MIT', $this->license->getShortname());
  }

  /**
   * @test
   * @brief Test setter for SPDX ID
   */
  public function testSetSpdxid()
  {
    $this->license->setSpdxid('MIT');
    $this->assertEquals('MIT', $this->license->getSpdxid());
  }

  /**
   * @test
   * @brief Test setter for full name
   */
  public function testSetFullname()
  {
    $this->license->setFullname('MIT License');
    $this->assertEquals('MIT License', $this->license->getFullname());
  }

  /**
   * @test
   * @brief Test setter for license text
   */
  public function testSetText()
  {
    $this->license->setText('MIT sample text');
    $this->assertEquals('MIT sample text', $this->license->getText());
  }

  /**
   * @test
   * @brief Test setter for group name
   */
  public function testSetGroupName()
  {
    $this->license->setGroupName('NewGroup');
    $this->assertEquals('NewGroup', $this->license->getGroupName());
  }

  /**
   * @test
   * @brief Test setter for group ID
   */
  public function testSetGroupId()
  {
    $this->license->setGroupId(202);
    $this->assertEquals(202, $this->license->getGroupId());
  }
}
