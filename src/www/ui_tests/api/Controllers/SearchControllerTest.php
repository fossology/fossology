<?php
/*
 SPDX-FileCopyrightText: © 2026 Fossology contributors

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * IMPORTANT for unit tests:
 * SearchController calls GetParm("item", PARM_INTEGER) which is normally
 * provided by the Fossology runtime. In unit tests we stub it.
 */
namespace {
  if (!defined('PARM_INTEGER')) {
    define('PARM_INTEGER', 1);
  }

  if (!function_exists('GetParm')) {
    function GetParm($name, $type)
    {
      // In these controller unit tests, we don't care about "item".
      // Returning 0 means "no specific item selected".
      return 0;
    }
  }
}

namespace Fossology\UI\Api\Test\Controllers {

use Fossology\UI\Api\Controllers\SearchController;
use Fossology\UI\Api\Exceptions\HttpBadRequestException;
use Fossology\UI\Api\Helper\ResponseHelper;
use Mockery as M;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\StreamFactory;
use Slim\Psr7\Headers;
use Slim\Psr7\Request;
use Slim\Psr7\Uri;

require_once dirname(__DIR__, 4) . "/lib/php/Plugin/FO_Plugin.php";

/**
 * @class SearchControllerTest
 * @brief Tests for SearchController
 */
class SearchControllerTest extends TestCase
{
  /** @var \Mockery\MockInterface */
  private $container;

  /** @var \Mockery\MockInterface */
  private $restHelper;

  /** @var \Mockery\MockInterface */
  private $dbHelper;

  /** @var \Mockery\MockInterface */
  private $searchHelperDao;

  /** @var \Mockery\MockInterface */
  private $uploadDao;

  /** @var StreamFactory */
  private $streamFactory;

  /** @var SearchController */
  private $searchController;

  protected function setUp(): void
  {
    parent::setUp();

    // RestController relies on a global $container in these tests
    global $container;
    $container = M::mock('ContainerBuilder');

    $this->container = $container;
    $this->restHelper = M::mock();
    $this->dbHelper = M::mock();
    $this->searchHelperDao = M::mock();
    $this->uploadDao = M::mock();
    $this->streamFactory = new StreamFactory();

    // RestController constructor path: container->get('helper.restHelper')
    $this->container->shouldReceive('get')
      ->with('helper.restHelper')
      ->andReturn($this->restHelper);

    // SearchController::performSearch() pulls the DAO from container
    $this->container->shouldReceive('get')
      ->with('dao.searchhelperdao')
      ->andReturn($this->searchHelperDao);

    // Controller uses dbHelper via restHelper->getDbHelper()
    $this->restHelper->shouldReceive('getDbHelper')->andReturn($this->dbHelper);

    $this->searchController = new SearchController($this->container);
  }

  protected function tearDown(): void
  {
    M::close();
    parent::tearDown();
  }

  /**
   * @test
   * - If no search params provided, should throw 400 HttpBadRequestException
   */
  public function testPerformSearchMissingAllParamsThrowsBadRequest()
  {
    $requestHeaders = new Headers();
    $body = $this->streamFactory->createStream('');
    $request = new Request(
      'GET',
      new Uri('http', 'localhost'),
      $requestHeaders,
      [],
      [],
      $body
    );
    $response = new ResponseHelper();

    $this->expectException(HttpBadRequestException::class);

    $this->searchController->performSearch($request, $response, []);
  }

  /**
   * @test
   * - Invalid page/limit should throw 400
   */
  public function testPerformSearchInvalidPageOrLimitThrowsBadRequest()
  {
    $requestHeaders = new Headers();
    $requestHeaders->setHeader('filename', 'abc');
    $requestHeaders->setHeader('page', '0');   // invalid (must be >= 1)
    $requestHeaders->setHeader('limit', '10');

    $body = $this->streamFactory->createStream('');
    $request = new Request(
      'GET',
      new Uri('http', 'localhost'),
      $requestHeaders,
      [],
      [],
      $body
    );
    $response = new ResponseHelper();

    $this->expectException(HttpBadRequestException::class);

    $this->searchController->performSearch($request, $response, []);
  }

  /**
   * @test
   * - Valid search should return 200 and set X-Total-Pages header
   * - Also ensures limit is capped at 100
   */
  public function testPerformSearchHappyPathReturnsResultsAndTotalPages()
  {
    $requestHeaders = new Headers();
    $requestHeaders->setHeader('filename', 'abc');
    $requestHeaders->setHeader('limit', '999'); // should be capped to 100
    $requestHeaders->setHeader('page', '1');

    $body = $this->streamFactory->createStream('');
    $request = new Request(
      'GET',
      new Uri('http', 'localhost'),
      $requestHeaders,
      [],
      [],
      $body
    );
    $response = new ResponseHelper();

    // restHelper calls used by performSearch()
    $this->restHelper->shouldReceive('getUploadDao')->andReturn($this->uploadDao);
    $this->restHelper->shouldReceive('getGroupId')->andReturn(2);
    $this->restHelper->shouldReceive('getUserId')->andReturn(3);

    // DAO returns one result, count = 101 => total pages = ceil(101/100)=2
    $results = [
      [
        'upload_fk' => 55,
        'uploadtree_pk' => 777,
      ],
    ];

    $this->searchHelperDao->shouldReceive('GetResults')
      ->once()
      ->andReturn([$results, 101]);

    // dbHelper->getUploads returns [?, [[upload]]], controller uses index [1]
    $upload = ['upload_pk' => 55, 'uploadname' => 'u1'];

    $this->dbHelper->shouldReceive('getUploads')
      ->once()
      ->andReturn([null, [[$upload]]]);

    $this->dbHelper->shouldReceive('getFilenameFromUploadTree')
      ->once()
      ->with(777)
      ->andReturn('file1.c');

    $out = $this->searchController->performSearch($request, $response, []);

    $this->assertTrue($out->hasHeader('X-Total-Pages'));
    $this->assertEquals('2', $out->getHeaderLine('X-Total-Pages'));
  }
}

}
