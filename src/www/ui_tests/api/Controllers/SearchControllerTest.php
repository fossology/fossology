<?php
/*
 SPDX-FileCopyrightText: © 2026 Fossology contributors

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @file
 * @brief Tests for SearchController
 */

namespace Fossology\UI\Api\Test\Controllers;

require_once dirname(__DIR__, 4) . '/lib/php/common-parm.php';

use Fossology\Lib\Dao\SearchHelperDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\UI\Api\Controllers\SearchController;
use Fossology\UI\Api\Exceptions\HttpBadRequestException;
use Fossology\UI\Api\Helper\DbHelper;
use Fossology\UI\Api\Helper\ResponseHelper;
use Fossology\UI\Api\Helper\RestHelper;
use Fossology\UI\Api\Models\ApiVersion;
use Mockery as M;
use Slim\Psr7\Factory\StreamFactory;
use Slim\Psr7\Headers;
use Slim\Psr7\Request;
use Slim\Psr7\Uri;

/**
 * @class SearchControllerTest
 * @brief Unit tests for SearchController
 */
class SearchControllerTest extends \PHPUnit\Framework\TestCase
{
  /**
   * @var SearchController $searchController
   * SearchController object to test
   */
  private $searchController;

  /**
   * @var SearchHelperDao|M\MockInterface $searchHelperDao
   * SearchHelperDao mock
   */
  private $searchHelperDao;

  /**
   * @var RestHelper|M\MockInterface $restHelper
   * RestHelper mock
   */
  private $restHelper;

  /**
   * @var StreamFactory $streamFactory
   * Stream factory to create body streams
   */
  private $streamFactory;

  /**
   * @brief Setup test objects
   * @see PHPUnit_Framework_TestCase::setUp()
   */
  protected function setUp() : void
  {
    $container = M::mock('Psr\Container\ContainerInterface');
    $this->restHelper = M::mock(RestHelper::class);
    $this->restHelper->shouldReceive('getDbHelper')
      ->andReturn(M::mock(DbHelper::class));
    $this->searchHelperDao = M::mock(SearchHelperDao::class);
    $container->shouldReceive('get')->withArgs(array('helper.restHelper'))
      ->andReturn($this->restHelper);
    $container->shouldReceive('get')->withArgs(array('dao.searchhelperdao'))
      ->andReturn($this->searchHelperDao);
    $this->searchController = new SearchController($container);
    $this->streamFactory = new StreamFactory();
  }

  /**
   * @brief Remove test objects
   * @see PHPUnit_Framework_TestCase::tearDown()
   */
  protected function tearDown() : void
  {
    M::close();
  }

  /**
   * Values of uploadId which are not a non-negative int4
   * @return array
   */
  public function invalidUploadIdProvider()
  {
    return [
      'non-digit' => ['abc'],
      'negative' => ['-1'],
      'decimal' => ['1.5'],
      'exponent' => ['1e3'],
      'above int4' => ['2147483648'],
    ];
  }

  /**
   * API versions to test
   * @return array
   */
  public function versionProvider()
  {
    return [
      'v1' => [ApiVersion::V1],
      'v2' => [ApiVersion::V2],
    ];
  }

  /**
   * Call SearchController::performSearch() with the given query
   * @param string $version API version to use
   * @param array $params Search parameters
   * @return ResponseHelper
   */
  private function performSearch($version, $params)
  {
    $requestHeaders = new Headers();
    if ($version == ApiVersion::V1) {
      foreach ($params as $key => $value) {
        $requestHeaders->setHeader($key, $value);
      }
    }
    $body = $this->streamFactory->createStream();
    $request = new Request("GET", new Uri("HTTP", "localhost"),
      $requestHeaders, [], [], $body);
    if ($version == ApiVersion::V2) {
      $request = $request->withQueryParams($params);
    }
    $request = $request->withAttribute(ApiVersion::ATTRIBUTE_NAME, $version);
    return $this->searchController->performSearch($request,
      new ResponseHelper(), []);
  }

  /**
   * @test
   * -# Test SearchController::performSearch() with invalid uploadId for V1
   * -# Check for 400 response
   * @dataProvider invalidUploadIdProvider
   */
  public function testPerformSearchInvalidUploadIdV1($uploadId)
  {
    $this->expectException(HttpBadRequestException::class);
    $this->expectExceptionMessage("uploadId must be a non-negative integer!");
    $this->performSearch(ApiVersion::V1,
      ['filename' => 'README', 'uploadId' => $uploadId]);
  }

  /**
   * @test
   * -# Test SearchController::performSearch() with invalid uploadId for V2
   * -# Check for 400 response
   * @dataProvider invalidUploadIdProvider
   */
  public function testPerformSearchInvalidUploadIdV2($uploadId)
  {
    $this->expectException(HttpBadRequestException::class);
    $this->expectExceptionMessage("uploadId must be a non-negative integer!");
    $this->performSearch(ApiVersion::V2,
      ['filename' => 'README', 'uploadId' => $uploadId]);
  }

  /**
   * @test
   * -# Test SearchController::performSearch() with the largest valid uploadId
   * -# Check that uploadId validation passes and the next check is reached
   */
  public function testPerformSearchMaxUploadIdAccepted()
  {
    $this->expectException(HttpBadRequestException::class);
    $this->expectExceptionMessage(
      "At least one parameter, containing a value is required");
    $this->performSearch(ApiVersion::V2, ['uploadId' => '2147483647']);
  }

  /**
   * @test
   * -# Test SearchController::performSearch() with a valid uploadId
   * -# Check that it reaches SearchHelperDao::GetResults() as an integer
   * -# Check for 200 response
   * @dataProvider versionProvider
   */
  public function testPerformSearchValidUploadId($version)
  {
    $uploadDao = M::mock(UploadDao::class);
    $this->restHelper->shouldReceive('getUploadDao')->andReturn($uploadDao);
    $this->restHelper->shouldReceive('getGroupId')->andReturn(2);
    $this->searchHelperDao->shouldReceive('GetResults')->once()
      ->withArgs(function ($item, $filename, $uploadId) {
        return $filename === 'README' && $uploadId === 2;
      })
      ->andReturn([[], 0]);

    $response = $this->performSearch($version,
      ['filename' => 'README', 'uploadId' => '2']);

    $this->assertEquals(200, $response->getStatusCode());
    $this->assertEquals('0', $response->getHeaderLine('X-Total-Pages'));
  }
}
