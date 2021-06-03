<?php
/***************************************************************
 * Copyright (C) 2020 Siemens AG
 * Author: Gaurav Mishra <mishra.gaurav@siemens.com>
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 ***************************************************************/
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

use Mockery as M;
use Fossology\UI\Api\Helper\RestHelper;
use Fossology\UI\Api\Models\Info;
use Fossology\UI\Api\Models\InfoType;
use Fossology\UI\Api\Controllers\AuthController;
use Fossology\UI\Api\Helper\DbHelper;
use Fossology\UI\Api\Helper\AuthHelper;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\Body;
use Slim\Http\Uri;
use Slim\Http\Headers;

/**
 * @class AuthControllerTest
 * @brief Test for AtuhController
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
   * @brief Setup test objects
   * @see PHPUnit_Framework_TestCase::setUp()
   */
  protected function setUp()
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
  }

  /**
   * @brief Remove test objects
   * @see PHPUnit_Framework_TestCase::tearDown()
   */
  protected function tearDown()
  {
    $this->addToAssertionCount(
      \Hamcrest\MatcherAssert::getCount() - $this->assertCountBefore);
    M::close();
  }

  /**
   * @test
   * Test is getAuthHeaders always return deprecation notice
   */
  public function testGetAuthHeaders()
  {
    $request = M::mock(Request::class);
    $response = new Response();
    $response = $this->authController->getAuthHeaders($request, $response,
      array());

    $warningMessage = "The resource is deprecated. Use /tokens";
    $returnVal = new Info(406, $warningMessage, InfoType::ERROR);

    $response->getBody()->seek(0);
    $this->assertEquals($warningMessage, $response->getHeaderLine('Warning'));
    $this->assertEquals($returnVal->getArray(),
      json_decode($response->getBody()->getContents(), true));
    $this->assertEquals($returnVal->getCode(), $response->getStatusCode());
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
      '2020-01-01', 'test_token', 'read'))->andReturn(true);
    $this->restHelper->shouldReceive('getAuthHelper')->andReturn($authHelper);
    $this->restHelper->shouldReceive('getUserId')->andReturn(2);
    $container->shouldReceive('get')->withArgs(array('helper.restHelper'))
      ->andReturn($this->restHelper);

    $body = new Body(fopen('php://temp', 'r+'));
    $body->write(json_encode([
        "username" => "foss",
        "password" => "foss",
        "token_name" => "test_token",
        "token_scope" => "read",
        "token_expire" => "2020-01-01"
      ]));
    $requestHeaders = new Headers();
    $requestHeaders->set('Content-Type', 'application/json');
    $request = new Request("POST", new Uri("HTTP", "localhost"), $requestHeaders,
      [], [], $body);
    $response = new Response();
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
   * -# Check if failed response if returned
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
    $failedResponse = new Info(400, "error text", InfoType::ERROR);
    $this->restHelper->shouldReceive('validateTokenRequest') ->withArgs(array(
      '2020-01-02', 'test_token', 'read'))->andReturn($failedResponse);
    $this->restHelper->shouldReceive('getAuthHelper')->andReturn($authHelper);
    $this->restHelper->shouldReceive('getUserId')->andReturn(2);
    $container->shouldReceive('get')->withArgs(array(
      'helper.restHelper'))->andReturn($this->restHelper);

    $body = new Body(fopen('php://temp', 'r+'));
    $body->write(json_encode([
        "username" => "foss",
        "password" => "foss",
        "token_name" => "test_token",
        "token_scope" => "read",
        "token_expire" => "2020-01-02"
      ]));
    $requestHeaders = new Headers();
    $requestHeaders->set('Content-Type', 'application/json');
    $request = new Request("POST", new Uri("HTTP", "localhost"), $requestHeaders,
      [], [], $body);
    $response = new Response();
    $response = $this->authController->createNewJwtToken($request, $response,
      []);
    $response->getBody()->seek(0);
    $this->assertEquals($failedResponse->getArray(),
      json_decode($response->getBody()->getContents(), true));
    $this->assertEquals($failedResponse->getCode(), $response->getStatusCode());
  }

  /**
   * @test
   * -# Mock the request to get a new token
   * -# Mock a failed response of AuthHelper::checkUsernameAndPassword()
   * -# Call AuthController::createNewJwtToken()
   * -# Check if failed response if returned
   */
  public function testCreateNewJwtTokenInvalidPassword()
  {
    global $container;
    $authHelper = M::mock(AuthHelper::class);
    $authHelper->shouldReceive('checkUsernameAndPassword')->withArgs([
      'foss', 'foss'])->andReturn(false);
    $this->restHelper->shouldReceive('validateTokenRequest')->withArgs(array(
      '2020-01-03', 'test_token', 'read'))->andReturn(true);
    $this->restHelper->shouldReceive('getAuthHelper')->andReturn($authHelper);
    $this->restHelper->shouldReceive('getUserId')->andReturn(2);
    $container->shouldReceive('get')->withArgs(array(
      'helper.restHelper'))->andReturn($this->restHelper);

    $body = new Body(fopen('php://temp', 'r+'));
    $body->write(json_encode([
        "username" => "foss",
        "password" => "foss",
        "token_name" => "test_token",
        "token_scope" => "read",
        "token_expire" => "2020-01-03"
      ]));
    $requestHeaders = new Headers();
    $requestHeaders->set('Content-Type', 'application/json');
    $request = new Request("POST", new Uri("HTTP", "localhost"), $requestHeaders,
      [], [], $body);
    $response = new Response();
    $failedResponse = new Info(404, "Username or password incorrect.",
      InfoType::ERROR);
    $response = $this->authController->createNewJwtToken($request, $response,
      []);
    $response->getBody()->seek(0);
    $this->assertEquals($failedResponse->getArray(),
      json_decode($response->getBody()->getContents(), true));
    $this->assertEquals($failedResponse->getCode(), $response->getStatusCode());
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
