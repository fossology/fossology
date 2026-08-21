<?php
/*
 SPDX-FileCopyrightText: © 2026 FOSSology contributors

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @file
 * @brief Tests for BucketPoolController
 */

namespace Fossology\UI\Api\Test\Controllers;

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Db\DbManager;
use Fossology\UI\Api\Controllers\BucketPoolController;
use Fossology\UI\Api\Exceptions\HttpConflictException;
use Fossology\UI\Api\Exceptions\HttpForbiddenException;
use Fossology\UI\Api\Exceptions\HttpNotFoundException;
use Fossology\UI\Api\Helper\DbHelper;
use Fossology\UI\Api\Helper\ResponseHelper;
use Fossology\UI\Api\Helper\RestHelper;
use Mockery as M;
use Slim\Psr7\Factory\StreamFactory;
use Slim\Psr7\Headers;
use Slim\Psr7\Request;
use Slim\Psr7\Uri;

/**
 * @class BucketPoolControllerTest
 * @brief Tests for BucketPoolController
 */
class BucketPoolControllerTest extends \PHPUnit\Framework\TestCase
{
  /**
   * @var integer $assertCountBefore
   * Assertions before running tests
   */
  private $assertCountBefore;

  /**
   * @var DbHelper $dbHelper
   * DbHelper mock
   */
  private $dbHelper;

  /**
   * @var DbManager $dbManager
   * DbManager mock
   */
  private $dbManager;

  /**
   * @var RestHelper $restHelper
   * RestHelper mock
   */
  private $restHelper;

  /**
   * @var BucketPoolController $bucketPoolController
   * BucketPoolController object to test
   */
  private $bucketPoolController;

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
    $this->dbManager = M::mock(DbManager::class);
    $this->restHelper = M::mock(RestHelper::class);

    $this->restHelper->shouldReceive('getDbHelper')->andReturn($this->dbHelper);
    $this->dbHelper->shouldReceive('getDbManager')->andReturn($this->dbManager);

    $container->shouldReceive('get')->withArgs(array(
      'helper.restHelper'))->andReturn($this->restHelper);
    $this->bucketPoolController = new BucketPoolController($container);
    $this->assertCountBefore = \Hamcrest\MatcherAssert::getCount();
    $this->streamFactory = new StreamFactory();
    $_SESSION[Auth::USER_LEVEL] = Auth::PERM_ADMIN;
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
   * -# Test BucketPoolController::getBucketpools() when user is not admin
   * -# Check if HttpForbiddenException is thrown
   */
  public function testGetBucketpoolsNotAdmin()
  {
    $_SESSION[Auth::USER_LEVEL] = Auth::PERM_WRITE;
    $this->expectException(HttpForbiddenException::class);
    $this->bucketPoolController->getBucketpools(null, new ResponseHelper(), []);
  }

  /**
   * @test
   * -# Test BucketPoolController::getBucketpools() for a valid admin request
   * -# Check if the response is the list of active bucket pools
   */
  public function testGetBucketpoolsSuccess()
  {
    $rows = [
      ["bucketpool_pk" => 1, "bucketpool_name" => "GPL Demo bucket pool",
        "version" => 2, "active" => "Y", "description" => "Demo pool"],
      ["bucketpool_pk" => 2, "bucketpool_name" => "GPL Demo bucket pool",
        "version" => 1, "active" => "Y", "description" => "Demo pool"]
    ];
    $this->dbManager->shouldReceive('getRows')
      ->withArgs([M::any(), [], M::any()])->andReturn($rows);

    $expected = [
      ["id" => 1, "name" => "GPL Demo bucket pool", "version" => 2,
        "active" => true, "description" => "Demo pool"],
      ["id" => 2, "name" => "GPL Demo bucket pool", "version" => 1,
        "active" => true, "description" => "Demo pool"]
    ];
    $expectedResponse = (new ResponseHelper())->withJson($expected, 200);

    $actualResponse = $this->bucketPoolController->getBucketpools(null,
      new ResponseHelper(), []);

    $this->assertEquals($expectedResponse->getStatusCode(),
      $actualResponse->getStatusCode());
    $this->assertEquals($this->getResponseJson($expectedResponse),
      $this->getResponseJson($actualResponse));
  }

