<?php
/*
 SPDX-FileCopyrightText: © 2026 Fossology contributors

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\UI\Api\Controllers;

function TryToDelete($uploadpk, $user_pk, $group_pk, $uploadDao)
{
  return \Fossology\UI\Api\Test\Controllers\DeleteUploadControllerTest::$functions
    ->TryToDelete($uploadpk, $user_pk, $group_pk, $uploadDao);
}

namespace Fossology\UI\Api\Test\Controllers;

use Fossology\DelAgent\UI\DeleteMessages;
use Fossology\DelAgent\UI\DeleteResponse;
use Fossology\Lib\Dao\UploadDao;
use Fossology\UI\Api\Controllers\UploadController;
use Fossology\UI\Api\Exceptions\HttpInternalServerErrorException;
use Fossology\UI\Api\Helper\DbHelper;
use Fossology\UI\Api\Helper\ResponseHelper;
use Fossology\UI\Api\Helper\RestHelper;
use Mockery as M;
use Slim\Psr7\Headers;
use Slim\Psr7\Request;
use Slim\Psr7\Uri;

class DeleteUploadControllerTest extends \PHPUnit\Framework\TestCase
{
  /** @var \Mockery\MockInterface */
  public static $functions;

  /** @var \Mockery\MockInterface */
  private $container;

  /** @var \Mockery\MockInterface */
  private $dbHelper;

  /** @var \Mockery\MockInterface */
  private $restHelper;

  /** @var \Mockery\MockInterface */
  private $uploadDao;

  /** @var UploadController */
  private $uploadController;

  /** @var int */
  private $userId = 2;

  /** @var int */
  private $groupId = 2;

  protected function setUp() : void
  {
    $this->container = M::mock('ContainerBuilder');
    $this->dbHelper = M::mock(DbHelper::class);
    $this->restHelper = M::mock(RestHelper::class);
    $this->uploadDao = M::mock(UploadDao::class);
    self::$functions = M::mock();

    $this->restHelper->shouldReceive('getDbHelper')->andReturn($this->dbHelper);
    $this->restHelper->shouldReceive('getUploadDao')->andReturn($this->uploadDao);
    $this->restHelper->shouldReceive('getUserId')->andReturn($this->userId);
    $this->restHelper->shouldReceive('getGroupId')->andReturn($this->groupId);

    $this->container->shouldReceive('get')->withArgs(['helper.restHelper'])
      ->andReturn($this->restHelper);

    $this->uploadController = new UploadController($this->container);
  }

  protected function tearDown() : void
  {
    M::close();
  }

  /** @test */
  public function testDeleteUploadReturnsAcceptedWhenEditable()
  {
    $uploadId = 11;
    $request = new Request("DELETE", new Uri("HTTP", "localhost"),
      new Headers(), [], [], fopen('php://temp', 'r+'));

    $this->dbHelper->shouldReceive('doesIdExist')
      ->withArgs(["upload", "upload_pk", $uploadId])->andReturn(true);
    $this->uploadDao->shouldReceive('isAccessible')
      ->withArgs([$uploadId, $this->groupId])->andReturn(true);

    self::$functions->shouldReceive('TryToDelete')
      ->withArgs([$uploadId, $this->userId, $this->groupId, $this->uploadDao])
      ->andReturn(new DeleteResponse(DeleteMessages::SUCCESS));

    $response = $this->uploadController->deleteUpload($request,
      new ResponseHelper(), ['id' => $uploadId]);
    $this->assertEquals(202, $response->getStatusCode());
  }

  /** @test */
  public function testDeleteUploadThrowsWhenTryToDeleteFailsPermission()
  {
    $uploadId = 12;
    $request = new Request("DELETE", new Uri("HTTP", "localhost"),
      new Headers(), [], [], fopen('php://temp', 'r+'));

    $this->dbHelper->shouldReceive('doesIdExist')
      ->withArgs(["upload", "upload_pk", $uploadId])->andReturn(true);
    $this->uploadDao->shouldReceive('isAccessible')
      ->withArgs([$uploadId, $this->groupId])->andReturn(true);

    self::$functions->shouldReceive('TryToDelete')
      ->withArgs([$uploadId, $this->userId, $this->groupId, $this->uploadDao])
      ->andReturn(new DeleteResponse(DeleteMessages::NO_PERMISSION));

    $this->expectException(HttpInternalServerErrorException::class);

    $this->uploadController->deleteUpload($request, new ResponseHelper(),
      ['id' => $uploadId]);
  }
}
