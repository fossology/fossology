<?php
/*
 SPDX-FileCopyrightText: © 2025 Harshit Gandhi <gandhiharshit716@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @file
 * @brief Tests for LicenseDecision model
 */

namespace Fossology\UI\Api\Test\Models;

use Fossology\UI\Api\Models\LicenseDecision;
use PHPUnit\Framework\TestCase;

/**
 * @class LicenseDecisionTest
 * @brief Unit tests for LicenseDecision model
 */
class LicenseDecisionTest extends TestCase
{
  /**
   * @var array Sample test data for LicenseDecision
   */
  private $sampleData;

  /**
   * @brief Setup test data before each test
   */
  protected function setUp(): void
  {
    $this->sampleData = [
      'id' => 1,
      'shortName' => 'GPL-3.0',
      'fullName' => 'GNU General Public License 3.0',
      'text' => 'Sample license text',
      'url' => 'https://www.gnu.org/licenses/gpl-3.0.html',
      'sources' => ['scanner', 'user'],
      'acknowledgement' => 'Sample acknowledgement',
      'comment' => 'Sample comment',
      'isMainLicense' => true,
      'risk' => null,
      'isRemoved' => false,
      'isCandidate' => false
    ];
  }

  ////// Constructor Tests //////

  /**
   * Tests that the LicenseDecision constructor initializes an instance correctly.
   *
   * @return void
   */
  public function testConstructor()
  {
    $license = new LicenseDecision(...array_values($this->sampleData));
    $this->assertInstanceOf(LicenseDecision::class, $license);
  }

  ////// Getter Tests //////

  /**
   * @test
   * @brief Test getter for sources
   */
  public function testGetSources()
  {
    $license = new LicenseDecision(...array_values($this->sampleData));
    $this->assertEquals($this->sampleData['sources'], $license->getSources());
  }

  /**
   * @test
   * @brief Test getter for acknowledgement
   */
  public function testGetAcknowledgement()
  {
    $license = new LicenseDecision(...array_values($this->sampleData));
    $this->assertEquals($this->sampleData['acknowledgement'], $license->getAcknowledgement());
  }

    /**
   * @test
   * @brief Test getter for comment
   */
  public function testGetComment()
  {
    $license = new LicenseDecision(...array_values($this->sampleData));
    $this->assertEquals($this->sampleData['comment'], $license->getComment());
  }

    /**
   * @test
   * @brief Test getter for isMainLicense
   */
  public function testGetIsMainLicense()
  {
    $license = new LicenseDecision(...array_values($this->sampleData));
    $this->assertEquals($this->sampleData['isMainLicense'], $license->getIsMainLicense());
  }

  /**
   * @test
   * @brief Test getter for isRemoved
   */
  public function testGetIsRemoved()
  {
    $license = new LicenseDecision(...array_values($this->sampleData));
    $this->assertEquals($this->sampleData['isRemoved'], $license->getIsRemoved());
  }

  ////// Setter Tests //////

  /**
   * @test
   * @brief Test setter for sources
   */
  public function testSetSources()
  {
    $license = new LicenseDecision(1);
    $sources = ['manual', 'detected'];
    $license->setSources($sources);
    $this->assertEquals($sources, $license->getSources());
  }

  /**
   * @test
   * @brief Test setter for acknowledgement
   */
  public function testSetAcknowledgement()
  {
    $license = new LicenseDecision(1);
    $acknowledgement = 'Updated acknowledgement';
    $license->setAcknowledgement($acknowledgement);
    $this->assertEquals($acknowledgement, $license->getAcknowledgement());
  }

  /**
   * @test
   * @brief Test setter for comment
   */
  public function testSetComment()
  {
    $license = new LicenseDecision(1);
    $comment = 'Updated comment';
    $license->setComment($comment);
    $this->assertEquals($comment, $license->getComment());
  }

  /**
   * @test
   * @brief Test setter for isMainLicense
   */
  public function testSetIsMainLicense()
  {
    $license = new LicenseDecision(1);
    $license->setIsMainLicense(true);
    $this->assertTrue($license->getIsMainLicense());
  }

  

  /**
   * @test
   * @brief Test setter for isRemoved
   */
  public function testSetIsRemoved()
  {
    $license = new LicenseDecision(1);
    $license->setIsRemoved(true);
    $this->assertTrue($license->getIsRemoved());
  }

  /**
   * @test
   * @brief Test conversion to array
   */
  public function testGetArray()
  {
    $license = new LicenseDecision(...array_values($this->sampleData));
    $array = $license->getArray();
    $this->assertEquals($this->sampleData, array_intersect_key($array, $this->sampleData));
  }

  /**
   * @test
   * @brief Test conversion to JSON
   */
  public function testGetJSON()
  {
    $license = new LicenseDecision(...array_values($this->sampleData));
    $json = $license->getJSON();
    $this->assertJson($json);
    $this->assertEquals($license->getArray(), json_decode($json, true));
  }

  /**
   * @test
   * @brief Test default values when initialized with minimal data
   */
  public function testEmptyValues()
  {
    $license = new LicenseDecision(1);
    $this->assertEmpty($license->getSources());
    $this->assertEmpty($license->getAcknowledgement());
    $this->assertEmpty($license->getComment());
    $this->assertFalse($license->getIsMainLicense());
    $this->assertFalse($license->getIsRemoved());
  }

  /**
   * @test
   * @brief Test class inheritance structure
   */
  public function testInheritance()
  {
    $license = new LicenseDecision(...array_values($this->sampleData));
    $this->assertEquals($this->sampleData['id'], $license->getId());
  }

  /**
   * 
   */
  public function testAllowedKeys()
  {
    $expectedKeys = [
      'shortName', 'fullName', 'text', 'url', 'risk', 'isCandidate', 'mergeRequest', 'source', 'acknowledgement', 'comment'
    ];
    $this->assertEquals($expectedKeys, LicenseDecision::ALLOWED_KEYS);
  }
}
