<?php
/*
 SPDX-FileCopyrightText: © 2026 Soumojit Bagchi

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\UI\Api\Test\Controllers;

use Fossology\Lib\Dao\SearchHelperDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\UI\Api\Controllers\SearchController;
use Fossology\UI\Api\Exceptions\HttpBadRequestException;
use Fossology\UI\Api\Helper\DbHelper;
use Fossology\UI\Api\Helper\ResponseHelper;
use Fossology\UI\Api\Helper\RestHelper;
use Fossology\UI\Api\Models\ApiVersion;
use Mockery as M;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\StreamFactory;
use Slim\Psr7\Headers;
use Slim\Psr7\Request;
use Slim\Psr7\Uri;

require_once dirname(__DIR__, 4) . '/lib/php/common-parm.php';

class SearchControllerTest extends TestCase
{
  private $searchController;
  private $searchHelperDao;
  private $restHelper;
  private $requestGlobals;

  protected function setUp(): void
  {
    foreach (['_GET', '_POST', '_SERVER', '_SESSION', '_COOKIE'] as $name) {
      $this->requestGlobals[$name] = $GLOBALS[$name] ?? null;
      $GLOBALS[$name] = [];
    }

    $container = M::mock('ContainerBuilder');
    $this->restHelper = M::mock(RestHelper::class);
    $this->searchHelperDao = M::mock(SearchHelperDao::class);
    $this->restHelper->shouldReceive('getDbHelper')
      ->andReturn(M::mock(DbHelper::class));
    $container->shouldReceive('get')->with('helper.restHelper')
      ->andReturn($this->restHelper);
    $container->shouldReceive('get')->with('dao.searchhelperdao')
      ->andReturn($this->searchHelperDao);
    $this->searchController = new SearchController($container);
  }

  protected function tearDown(): void
  {
    foreach ($this->requestGlobals as $name => $value) {
      if ($value === null) {
        unset($GLOBALS[$name]);
      } else {
        $GLOBALS[$name] = $value;
      }
    }
    M::close();
  }

  private function createRequest($version, array $pagination): Request
  {
    $request = new Request('GET', new Uri('http', 'localhost', null, '/search'),
      new Headers(), [], [], (new StreamFactory())->createStream());
    $request = $request->withAttribute(ApiVersion::ATTRIBUTE_NAME, $version);
    $parameters = array_merge(['filename' => 'test'], $pagination);
    if ($version === ApiVersion::V2) {
      return $request->withQueryParams($parameters);
    }
    foreach ($parameters as $name => $value) {
      $request = $request->withHeader($name, $value);
    }
    return $request;
  }

  /** @dataProvider invalidPaginationProvider */
  public function testInvalidPagination($version, $field, $value): void
  {
    $this->searchHelperDao->shouldNotReceive('GetResults');
    $this->expectException(HttpBadRequestException::class);
    $this->expectExceptionCode(400);
    $this->expectExceptionMessage('page and limit need to be positive integers!');

    $this->searchController->performSearch(
      $this->createRequest($version, [$field => $value]),
      new ResponseHelper(), []);
  }

  public static function invalidPaginationProvider(): array
  {
    $cases = [];
    foreach ([ApiVersion::V1, ApiVersion::V2] as $version) {
      foreach (['page', 'limit'] as $field) {
        foreach (['1.5', '2.5', '0.5', '100.5', 'abc', '0', '-1',
          '1.0', '1e2', '01', '999999999999999999999999'] as $value) {
          $cases["v$version $field=$value"] = [$version, $field, $value];
        }
      }
    }
    return $cases;
  }

  /** @dataProvider validPaginationProvider */
  public function testValidPagination($version, array $pagination, $pageIndex,
    $limit, $count, $totalPages): void
  {
    $uploadDao = M::mock(UploadDao::class);
    $this->restHelper->shouldReceive('getUploadDao')->andReturn($uploadDao);
    $this->restHelper->shouldReceive('getGroupId')->andReturn(2);
    $this->searchHelperDao->shouldReceive('GetResults')->once()
      ->withArgs(function ($item, $filename, $uploadId, $tag, $actualPage,
        $actualLimit, $sizeMin, $sizeMax, $searchType, $license, $copyright,
        $actualUploadDao, $groupId) use ($pageIndex, $limit, $uploadDao) {
        return $item === null && $filename === 'test' && $uploadId === 0 &&
          $tag === '' && $actualPage === $pageIndex && $actualLimit === $limit &&
          $sizeMin === '' && $sizeMax === '' && $searchType === 'allfiles' &&
          $license === '' && $copyright === '' &&
          $actualUploadDao === $uploadDao && $groupId === 2;
      })->andReturn([[], $count]);

    $response = $this->searchController->performSearch(
      $this->createRequest($version, $pagination), new ResponseHelper(), []);

    $this->assertSame(200, $response->getStatusCode());
    $this->assertSame((string) $totalPages,
      $response->getHeaderLine('X-Total-Pages'));
    $response->getBody()->rewind();
    $this->assertSame([], json_decode($response->getBody()->getContents(), true));
  }

  public static function validPaginationProvider(): array
  {
    $paginationCases = [
      'positive integers' => [['page' => '1', 'limit' => '10'], 0, 10, 21, 3],
      'second page' => [['page' => '2', 'limit' => '10'], 1, 10, 21, 3],
      'minimum limit' => [['page' => '1', 'limit' => '1'], 0, 1, 2, 2],
      'defaults' => [[], 0, 50, 101, 3],
      'default limit' => [['page' => '2'], 1, 50, 101, 3],
      'default page' => [['limit' => '10'], 0, 10, 21, 3],
      'empty values' => [['page' => '', 'limit' => ''], 0, 50, 101, 3],
      'empty page' => [['page' => '', 'limit' => '10'], 0, 10, 21, 3],
      'empty limit' => [['page' => '2', 'limit' => ''], 1, 50, 101, 3],
      'maximum limit' => [['page' => '1', 'limit' => '100'], 0, 100, 101, 2],
      'capped limit' => [['page' => '2', 'limit' => '101'], 1, 100, 101, 2],
      'signed integers' => [['page' => '+2', 'limit' => '+10'], 1, 10, 21, 3],
      'padded integers' => [['page' => ' 2 ', 'limit' => ' 10 '], 1, 10, 21, 3],
      'no results' => [['page' => '1', 'limit' => '10'], 0, 10, 0, 0]
    ];
    $cases = [];
    foreach ([ApiVersion::V1, ApiVersion::V2] as $version) {
      foreach ($paginationCases as $name => $case) {
        $cases["v$version $name"] = array_merge([$version], $case);
      }
    }
    return $cases;
  }
}
