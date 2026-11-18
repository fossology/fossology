<?php
/*
 SPDX-FileCopyrightText: © 2026 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @file
 * @brief Tests for SearchController
 */

namespace Fossology\UI\Api\Test\Controllers;

if (!defined('PARM_INTEGER')) {
  define('PARM_INTEGER', 1);
}
if (!function_exists('GetParm')) {
  function GetParm($parameterName, $parameterType)
  {
    return 0;
  }
}

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
 * @brief Test cases for SearchController
 */
class SearchControllerTest extends \PHPUnit\Framework\TestCase
{
  /** @var DbHelper $dbHelper */
  private $dbHelper;

  /** @var RestHelper $restHelper */
  private $restHelper;

  /** @var SearchHelperDao $searchHelperDao */
  private $searchHelperDao;

  /** @var UploadDao $uploadDao */
  private $uploadDao;

  /** @var SearchController $searchController */
  private $searchController;

  protected function setUp() : void
  {
    $container = M::mock('ContainerBuilder');
    $this->dbHelper = M::mock(DbHelper::class);
    $this->restHelper = M::mock(RestHelper::class);
    $this->searchHelperDao = M::mock(SearchHelperDao::class);
    $this->uploadDao = M::mock(UploadDao::class);

    $this->restHelper->shouldReceive('getDbHelper')->andReturn($this->dbHelper);
    $this->restHelper->shouldReceive('getUserId')->andReturn(1);
    $this->restHelper->shouldReceive('getGroupId')->andReturn(2);
    $this->restHelper->shouldReceive('getUploadDao')->andReturn($this->uploadDao);

    $container->shouldReceive('get')->withArgs(['helper.restHelper'])->andReturn($this->restHelper);
    $container->shouldReceive('get')->withArgs(['dao.searchhelperdao'])->andReturn($this->searchHelperDao);

    $this->searchController = new SearchController($container);
  }

  protected function tearDown() : void
  {
    M::close();
  }

  private function getResponseJson($response)
  {
    $response->getBody()->seek(0);
    return json_decode($response->getBody()->getContents(), true);
  }

  public function testPerformSearchRequiresAtLeastOneParameter()
  {
    $request = new Request("GET", new Uri("HTTP", "localhost"), new Headers(),
      [], [], (new StreamFactory())->createStream());

    $this->expectException(HttpBadRequestException::class);
    $this->searchController->performSearch($request, new ResponseHelper(), []);
  }

  public function testPerformSearchMemoizesUploadsAndReusesUfileName()
  {
    $headers = new Headers(['filename' => '*.c']);
    $request = new Request("GET", new Uri("HTTP", "localhost"), $headers,
      [], [], (new StreamFactory())->createStream());

    $dbRows = [
      [
        'upload_fk' => 10,
        'uploadtree_pk' => 101,
        'ufile_name' => 'alpha.c',
      ],
      [
        'upload_fk' => 10,
        'uploadtree_pk' => 102,
        'ufile_name' => 'beta.c',
      ],
      [
        'upload_fk' => 10,
        'uploadtree_pk' => 103,
        'ufile_name' => '0',
      ],
    ];

    $this->searchHelperDao->shouldReceive('GetResults')
      ->once()
      ->andReturn([$dbRows, 3]);

    $mockUpload = [
      'id' => 10,
      'folderid' => 1,
      'foldername' => 'root',
      'uploadname' => 'pkg.tar.gz',
    ];

    // Crucial: getUploads must only be called ONCE for upload_fk=10, not 3 times.
    $this->dbHelper->shouldReceive('getUploads')
      ->with(1, 2, 1, 1, 10, null, true)
      ->once()
      ->andReturn([1, [$mockUpload]]);

    // Crucial: getFilenameFromUploadTree must NEVER be called since ufile_name is present.
    $this->dbHelper->shouldReceive('getFilenameFromUploadTree')
      ->never();

    $response = $this->searchController->performSearch($request, new ResponseHelper(), []);
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertEquals('1', $response->getHeaderLine('X-Total-Pages'));

    $json = $this->getResponseJson($response);
    $this->assertCount(3, $json);
    $this->assertEquals('alpha.c', $json[0]['filename']);
    $this->assertEquals('beta.c', $json[1]['filename']);
    $this->assertEquals('0', $json[2]['filename']);
    $this->assertEquals(101, $json[0]['uploadTreeId']);
    $this->assertEquals(102, $json[1]['uploadTreeId']);
    $this->assertEquals(103, $json[2]['uploadTreeId']);
    $this->assertEquals($mockUpload, $json[0]['upload']);
    $this->assertEquals($mockUpload, $json[1]['upload']);
    $this->assertEquals($mockUpload, $json[2]['upload']);
  }

  public function testPerformSearchFallsBackWhenUfileNameMissing()
  {
    $headers = new Headers(['filename' => 'legacy.c']);
    $request = new Request("GET", new Uri("HTTP", "localhost"), $headers,
      [], [], (new StreamFactory())->createStream());

    $dbRows = [
      [
        'upload_fk' => 20,
        'uploadtree_pk' => 201,
        // ufile_name absent or null
        'ufile_name' => null,
      ],
    ];

    $this->searchHelperDao->shouldReceive('GetResults')
      ->once()
      ->andReturn([$dbRows, 1]);

    $mockUpload = ['id' => 20, 'uploadname' => 'legacy.tar.gz'];

    $this->dbHelper->shouldReceive('getUploads')
      ->with(1, 2, 1, 1, 20, null, true)
      ->once()
      ->andReturn([1, [$mockUpload]]);

    // Fallback is called when ufile_name is null
    $this->dbHelper->shouldReceive('getFilenameFromUploadTree')
      ->with(201)
      ->once()
      ->andReturn('resolved_by_db.c');

    $response = $this->searchController->performSearch($request, new ResponseHelper(), []);
    $this->assertEquals(200, $response->getStatusCode());

    $json = $this->getResponseJson($response);
    $this->assertCount(1, $json);
    $this->assertEquals('resolved_by_db.c', $json[0]['filename']);
    $this->assertEquals(201, $json[0]['uploadTreeId']);
  }

  public function testPerformSearchSkipsInaccessibleUploadWithoutRepeatedQueries()
  {
    $headers = new Headers(['filename' => '*.c']);
    $request = new Request("GET", new Uri("HTTP", "localhost"), $headers,
      [], [], (new StreamFactory())->createStream());

    $dbRows = [
      [
        'upload_fk' => 99,
        'uploadtree_pk' => 901,
        'ufile_name' => 'secret1.c',
      ],
      [
        'upload_fk' => 99,
        'uploadtree_pk' => 902,
        'ufile_name' => 'secret2.c',
      ],
    ];

    $this->searchHelperDao->shouldReceive('GetResults')
      ->once()
      ->andReturn([$dbRows, 2]);

    // getUploads returns empty (inaccessible upload) - called only once
    $this->dbHelper->shouldReceive('getUploads')
      ->with(1, 2, 1, 1, 99, null, true)
      ->once()
      ->andReturn([0, []]);

    $response = $this->searchController->performSearch($request, new ResponseHelper(), []);
    $this->assertEquals(200, $response->getStatusCode());

    $json = $this->getResponseJson($response);
    $this->assertCount(0, $json);
  }
}
