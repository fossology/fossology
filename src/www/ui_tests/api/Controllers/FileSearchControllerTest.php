<?php
/*
 SPDX-FileCopyrightText: © 2026 FOSSology contributors

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @file
 * @brief Tests for FileSearchController
 */

namespace Fossology\UI\Api\Test\Controllers;

use Fossology\Lib\Dao\ClearingDao;
use Fossology\Lib\Dao\LicenseDao;
use Fossology\UI\Api\Controllers\FileSearchController;
use Fossology\UI\Api\Exceptions\HttpBadRequestException;
use Fossology\UI\Api\Helper\FileHelper;
use Fossology\UI\Api\Helper\ResponseHelper;
use Fossology\UI\Api\Helper\RestHelper;
use Mockery as M;
use Slim\Psr7\Factory\StreamFactory;
use Slim\Psr7\Headers;
use Slim\Psr7\Request;
use Slim\Psr7\Uri;

/**
 * @class FileSearchControllerTest
 * @brief Unit tests for FileSearchController
 */
class FileSearchControllerTest extends \PHPUnit\Framework\TestCase
{
  /**
   * @var integer $assertCountBefore
   * Assertions before running tests
   */
  private $assertCountBefore;

  /**
   * @var FileSearchController $fileSearchController
   * FileSearchController instance
   */
  private $fileSearchController;

  /**
   * @var StreamFactory $streamFactory
   * Stream factory for creating request bodies
   */
  private $streamFactory;

  /**
   * @brief Setup test objects
   * @see PHPUnit_Framework_TestCase::setUp()
   */
  protected function setUp() : void
  {
    $this->assertCountBefore = \Hamcrest\MatcherAssert::getCount();

    $container = M::mock('ContainerInterface');
    $restHelper = M::mock('overload:Fossology\UI\Api\Helper\RestHelper');
    $fileHelper = M::mock('overload:Fossology\UI\Api\Helper\FileHelper');
    $clearingDao = M::mock('overload:Fossology\Lib\Dao\ClearingDao');
    $licenseDao = M::mock('overload:Fossology\Lib\Dao\LicenseDao');

    $container->shouldReceive('get')->withArgs(['helper.restHelper'])
      ->andReturn($restHelper);
    $container->shouldReceive('get')->withArgs(['helper.fileHelper'])
      ->andReturn($fileHelper);
    $container->shouldReceive('get')->withArgs(['dao.clearing'])
      ->andReturn($clearingDao);
    $container->shouldReceive('get')->withArgs(['dao.license'])
      ->andReturn($licenseDao);

    $this->fileSearchController = new FileSearchController($container);
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
   * Helper function to get JSON array from response
   *
   * @param ResponseHelper $response
   * @return array Decoded response
   */
  private function getResponseJson($response)
  {
    $response->getBody()->seek(0);
    return json_decode($response->getBody()->getContents(), true);
  }

  /**
   * @test
   * -# Test for FileSearchController::getFiles() with null request body
   * -# Check if HttpBadRequestException is thrown with HTTP 400
   */
  public function testGetFilesWithNullBody()
  {
    $requestHeaders = new Headers();
    $requestHeaders->setHeader('Content-Type', 'application/json');
    $body = $this->streamFactory->createStream('null');
    $request = new Request("POST", new Uri("HTTP", "localhost"),
      $requestHeaders, [], [], $body);

    $this->expectException(HttpBadRequestException::class);
    $this->expectExceptionMessage("Request body is missing or invalid");

    $this->fileSearchController->getFiles($request, new ResponseHelper(), []);
  }

  /**
   * @test
   * -# Test for FileSearchController::getFiles() with empty request body
   * -# Check if HttpBadRequestException is thrown with HTTP 400
   */
  public function testGetFilesWithEmptyBody()
  {
    $requestHeaders = new Headers();
    $body = $this->streamFactory->createStream('');
    $request = new Request("POST", new Uri("HTTP", "localhost"),
      $requestHeaders, [], [], $body);

    $this->expectException(HttpBadRequestException::class);
    $this->expectExceptionMessage("Request body is missing or invalid");

    $this->fileSearchController->getFiles($request, new ResponseHelper(), []);
  }

  /**
   * @test
   * -# Test for FileSearchController::getFiles() with non-array request body
   * -# Check if HttpBadRequestException is thrown with HTTP 400
   */
  public function testGetFilesWithNonArrayBody()
  {
    $requestHeaders = new Headers();
    $requestHeaders->setHeader('Content-Type', 'application/json');
    $body = $this->streamFactory->createStream('"not an array"');
    $request = new Request("POST", new Uri("HTTP", "localhost"),
      $requestHeaders, [], [], $body);

    $this->expectException(HttpBadRequestException::class);
    $this->expectExceptionMessage("Request body is missing or invalid");

    $this->fileSearchController->getFiles($request, new ResponseHelper(), []);
  }
}
