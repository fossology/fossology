<?php
/*
 SPDX-FileCopyrightText: © 2026 NUAAwqy

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
use Fossology\UI\Api\Helper\DbHelper;
use Fossology\UI\Api\Helper\FileHelper;
use Fossology\UI\Api\Helper\ResponseHelper;
use Fossology\UI\Api\Helper\RestHelper;
use Fossology\UI\Api\Models\Hash;
use Mockery as M;
use Slim\Psr7\Factory\StreamFactory;
use Slim\Psr7\Headers;
use Slim\Psr7\Request;
use Slim\Psr7\Uri;

/**
 * @class FileSearchControllerTest
 * @brief Tests for batched file hash searches.
 */
class FileSearchControllerTest extends \PHPUnit\Framework\TestCase
{
  /**
   * @var FileHelper $fileHelper
   */
  private $fileHelper;

  /**
   * @var RestHelper $restHelper
   */
  private $restHelper;

  /**
   * @var FileSearchController $controller
   */
  private $controller;

  /**
   * @var StreamFactory $streamFactory
   */
  private $streamFactory;

  /**
   * @brief Create the controller with mocked dependencies.
   */
  protected function setUp(): void
  {
    $container = M::mock('ContainerBuilder');
    $dbHelper = M::mock(DbHelper::class);
    $this->fileHelper = M::mock(FileHelper::class);
    $this->restHelper = M::mock(RestHelper::class);
    $clearingDao = M::mock(ClearingDao::class);
    $licenseDao = M::mock(LicenseDao::class);

    $this->restHelper->shouldReceive('getDbHelper')->andReturn($dbHelper);
    $this->restHelper->shouldReceive('getGroupId')->andReturn(1);
    $container->shouldReceive('get')->with('helper.restHelper')
      ->andReturn($this->restHelper);
    $container->shouldReceive('get')->with('helper.fileHelper')
      ->andReturn($this->fileHelper);
    $container->shouldReceive('get')->with('dao.clearing')
      ->andReturn($clearingDao);
    $container->shouldReceive('get')->with('dao.license')
      ->andReturn($licenseDao);

    $this->controller = new FileSearchController($container);
    $this->streamFactory = new StreamFactory();
  }

  /**
   * @brief Close Mockery after each test.
   */
  protected function tearDown(): void
  {
    M::close();
  }

  /**
   * @param array $body Request body.
   * @return ResponseHelper JSON response.
   */
  private function makeRequest($body)
  {
    $stream = $this->streamFactory->createStream(json_encode($body));
    $request = new Request(
      'POST',
      new Uri('http', 'localhost'),
      new Headers(),
      [],
      [],
      $stream
    );
    return $request->withHeader('Content-Type', 'application/json');
  }

  /**
   * @param ResponseHelper $response JSON response.
   * @return array Decoded response body.
   */
  private function responseBody($response)
  {
    $response->getBody()->rewind();
    return json_decode($response->getBody()->getContents(), true);
  }

  /**
   * A batch must retain one result per selector and its input order, even when
   * two different hashes identify the same stored file.
   */
  public function testBatchPreservesCardinalityAndOrderForDuplicatePfile(): void
  {
    $this->fileHelper->shouldReceive('getPfile')
      ->withArgs(function ($hash) {
        return $hash instanceof Hash
          && in_array($hash->getSha1(), ['missing', 'same-sha1'], true)
          || $hash instanceof Hash && $hash->getSha256() === 'same-sha256';
      })
      ->andReturnUsing(function ($hash) {
        if ($hash->getSha1() === 'missing') {
          return null;
        }
        return [
          'pfile_pk' => 42,
          'pfile_sha1' => 'canonical-sha1',
          'pfile_md5' => 'canonical-md5',
          'pfile_sha256' => 'canonical-sha256',
          'pfile_size' => 12,
        ];
      });
    $this->fileHelper->shouldReceive('getPackageUpload')
      ->once()->with(42)->andReturn([]);
    $this->fileHelper->shouldReceive('pfileScannerFindings')
      ->once()->with(42)->andReturn(['MIT']);
    $this->fileHelper->shouldReceive('pfileConclusions')
      ->once()->with(1, 42)->andReturn([]);
    $this->fileHelper->shouldReceive('pfileCopyright')
      ->once()->with(42)->andReturn([]);

    $response = $this->controller->getFiles(
      $this->makeRequest([
        ['sha1' => 'missing'],
        ['sha1' => 'same-sha1'],
        ['sha256' => 'same-sha256'],
        ['unexpectedField' => 'invalid'],
      ]),
      new ResponseHelper(),
      []
    );

    $body = $this->responseBody($response);
    $this->assertCount(4, $body);
    $this->assertSame('Not found', $body[0]['message']);
    $this->assertSame('canonical-sha1', $body[1]['hash']['sha1']);
    $this->assertSame('canonical-sha256', $body[2]['hash']['sha256']);
    $this->assertSame($body[1]['findings'], $body[2]['findings']);
    $this->assertSame('Invalid keys', $body[3]['message']);
  }
}
