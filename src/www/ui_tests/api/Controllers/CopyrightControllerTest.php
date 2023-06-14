<?php
/*
 SPDX-FileCopyrightText: © 2023 Soham Banerjee <sohambanerjee4abc@hotmail.com>
 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @file
 * @brief Tests for CopyrightController
 */

namespace Fossology\UI\Api\Test\Controllers;

use CopyrightHistogramProcessPost;
use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\ClearingDao;
use Fossology\Lib\Dao\LicenseDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Data\DecisionTypes;
use Fossology\Lib\Data\UploadStatus;
use Fossology\Lib\Db\DbManager;
use Fossology\UI\Api\Controllers\CopyrightController;
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
class CopyrightControllerTest extends \PHPUnit\Framework\TestCase
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
   * @var CopyrightController $CopyrightController
   * CopyrightController mock
   */
  private $CopyrightController;

  /**
   * @var UploadDao $uploadDao
   * UploadDao mock
   */
  private $uploadDao;

  /**
   * @var CopyrightHistogramProcessPost $CopyrightHistPlugin
   * CopyrightHistogramProcessPost mock
   */
  private $CopyrightHistPlugin;

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
   * @var AssertCountBefore $assertCountBefore
   * Assert count object
   */
  private $assertCountBefore;

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
    $this->CopyrightHistPlugin = M::mock(CopyrightHistogramProcessPost::class);
    $this->decisionTypes = M::mock(DecisionTypes::class);
    $this->licenseDao = M::mock(LicenseDao::class);

    $container->shouldReceive('get')->withArgs(array(
      'helper.restHelper'
    ))->andReturn($this->restHelper);

    $this->restHelper->shouldReceive('getPlugin')
      ->withArgs(array('ajax-copyright-hist'))->andReturn($this->CopyrightHistPlugin);

    $this->dbManager->shouldReceive('getSingleRow')
      ->withArgs([M::any(), [
        $this->groupId, UploadStatus::OPEN,
        Auth::PERM_READ
      ]]);
    $this->dbHelper->shouldReceive('getDbManager')->andReturn($this->dbManager);

    $this->restHelper->shouldReceive('getDbHelper')->andReturn($this->dbHelper);
    $this->restHelper->shouldReceive('getGroupId')->andReturn($this->groupId);
    $this->restHelper->shouldReceive('getUserId')->andReturn($this->userId);
    $container->shouldReceive('get')->withArgs(['decision.types'])->andReturn($this->decisionTypes);
    $container->shouldReceive('get')->withArgs(['dao.license'])->andReturn($this->licenseDao);
    $container->shouldReceive('get')->withArgs(['dao.clearing'])->andReturn($this->clearingDao);
    $container->shouldReceive('get')->withArgs(['dao.upload'])->andReturn($this->uploadDao);
    $this->CopyrightController = new CopyrightController($container);
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
   * -# Test for CopyrightController::getInactiveFileCopyrights()
   * -# Check if response status is 200 and RES body matches
   */
  public function testGetFileCopyrights()
  {
    $itemId = 68;
    $uploadId = 4;
    $agentId = 6;
    $UploadTreeTableName = 'uploadtree_a';
    $rows = array(
      'content' => "Copyright (C) 2020 Siemens AG Author: Gaurav Mishra <mishra.gaurav@siemens.com>", 'hash' => "16f93a4e03cf6b9218fa03ca5a8ee88e",
      'copyright_count' => "1"
    );

    $res = array($rows, 1, 1);

    $this->dbHelper->shouldReceive('doesIdExist')
      ->withArgs(["upload", "upload_pk", $uploadId])->andReturn(true);

    $this->uploadDao->shouldReceive('getUploadtreeTableName')->withArgs([$uploadId])->andReturn("uploadtree");
    $this->dbHelper->shouldReceive('doesIdExist')
      ->withArgs(["uploadtree", "uploadtree_pk", $itemId])->andReturn(true);
    $this->CopyrightHistPlugin->shouldReceive('getAgentId')->withArgs([$uploadId, 'copyright_ars'])->andReturn($agentId);
    $this->CopyrightHistPlugin->shouldReceive('getCopyrights')->withArgs([$uploadId, $itemId, $UploadTreeTableName, $agentId, 'statement', 'inactive', true])->andReturn($res);
    $expectedResponse = (new ResponseHelper())->withJson($res, 200);
    $actualResponse = $this->CopyrightController->getInactiveFileCopyrights(null, new ResponseHelper(), ['id' => $uploadId, 'itemId' => $itemId]);
    $this->assertEquals($expectedResponse->getStatusCode(), $actualResponse->getStatusCode());
    $this->assertEquals($this->getResponseJson($actualResponse), $this->getResponseJson($expectedResponse));
  }
}