  /**
   * @test
   * -# Test BucketPoolController::duplicateBucketpool() when user is not admin
   * -# Check if HttpForbiddenException is thrown
   */
  public function testDuplicateBucketpoolNotAdmin()
  {
    $_SESSION[Auth::USER_LEVEL] = Auth::PERM_WRITE;
    $this->expectException(HttpForbiddenException::class);
    $this->bucketPoolController->duplicateBucketpool(null, new ResponseHelper(),
      ['id' => 1]);
  }

  /**
   * @test
   * -# Test BucketPoolController::duplicateBucketpool() for a bucket pool
   *    that does not exist
   * -# Check if HttpNotFoundException is thrown
   */
  public function testDuplicateBucketpoolNotFound()
  {
    $this->dbHelper->shouldReceive('doesIdExist')
      ->withArgs(["bucketpool", "bucketpool_pk", 99])->andReturn(false);

    $this->expectException(HttpNotFoundException::class);
    $this->bucketPoolController->duplicateBucketpool(null, new ResponseHelper(),
      ['id' => 99]);
  }

  /**
   * @test
   * -# Test BucketPoolController::duplicateBucketpool() when the insert
   *    hits the (bucketpool_name, version) unique constraint, e.g. due to
   *    a concurrent duplication of the same bucket pool
   * -# Check if HttpConflictException is thrown
   */
  public function testDuplicateBucketpoolConflict()
  {
    $bucketpoolId = 1;
    $oldPool = ["bucketpool_name" => "GPL Demo bucket pool", "active" => "Y",
      "description" => "Demo pool"];

    $this->dbHelper->shouldReceive('doesIdExist')
      ->withArgs(["bucketpool", "bucketpool_pk", $bucketpoolId])->andReturn(true);
    $this->dbManager->shouldReceive('getSingleRow')
      ->withArgs(["SELECT bucketpool_name, active, description FROM bucketpool " .
        "WHERE bucketpool_pk = $1", [$bucketpoolId], M::any()])
      ->andReturn($oldPool);
    $this->dbManager->shouldReceive('getSingleRow')
      ->withArgs(["SELECT max(version) AS version FROM bucketpool WHERE bucketpool_name = $1",
        [$oldPool['bucketpool_name']], M::any()])
      ->andReturn(["version" => 2]);
    $this->dbManager->shouldReceive('insertTableRow')
      ->withArgs(["bucketpool", [
        "bucketpool_name" => $oldPool['bucketpool_name'],
        "version" => 3,
        "active" => $oldPool['active'],
        "description" => $oldPool['description']
      ], M::any(), "bucketpool_pk"])
      ->andThrow(new \Exception("duplicate key value violates unique constraint"));

    $request = new Request("POST", new Uri("HTTP", "localhost"),
      new Headers(), [], [], $this->streamFactory->createStream());

    $this->expectException(HttpConflictException::class);
    $this->bucketPoolController->duplicateBucketpool($request, new ResponseHelper(),
      ['id' => $bucketpoolId]);
  }

