<?php
/*
 SPDX-FileCopyrightText: © 2020 Siemens AG
 Author: Gaurav Mishra <mishra.gaurav@siemens.com>

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @dir
 * @brief Unit test cases for API controllers
 * @file
 * @brief Unit tests for AuthController
 */

/**
 * @namespace Fossology::UI::Api::Test::Controllers
 *            Unit tests for controllers
 */
namespace Fossology\UI\Api\Test\Controllers;

use Fossology\UI\Api\Controllers\AuthController;
use Fossology\UI\Api\Exceptions\HttpBadRequestException;
use Fossology\UI\Api\Exceptions\HttpNotFoundException;
use Fossology\UI\Api\Helper\AuthHelper;
use Fossology\UI\Api\Helper\DbHelper;
use Fossology\UI\Api\Helper\ResponseHelper;
use Fossology\UI\Api\Helper\RestHelper;
use Mockery as M;
use Slim\Psr7\Factory\StreamFactory;
use Slim\Psr7\Headers;
use Slim\Psr7\Request;
use Slim\Psr7\Uri;

/**
 * @class AuthControllerTest
 * @brief Test for AuthController
 */
class AuthControllerTest extends \PHPUnit\Framework\TestCase
{

  /**
   * @var DbHelper $dbHelper
   * DB Helper mock
   */
  private $dbHelper;

  /**
   * @var RestHelper $restHelper
   * RestHelper mock
   */
  private $restHelper;

  /**
   * @var AuthController $authController
   * Test object
   */
  private $authController;

  /**
   * @var integer $assertCountBefore
   * Assertions before running tests
   */
  private $assertCountBefore;

  /**
   * @var StreamFactory $streamFactory
   * Stream factory to create body streams.
   */
  private $streamFactory;

  /**
   * @brief Setup test objects
   * @see PHPUnit_Framework_TestCase::setUp()
   */
  protected function setUp() : void
  {
    global $container;
    $container = M::mock('ContainerBuilder');
    $this->dbHelper = M::mock(DbHelper::class);
    $this->restHelper = M::mock(RestHelper::class);

    $this->restHelper->shouldReceive('getDbHelper')->andReturn($this->dbHelper);

    $container->shouldReceive('get')->withArgs(array(
      'helper.restHelper'))->andReturn($this->restHelper);
    $this->authController = new AuthController($container);
    $this->assertCountBefore = \Hamcrest\MatcherAssert::getCount();
    $this->streamFactory = new StreamFactory();
  }

  /**
   * @brief Remove test objects
   * @see PHPUnit_Framework_TestCase::tearDown()
   */
  protected function tearDown() : void
  {
    $this->addToAssertionCount(
      \Hamcrest\MatcherAssert::getCount() - $this->assertCountBefore);
    M::close();
  }

  /**
   * @test
   * -# Mock the request to get a new token
   * -# Call AuthController::createNewJwtToken()
   * -# Check if response contains a new JWT token
   */
  public function testCreateNewJwtToken()
  {
    global $container;
    $authHelper = M::mock(AuthHelper::class);
    $authHelper->shouldReceive('checkUsernameAndPassword')->withArgs([
      'foss','foss'])->andReturn(true);
    $authHelper->shouldReceive('generateJwtToken')->withArgs([
      '2020-01-01', '2020-01-01', '2.2', 'read', M::any()])
      ->andReturn("sometoken");
    $this->dbHelper->shouldReceive('insertNewTokenKey')->withArgs(array(
      2, '2020-01-01', 'r', 'test_token', M::any()))->andReturn([
      "jti" => "2.2",
      "created_on" => '2020-01-01'
    ]);
    $this->restHelper->shouldReceive('validateTokenRequest')->withArgs(array(
      '2020-01-01', 'test_token', 'read'))->andReturnNull();
    $this->restHelper->shouldReceive('getAuthHelper')->andReturn($authHelper);
    $this->restHelper->shouldReceive('getUserId')->andReturn(2);
    $container->shouldReceive('get')->withArgs(array('helper.restHelper'))
      ->andReturn($this->restHelper);

    $body = $this->streamFactory->createStream(json_encode([
      "username" => "foss",
      "password" => "foss",
      "token_name" => "test_token",
      "token_scope" => "read",
      "token_expire" => "2020-01-01"
    ]));
    $requestHeaders = new Headers();
    $requestHeaders->setHeader('Content-Type', 'application/json');
    $request = new Request("POST", new Uri("HTTP", "localhost"),
      $requestHeaders, [], [], $body);
    $response = new ResponseHelper();
    $GLOBALS['SysConf'] = ['AUTHENTICATION' => ['resttoken' => 'token']];
    $response = $this->authController->createNewJwtToken($request, $response,
      []);
    $response->getBody()->seek(0);
    $this->assertEquals(["Authorization" => "Bearer sometoken"],
      json_decode($response->getBody()->getContents(), true));
    $this->assertEquals(201, $response->getStatusCode());
  }

