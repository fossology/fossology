<?php
/*
 SPDX-FileCopyrightText: © 2023 Samuel Dushimimana <dushsam100@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @file
 * @brief Tests for UploadTreeController
 */

namespace Fossology\UI\Api\Test\Controllers;

use AjaxClearingView;
use ClearingView;
use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\ClearingDao;
use Fossology\Lib\Dao\LicenseDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Data\DecisionTypes;
use Fossology\Lib\Data\UploadStatus;
use Fossology\Lib\Db\DbManager;
use Fossology\UI\Api\Controllers\UploadTreeController;
use Fossology\UI\Api\Helper\DbHelper;
use Fossology\UI\Api\Helper\ResponseHelper;
use Fossology\UI\Api\Helper\RestHelper;
use Fossology\UI\Api\Models\Info;
use Fossology\UI\Api\Models\InfoType;
use Fossology\UI\Api\Models\License;
use Slim\Psr7\Factory\StreamFactory;
use Mockery as M;
use Slim\Psr7\Headers;
use Slim\Psr7\Request;
use Slim\Psr7\Uri;

/**
 * @class UploadControllerTest
 * @brief Unit tests for UploadController
 */
class UploadTreeControllerTest extends \PHPUnit\Framework\TestCase
{
  /**
   * @var DbHelper $dbHelper
   * DbHelper mock
   */
  private $dbHelper;

  /**
   * @var DbManager $dbManager
   * Dbmanager mock
   */
  private $dbManager;

  /**
   * @var RestHelper $restHelper
   * RestHelper mock
   */
  private $restHelper;

  /**
   * @var UploadTreeController $uploadTreeController
   * UploadTreeController mock
   */
  private $uploadTreeController;

  /**
   * @var UploadDao $uploadDao
   * UploadDao mock
   */
  private $uploadDao;

  /**
   * @var AjaxClearingView $clearingViewPlugin
   * AjaxClearingView mock
   */
  private $clearingViewPlugin;

  /**
   * @var LicenseDao $licenseDao
   * LicenseDao mock
   */
  private $licenseDao;

  /**
   * @var ClearingDao $clearingDao
   * ClearingDao mock
   */
  private $clearingDao;

  /**
   * @var StreamFactory $streamFactory
   * Stream factory to create body streams.
   */
  private $streamFactory;


  /**
   * @var DecisionTypes $decisionTypes
   * Decision types object
   */
  private $decisionTypes;

  /**
   * @brief Setup test objects
   * @see PHPUnit_Framework_TestCase::setUp()
   */
  protected function setUp(): void
  {
    global $container;
    $this->userId = 2;
    $this->groupId = 2;
    $container = M::mock('ContainerBuilder');
    $this->dbHelper = M::mock(DbHelper::class);
    $this->dbManager = M::mock(DbManager::class);
    $this->restHelper = M::mock(RestHelper::class);
    $this->uploadDao = M::mock(UploadDao::class);
    $this->clearingDao = M::mock(ClearingDao::class);
    $this->clearingViewPlugin = M::mock(AjaxClearingView::class);
    $this->decisionTypes = M::mock(DecisionTypes::class);
    $this->licenseDao = M::mock(LicenseDao::class);

    $container->shouldReceive('get')->withArgs(array(
      'helper.restHelper'))->andReturn($this->restHelper);

    $this->restHelper->shouldReceive('getPlugin')
      ->withArgs(array('conclude-license'))->andReturn($this->clearingViewPlugin);

    $this->dbManager->shouldReceive('getSingleRow')
      ->withArgs([M::any(), [$this->groupId, UploadStatus::OPEN,
        Auth::PERM_READ]]);
    $this->dbHelper->shouldReceive('getDbManager')->andReturn($this->dbManager);

    $this->restHelper->shouldReceive('getDbHelper')->andReturn($this->dbHelper);
    $this->restHelper->shouldReceive('getGroupId')->andReturn($this->groupId);
    $this->restHelper->shouldReceive('getUserId')->andReturn($this->userId);
    $this->restHelper->shouldReceive('getUploadDao')
      ->andReturn($this->uploadDao);
    $container->shouldReceive('get')->withArgs(['decision.types'])->andReturn($this->decisionTypes);
    $container->shouldReceive('get')->withArgs(['dao.license'])->andReturn($this->licenseDao);
    $container->shouldReceive('get')->withArgs(['dao.clearing'])->andReturn($this->clearingDao);
    $this->uploadTreeController = new UploadTreeController($container);
    $this->assertCountBefore = \Hamcrest\MatcherAssert::getCount();
    $this->streamFactory = new StreamFactory();
  }

  /**
   * Helper function to get JSON array from response
   *
   * @param Response $response
   * @return array Decoded response
   */
  private function getResponseJson($response)
  {
    $response->getBody()->seek(0);
    return json_decode($response->getBody()->getContents(), true);
  }