  /**
   * @test
   * -# Test BucketPoolController::duplicateBucketpool() for a valid request
   *    without updating the default bucket pool
   * -# Check if the response contains the new bucket pool id and version
   */
  public function testDuplicateBucketpoolSuccess()
  {
    $bucketpoolId = 1;
    $newBucketpoolId = 5;
    $oldPool = ["bucketpool_name" => "GPL Demo bucket pool", "active" => "Y",
      "description" => "Demo pool"];

    $this->dbHelper->shouldReceive('doesIdExist')
      ->withArgs(["bucketpool", "bucketpool_pk", $bucketpoolId])->andReturn(true);
    $this->dbManager->shouldReceive('getSingleRow')
      ->withArgs(["SELECT bucketpool_name, active, description FROM bucketpool " .
        "WHERE bucketpool_pk = $1", [$bucketpoolId], M::any()])
      ->andReturn($oldPool);
    $this->dbManager->shouldReceive('getSingleRow')
      ->withArgs(["SELECT max(version) AS version FROM bucketpool WHERE bucketpool_name = $1",
        [$oldPool['bucketpool_name']], M::any()])
      ->andReturn(["version" => 2]);
    $this->dbManager->shouldReceive('insertTableRow')
      ->withArgs(["bucketpool", [
        "bucketpool_name" => $oldPool['bucketpool_name'],
        "version" => 3,
        "active" => $oldPool['active'],
        "description" => $oldPool['description']
      ], M::any(), "bucketpool_pk"])->andReturn($newBucketpoolId);
    $this->dbManager->shouldReceive('getSingleRow')
      ->withArgs([M::pattern('/^INSERT INTO bucket_def/'),
        [$newBucketpoolId, $bucketpoolId], M::any()])->andReturn(null);

    $request = new Request("POST", new Uri("HTTP", "localhost"),
      new Headers(), [], [], $this->streamFactory->createStream());

    $expectedResponse = (new ResponseHelper())->withJson(
      ["id" => $newBucketpoolId, "version" => 3], 201);

    $actualResponse = $this->bucketPoolController->duplicateBucketpool($request,
      new ResponseHelper(), ['id' => $bucketpoolId]);

    $this->assertEquals($expectedResponse->getStatusCode(),
      $actualResponse->getStatusCode());
    $this->assertEquals($this->getResponseJson($expectedResponse),
      $this->getResponseJson($actualResponse));
  }

  /**
   * @test
   * -# Test BucketPoolController::duplicateBucketpool() for a valid request
   *    that also updates the requesting user's default bucket pool
   * -# Check if the users table is updated with the new bucket pool id
   */
  public function testDuplicateBucketpoolSuccessWithUpdateDefault()
  {
    $bucketpoolId = 1;
    $newBucketpoolId = 5;
    $userId = 7;
    $oldPool = ["bucketpool_name" => "GPL Demo bucket pool", "active" => "Y",
      "description" => "Demo pool"];

    $this->restHelper->shouldReceive('getUserId')->andReturn($userId);
    $this->dbHelper->shouldReceive('doesIdExist')
      ->withArgs(["bucketpool", "bucketpool_pk", $bucketpoolId])->andReturn(true);
    $this->dbManager->shouldReceive('getSingleRow')
      ->withArgs(["SELECT bucketpool_name, active, description FROM bucketpool " .
        "WHERE bucketpool_pk = $1", [$bucketpoolId], M::any()])
      ->andReturn($oldPool);
    $this->dbManager->shouldReceive('getSingleRow')
      ->withArgs(["SELECT max(version) AS version FROM bucketpool WHERE bucketpool_name = $1",
        [$oldPool['bucketpool_name']], M::any()])
      ->andReturn(["version" => 2]);
    $this->dbManager->shouldReceive('insertTableRow')
      ->withArgs(["bucketpool", [
        "bucketpool_name" => $oldPool['bucketpool_name'],
        "version" => 3,
        "active" => $oldPool['active'],
        "description" => $oldPool['description']
      ], M::any(), "bucketpool_pk"])->andReturn($newBucketpoolId);
    $this->dbManager->shouldReceive('getSingleRow')
      ->withArgs([M::pattern('/^INSERT INTO bucket_def/'),
        [$newBucketpoolId, $bucketpoolId], M::any()])->andReturn(null);
    $this->dbManager->shouldReceive('getSingleRow')
      ->withArgs(["UPDATE users SET default_bucketpool_fk = $1 WHERE user_pk = $2",
        [$newBucketpoolId, $userId], M::any()])->andReturn(null);

    $body = $this->streamFactory->createStream(json_encode(["updateDefault" => true]));
    $requestHeaders = new Headers();
    $requestHeaders->setHeader('Content-Type', 'application/json');
    $request = new Request("POST", new Uri("HTTP", "localhost"),
      $requestHeaders, [], [], $body);

    $expectedResponse = (new ResponseHelper())->withJson(
      ["id" => $newBucketpoolId, "version" => 3], 201);

    $actualResponse = $this->bucketPoolController->duplicateBucketpool($request,
      new ResponseHelper(), ['id' => $bucketpoolId]);

    $this->assertEquals($expectedResponse->getStatusCode(),
      $actualResponse->getStatusCode());
    $this->assertEquals($this->getResponseJson($expectedResponse),
      $this->getResponseJson($actualResponse));
  }
}