  /**
   * @test
   * -# Mock the request to get a new token
   * -# Mock a failed response of RestHelper::validateTokenRequest()
   * -# Call AuthController::createNewJwtToken()
   * -# Check if failed response is returned
   */
  public function testCreateNewJwtTokenExpiredToken()
  {
    global $container;
    $authHelper = M::mock(AuthHelper::class);
    $authHelper->shouldReceive('checkUsernameAndPassword')->withArgs([
      'foss', 'foss'])->andReturn(true);
    $authHelper->shouldReceive('generateJwtToken')->withArgs([
      '2020-01-02', '2020-01-01', '2.2', 'read', M::any()])
      ->andReturn("sometoken");
    $this->dbHelper->shouldReceive('insertNewTokenKey')->withArgs(array(
      2, '2020-01-02', 'r', 'test_token', M::any()))->andReturn([
      "jti" => "2.2",
      "created_on" => '2020-01-01'
    ]);
    $this->restHelper->shouldReceive('validateTokenRequest') ->withArgs(array(
      '2020-01-02', 'test_token', 'read'))->andThrowExceptions([
        new HttpBadRequestException(
          "bad req"
        )]);
    $this->restHelper->shouldReceive('getAuthHelper')->andReturn($authHelper);
    $this->restHelper->shouldReceive('getUserId')->andReturn(2);
    $container->shouldReceive('get')->withArgs(array(
      'helper.restHelper'))->andReturn($this->restHelper);

    $body = $this->streamFactory->createStream(json_encode([
      "username" => "foss",
      "password" => "foss",
      "token_name" => "test_token",
      "token_scope" => "read",
      "token_expire" => "2020-01-02"
    ]));
    $requestHeaders = new Headers();
    $requestHeaders->setHeader('Content-Type', 'application/json');
    $request = new Request("POST", new Uri("HTTP", "localhost"), $requestHeaders,
      [], [], $body);
    $response = new ResponseHelper();
    $GLOBALS['SysConf'] = ['AUTHENTICATION' => ['resttoken' => 'token']];

    $this->expectException(HttpBadRequestException::class);
    $this->expectExceptionCode(400);

    $this->authController->createNewJwtToken($request, $response, []);
  }

  /**
   * @test
   * -# Mock the request to get a new token
   * -# Mock a failed response of AuthHelper::checkUsernameAndPassword()
   * -# Call AuthController::createNewJwtToken()
   * -# Check if failed response is returned
   */
  public function testCreateNewJwtTokenInvalidPassword()
  {
    global $container;
    $authHelper = M::mock(AuthHelper::class);
    $authHelper->shouldReceive('checkUsernameAndPassword')->withArgs([
      'foss', 'foss'])->andReturn(false);
    $this->restHelper->shouldReceive('validateTokenRequest')->withArgs(array(
      '2020-01-03', 'test_token', 'read'))->andReturnNull();
    $this->restHelper->shouldReceive('getAuthHelper')->andReturn($authHelper);
    $this->restHelper->shouldReceive('getUserId')->andReturn(2);
    $container->shouldReceive('get')->withArgs(array(
      'helper.restHelper'))->andReturn($this->restHelper);

    $body = $this->streamFactory->createStream(json_encode([
      "username" => "foss",
      "password" => "foss",
      "token_name" => "test_token",
      "token_scope" => "read",
      "token_expire" => "2020-01-03"
    ]));
    $requestHeaders = new Headers();
    $requestHeaders->setHeader('Content-Type', 'application/json');
    $request = new Request("POST", new Uri("HTTP", "localhost"), $requestHeaders,
      [], [], $body);
    $response = new ResponseHelper();
    $GLOBALS['SysConf'] = ['AUTHENTICATION' => ['resttoken' => 'token']];
    $this->expectException(HttpNotFoundException::class);
    $this->expectExceptionCode(404);

    $this->authController->createNewJwtToken($request, $response, []);
  }

  /**
   * @test
   * -# Test if the function AuthController::arrayKeyExists returns true for
   * request where all key exists
   * -# Test if the function AuthController::arrayKeyExists returns true for
   * request with one extra key
   * -# Test if the function AuthController::arrayKeyExists returns false for
   * request with missing keys
   */
  public function testArrayKeysExists()
  {
    $reflection = new \ReflectionClass(get_class($this->authController));
    $method = $reflection->getMethod('arrayKeysExists');
    $method->setAccessible(true);
    $result = $method->invokeArgs($this->authController, [
        ["key1" => "value1", "key2" => "value2"],
        ["key1", "key2"]
      ]);
    $this->assertTrue($result);
    $result = $method->invokeArgs($this->authController, [
        ["key1" => "value1", "key2" => "value2"],
        ["key1"]
      ]);
    $this->assertTrue($result);
    $result = $method->invokeArgs($this->authController, [
        ["key1" => "value1", "key2" => "value2"],
        ["key1", "key2", "key3"]
      ]);
    $this->assertFalse($result);
  }
}
