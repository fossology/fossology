<?php
/*
 SPDX-FileCopyrightText: © 2025 Harshit Gandhi <gandhiharshit716@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @file
 * @brief Tests for TokenRequest model
 */

namespace Fossology\UI\Api\Test\Models;

use Fossology\UI\Api\Models\TokenRequest;
use Fossology\UI\Api\Exceptions\HttpBadRequestException;
use PHPUnit\Framework\TestCase;
use DateTime;

/**
 * @class TokenRequestTest
 * @brief Tests for TokenRequest model
 */
class TokenRequestTest extends TestCase
{
  private $sampleData;

  /**
   * Sets up sample data before every test
   */
  protected function setUp(): void
  {
    $this->sampleData = [
      'tokenName' => 'TestToken',
      'tokenScope' => 'read',
      'tokenExpire' => '2025-12-31',
      'username' => 'testUser',
      'password' => 'testPassword'
    ];
  }

  ////// Constructor Tests //////

  /**
   * Tests that the TokenRequest constructor initializes an instance correctly.
   *
   * @return void
   */
  public function testConstructor()
  {
    $tokenRequest = new TokenRequest(...array_values($this->sampleData));
    $this->assertInstanceOf(TokenRequest::class, $tokenRequest);
  }

  ////// Getter Tests //////

  /**
   * @test
   * -# Test getter for Token Name
   */
  public function testGetTokenName()
  {
    $tokenRequest = new TokenRequest(...array_values($this->sampleData));
    $this->assertEquals($this->sampleData['tokenName'], $tokenRequest->getTokenName());
  }

  /**
   * @test
   * -# Test getter for Token Scope
   */
  public function testGetTokenScope()
  {
    $tokenRequest = new TokenRequest(...array_values($this->sampleData));
    $this->assertEquals('r', $tokenRequest->getTokenScope());
  }

  /**
   * @test
   * -# Test getter for Token Expire
   */
  public function testGetTokenExpire()
  {
    $tokenRequest = new TokenRequest(...array_values($this->sampleData));
    $this->assertEquals($this->sampleData['tokenExpire'], $tokenRequest->getTokenExpire());
  }

  /**
   * @test
   * -# Test getter for Username
   */
  public function testGetUsername()
  {
    $tokenRequest = new TokenRequest(...array_values($this->sampleData));
    $this->assertEquals($this->sampleData['username'], $tokenRequest->getUsername());
  }

  /**
   * @test
   * -# Test getter for Password
   */
  public function testGetPassword()
  {
    $tokenRequest = new TokenRequest(...array_values($this->sampleData));
    $this->assertEquals($this->sampleData['password'], $tokenRequest->getPassword());
  }

  ////// Setter Tests //////

  /**
   * @test
   * -# Test setter for Token Name
   */
  public function testSetTokenName()
  {
    $tokenRequest = new TokenRequest(...array_values($this->sampleData));
    $tokenRequest->setTokenName('NewToken');
    $this->assertEquals('NewToken', $tokenRequest->getTokenName());
  }

  /**
   * @test
   * -# Test setter for Token Scope
   */
  public function testSetTokenScope()
  {
    $tokenRequest = new TokenRequest(...array_values($this->sampleData));
    $tokenRequest->setTokenScope('write');
    $this->assertEquals('w', $tokenRequest->getTokenScope());
  }

  /**
   * @test
   * -# Test setter for Token Expire
   */
  public function testSetTokenExpire()
  {
    $tokenRequest = new TokenRequest(...array_values($this->sampleData));
    $tokenRequest->setTokenExpire('2026-01-01');
    $this->assertEquals('2026-01-01', $tokenRequest->getTokenExpire());
  }

  /**
   * @test
   * -# Test setter for Username
   */
  public function testSetUsername()
  {
    $tokenRequest = new TokenRequest(...array_values($this->sampleData));
    $tokenRequest->setUsername('testUser');
    $this->assertEquals('testUser', $tokenRequest->getUsername());
  }

  /**
   * @test
   * -# Test setter for Password
   */
  public function testSetPassword()
  {
    $tokenRequest = new TokenRequest(...array_values($this->sampleData));
    $tokenRequest->setPassword('testPassword');
    $this->assertEquals('testPassword', $tokenRequest->getPassword());
  }

  ////// From Array Tests //////

  /**
   * Tests the TokenRequest::fromArray() method for version 1.
   *
   * This test verifies that calling fromArray() with a valid input array
   * and version 1 correctly returns an instance of the TokenRequest class.
   *
   * @return void
   */
  public function testFromArrayVersion1()
  {
    $input = [
      'username' => 'testUser',
      'password' => 'testPassword',
      'token_name' => 'TestToken',
      'token_scope' => 'read',
      'token_expire' => '2025-12-31'
    ];
    
    $tokenRequest = TokenRequest::fromArray($input, 1);
    $this->assertInstanceOf(TokenRequest::class, $tokenRequest);
  }

  /**
   * Tests the TokenRequest::fromArray() method for version 2.
   *
   * This test verifies that calling fromArray() with a valid input array
   * and version 2 correctly returns an instance of the TokenRequest class.
   *
   * @return void
   */
  public function testFromArrayVersion2()
  {
    $input = [
      'username' => 'testUser',
      'password' => 'testPassword',
      'tokenName' => 'TestToken',
      'tokenScope' => 'read',
      'tokenExpire' => '2025-12-31'
    ];
    
    $tokenRequest = TokenRequest::fromArray($input, 2);
    $this->assertInstanceOf(TokenRequest::class, $tokenRequest);
  }

  /**
   * Tests TokenRequest::fromArray() when required keys are missing.
   *
   * This test ensures that calling fromArray() with an incomplete input array
   * (missing required keys) throws an HttpBadRequestException.
   *
   * Expected Behavior:
   * - Since the input array lacks necessary fields, the method should 
   *   trigger an HttpBadRequestException.
   *
   * @return void
   * @throws HttpBadRequestException If required keys are missing.
   */
  public function testFromArrayMissingKeys()
  {
    $this->expectException(HttpBadRequestException::class);
    $input = ['username' => 'testUser'];
    TokenRequest::fromArray($input, 1);
  }
}
