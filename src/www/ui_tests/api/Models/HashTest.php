<?php
/*
 SPDX-FileCopyrightText: © 2025 Harshit Gandhi <gandhiharshit716@gmail.com>
 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @file
 * @brief Tests for Hash model
 */

namespace Fossology\UI\Api\Test\Models;

use Fossology\UI\Api\Models\Hash;
use PHPUnit\Framework\TestCase;

/**
 * @class HashTest
 * @brief Tests for Hash model
 */
class HashTest extends TestCase
{
  /** @var array $testData Test data */
  private $sha1 = "da39a3ee5e6b4b0d3255bfef95601890afd80709";
  private $md5 = "d41d8cd98f00b204e9800998ecf8427e";
  private $sha256 = "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855";
  private $size = 1024;

  ////// Constructor Tests //////

  /**
   * Tests that the Hash constructor initializes an instance correctly.
   *
   * @return void
   */
  public function testConstructor()
  {
    $hash = new Hash($this->sha1, $this->md5, $this->sha256, $this->size);
    $this->assertInstanceOf(Hash::class, $hash);
  }

  ////// Getter Tests //////

  /**
   * @test
   * -# Test getter for sha1
   */
  public function testGetSha1()
  {
    $hash = new Hash($this->sha1, $this->md5, $this->sha256, $this->size);
    $this->assertEquals($this->sha1, $hash->getSha1());
  }

  /**
   * @test
   * -# Test getter for md5
   */
  public function testGetMd5()
  {
    $hash = new Hash($this->sha1, $this->md5, $this->sha256, $this->size);
    $this->assertEquals($this->md5, $hash->getMd5());
  }

  /**
   * @test
   * -# Test getter for sha256with valid data
   */
  public function testGetSha256()
  {
    $hash = new Hash($this->sha1, $this->md5, $this->sha256, $this->size);
    $this->assertEquals($this->sha256, $hash->getSha256());
  }

  /**
   * @test
   * -# Test getter for size
   */
  public function testGetSize()
  {
    $hash = new Hash($this->sha1, $this->md5, $this->sha256, $this->size);
    $this->assertEquals($this->size, $hash->getSize());
  }

  ////// GetArray Tests //////

  /**
   * @test
   * -# Test getArray with full data
   */
  public function testGetArrayWithFullData()
  {
    $hash = new Hash($this->sha1, $this->md5, $this->sha256, $this->size);
    
    $expectedArray = [
      'sha1' => $this->sha1,
      'md5' => $this->md5,
      'sha256' => $this->sha256,
      'size' => $this->size
    ];

    $this->assertEquals($expectedArray, $hash->getArray());
  }

  /**
   * @test
   * -# Test getArray with null values
   */
  public function testGetArrayWithNullValues()
  {
    $hash = new Hash(null, null, null, null);
    
    $expectedArray = [
      'sha1' => null,
      'md5' => null,
      'sha256' => null,
      'size' => null
    ];

    $this->assertEquals($expectedArray, $hash->getArray());
  }

  ////// CreateFromArray Tests //////

  /**
   * @test
   * -# Test createFromArray with valid full data
   */
  public function testCreateFromArrayWithValidData()
  {
    $inputArray = [
      'sha1' => $this->sha1,
      'md5' => $this->md5,
      'sha256' => $this->sha256,
      'size' => $this->size
    ];

    $hash = Hash::createFromArray($inputArray);
    $this->assertInstanceOf(Hash::class, $hash);
    $this->assertEquals($this->sha1, $hash->getSha1());
    $this->assertEquals($this->md5, $hash->getMd5());
    $this->assertEquals($this->sha256, $hash->getSha256());
    $this->assertEquals($this->size, $hash->getSize());
  }

  /**
   * @test
   * -# Test createFromArray with partial data
   */
  public function testCreateFromArrayWithPartialData()
  {
    $inputArray = [
      'sha1' => $this->sha1,
      'size' => $this->size
    ];

    $hash = Hash::createFromArray($inputArray);
    $this->assertInstanceOf(Hash::class, $hash);
    $this->assertEquals($this->sha1, $hash->getSha1());
    $this->assertNull($hash->getMd5());
    $this->assertNull($hash->getSha256());
    $this->assertEquals($this->size, $hash->getSize());
  }

  /**
   * @test
   * -# Test createFromArray with empty array
   */
  public function testCreateFromArrayWithEmptyArray()
  {
    $hash = Hash::createFromArray([]);
    $this->assertInstanceOf(Hash::class, $hash);
    $this->assertNull($hash->getSha1());
    $this->assertNull($hash->getMd5());
    $this->assertNull($hash->getSha256());
    $this->assertNull($hash->getSize());
  }

  /**
   * @test
   * -# Test createFromArray with invalid keys
   */
  public function testCreateFromArrayWithInvalidKeys()
  {
    $inputArray = [
      'sha1' => $this->sha1,
      'invalid_key' => 'value'
    ];

    $hash = Hash::createFromArray($inputArray);
    $this->assertNull($hash);
  }

  /**
   * @test
   * -# Test createFromArray with mixed valid and invalid keys
   */
  public function testCreateFromArrayWithMixedKeys()
  {
    $inputArray = [
      'sha1' => $this->sha1,
      'md5' => $this->md5,
      'invalid_key' => 'value',
      'size' => $this->size
    ];

    $hash = Hash::createFromArray($inputArray);
    $this->assertNull($hash);
  }
}