  /**
   * @test
   * -# Test for UploadTreeController::updateClearingInfo()
   * -# Check if response status is 200 and RES body matches
   */
  public function testUpdateClearingInfo()
  {
    $itemId = 200;
    $uploadId = 1;
    $licenseId = 123;
    $column = "TEXT";
    $text = "text";

    $aaData = array(['DT_RowId' => "$itemId,$licenseId", 'DT_RowClass' => 'removed']);

    $res = array(
      'aaData' => $aaData,
    );

    $rq = [
      "text" => $text,
      "column" => $column,
    ];

    $this->dbHelper->shouldReceive('doesIdExist')
      ->withArgs(["upload", "upload_pk", $uploadId])->andReturn(true);

    $this->uploadDao->shouldReceive('getUploadtreeTableName')->withArgs([$uploadId])->andReturn("uploadtree");
    $this->dbHelper->shouldReceive('doesIdExist')
      ->withArgs(["uploadtree", "uploadtree_pk", $itemId])->andReturn(true);
    $this->dbHelper->shouldReceive('doesIdExist')
      ->withArgs(["license_ref", "rf_pk", $licenseId])->andReturn(true);

    $this->clearingViewPlugin->shouldReceive('doClearings')->withArgs([true, $this->groupId, $uploadId, $itemId])->andReturn($res);
    $this->clearingDao->shouldReceive('updateClearingEvent')->withArgs([$itemId, $this->userId, $this->groupId, $licenseId, "reportinfo", $text])->andReturn(null);

    $info = new Info(200, "Successfully updated License text.", InfoType::INFO);
    $expectedResponse = (new ResponseHelper())->withJson($info->getArray(), $info->getCode());
    $reqBody = $this->streamFactory->createStream(json_encode(
      $rq
    ));
    $requestHeaders = new Headers();
    $requestHeaders->setHeader('Content-Type', 'application/json');
    $request = new Request("PUT", new Uri("HTTP", "localhost"),
      $requestHeaders, [], [], $reqBody);
    $actualResponse = $this->uploadTreeController->updateClearingInfo($request, new ResponseHelper(), ['id' => $uploadId, 'itemId' => $itemId, 'licenseId' => $licenseId]);

    $this->assertEquals($expectedResponse->getStatusCode(),
      $actualResponse->getStatusCode());
    $this->assertEquals($this->getResponseJson($expectedResponse),
      $this->getResponseJson($actualResponse));
  }

  /**
   * @test
   * -# Test for UploadTreeController::updateClearingInfo()
   * -# Check if response status is 400 when Column Id is invalid
   */
  public function testUpdateClearingInfo_BadRequest()
  {
    $itemId = 200;
    $uploadId = 1;
    $licenseId = 123;
    $column = "ANY";
    $text = "text";

    $aaData = array(['DT_RowId' => "$itemId,$licenseId", 'DT_RowClass' => 'removed']);

    $res = array(
      'aaData' => $aaData,
    );

    $rq = [
      "text" => $text,
      "column" => $column,
    ];

    $this->dbHelper->shouldReceive('doesIdExist')
      ->withArgs(["upload", "upload_pk", $uploadId])->andReturn(true);

    $this->uploadDao->shouldReceive('getUploadtreeTableName')->withArgs([$uploadId])->andReturn("uploadtree");
    $this->dbHelper->shouldReceive('doesIdExist')
      ->withArgs(["uploadtree", "uploadtree_pk", $itemId])->andReturn(true);
    $this->dbHelper->shouldReceive('doesIdExist')
      ->withArgs(["license_ref", "rf_pk", $licenseId])->andReturn(true);

    $this->clearingViewPlugin->shouldReceive('doClearings')->withArgs([true, $this->groupId, $uploadId, $itemId])->andReturn($res);
    $this->clearingDao->shouldReceive('updateClearingEvent')->withArgs([$itemId, $this->userId, $this->groupId, $licenseId, "reportinfo", $text])->andReturn(null);

    $info = new Info(400, "Invalid columnKey. Allowed values are 'TEXT', 'ACK', 'COMMENT'", InfoType::ERROR);
    $expectedResponse = (new ResponseHelper())->withJson($info->getArray(), $info->getCode());
    $reqBody = $this->streamFactory->createStream(json_encode(
      $rq
    ));
    $requestHeaders = new Headers();
    $requestHeaders->setHeader('Content-Type', 'application/json');
    $request = new Request("PUT", new Uri("HTTP", "localhost"),
      $requestHeaders, [], [], $reqBody);
    $actualResponse = $this->uploadTreeController->updateClearingInfo($request, new ResponseHelper(), ['id' => $uploadId, 'itemId' => $itemId, 'licenseId' => $licenseId]);

    $this->assertEquals($expectedResponse->getStatusCode(),
      $actualResponse->getStatusCode());
    $this->assertEquals($this->getResponseJson($expectedResponse),
      $this->getResponseJson($actualResponse));
  }
}
