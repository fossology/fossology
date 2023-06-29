<?php
/*
 SPDX-FileCopyrightText: © 2023 Samuel Dushimimana <dushsam100@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @file
 * @brief Tests for UploadTreeController
 */

namespace {
  if (!function_exists('RepPathItem')) {
    function RepPathItem($Item, $Repo = "files")
    {
      return dirname(__DIR__) . "/tests.xml";
    }
  }
}

namespace Fossology\UI\Api\Test\Controllers {

  use Fossology\Lib\Auth\Auth;
  use Fossology\Lib\Dao\UploadDao;
  use Fossology\Lib\Data\UploadStatus;
  use Fossology\Lib\Db\DbManager;
  use Fossology\UI\Api\Controllers\UploadTreeController;
  use Fossology\UI\Api\Helper\DbHelper;
  use Fossology\UI\Api\Helper\ResponseHelper;
  use Fossology\UI\Api\Helper\RestHelper;
  use Mockery as M;
  use Slim\Psr7\Factory\StreamFactory;

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
     * @var StreamFactory $streamFactory
     * Stream factory to create body streams.
     */
    private $streamFactory;

    /**
     * @var M\MockInterface $viewFilePlugin
     * ViewFilePlugin mock
     */
    private $viewFilePlugin;

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

      $this->viewFilePlugin = M::mock('ui_view');

      $this->restHelper->shouldReceive('getPlugin')
        ->withArgs(array('view'))->andReturn($this->viewFilePlugin);

      $this->dbManager->shouldReceive('getSingleRow')
        ->withArgs([M::any(), [$this->groupId, UploadStatus::OPEN,
          Auth::PERM_READ]]);
      $this->dbHelper->shouldReceive('getDbManager')->andReturn($this->dbManager);

      $this->restHelper->shouldReceive('getDbHelper')->andReturn($this->dbHelper);
      $this->restHelper->shouldReceive('getGroupId')->andReturn($this->groupId);
      $this->restHelper->shouldReceive('getUserId')->andReturn($this->userId);
      $this->restHelper->shouldReceive('getUploadDao')
        ->andReturn($this->uploadDao);
      $container->shouldReceive('get')->withArgs(array(
        'helper.restHelper'))->andReturn($this->restHelper);

      $this->uploadTreeController = new UploadTreeController($container);
      $this->assertCountBefore = \Hamcrest\MatcherAssert::getCount();
      $this->streamFactory = new StreamFactory();
    }

    /**
     * @test
     * -# Test for UploadController::viewLicenseFile() with valid status
     * -# Check if response status is 200 and the body has the expected contents
     */
    public function testViewLicenseFile()
    {
      $upload_pk = 1;
      $item_pk = 200;
      $expectedContent = file_get_contents(dirname(__DIR__) . "/tests.xml");

      $this->dbHelper->shouldReceive('doesIdExist')
        ->withArgs(["upload", "upload_pk", $upload_pk])->andReturn(true);
      $this->uploadDao->shouldReceive("getUploadtreeTableName")->withArgs([$upload_pk])->andReturn("uploadtree");
      $this->dbHelper->shouldReceive('doesIdExist')
        ->withArgs(["uploadtree", "uploadtree_pk", $item_pk])->andReturn(true);

      $this->viewFilePlugin->shouldReceive('getText')->withArgs([M::any(), 0, 0, -1, null, false, true])->andReturn($expectedContent);

      $expectedResponse = new ResponseHelper();
      $expectedResponse->getBody()->write($expectedContent);
      $actualResponse = $this->uploadTreeController->viewLicenseFile(null, new ResponseHelper(), ['id' => $upload_pk, 'itemId' => $item_pk]);
      $this->assertEquals($expectedResponse->getStatusCode(), $actualResponse->getStatusCode());
      $this->assertEquals($expectedResponse->getBody()->getContents(), $actualResponse->getBody()->getContents());
    }
  }
}
