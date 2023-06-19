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

  use ClearingView;
  use Fossology\Lib\Auth\Auth;
  use Fossology\Lib\Dao\ClearingDao;
  use Fossology\Lib\Dao\UploadDao;
  use Fossology\Lib\Data\ClearingDecision;
  use Fossology\Lib\Data\DecisionScopes;
  use Fossology\Lib\Data\DecisionTypes;
  use Fossology\Lib\Data\Tree\Item;
  use Fossology\Lib\Data\Tree\ItemTreeBounds;
  use Fossology\Lib\Data\Highlight;
  use Fossology\Lib\Data\UploadStatus;
  use Fossology\Lib\Db\DbManager;
  use Fossology\UI\Api\Controllers\UploadTreeController;
  use Fossology\UI\Api\Helper\DbHelper;
  use Fossology\UI\Api\Helper\ResponseHelper;
  use Fossology\UI\Api\Helper\RestHelper;
  use Fossology\UI\Api\Models\BulkHistory;
  use Fossology\UI\Api\Models\ClearingHistory;
  use Fossology\UI\Api\Models\Info;
  use Fossology\UI\Api\Models\InfoType;
  use Mockery as M;
  use Psr\Http\Message\ServerRequestInterface;
  use Slim\Psr7\Factory\StreamFactory;
  use Slim\Psr7\Headers;
  use Slim\Psr7\Request;
  use Slim\Psr7\Response;
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
     * @var DecisionScopes $decisionScopes
     * Decision types object
     */
    private $decisionScopes;

    /**
     * @var M\MockInterface $viewFilePlugin
     * ViewFilePlugin mock
     */
    private $viewFilePlugin;

    /**
     * @var M\MockInterface $viewLicensePlugin
     * ViewFilePlugin mock
     */
    private $viewLicensePlugin;

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
      $this->decisionTypes = M::mock(DecisionTypes::class);
      $this->viewFilePlugin = M::mock('ui_view');
      $this->viewLicensePlugin = M::mock(ClearingView::class);
      $this->clearingDao = M::mock(ClearingDao::class);
      $this->decisionScopes = M::mock(DecisionScopes::class);

      $this->restHelper->shouldReceive('getPlugin')
        ->withArgs(array('view'))->andReturn($this->viewFilePlugin);
      $this->restHelper->shouldReceive('getPlugin')
        ->withArgs(array('view-license'))->andReturn($this->viewLicensePlugin);

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
      $container->shouldReceive('get')->withArgs(['decision.types'])->andReturn($this->decisionTypes);
      $container->shouldReceive('get')->withArgs(['dao.clearing'])->andReturn($this->clearingDao);

      $container->shouldReceive('get')->withArgs(['decision.types'])->andReturn($this->decisionTypes);
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


    /**
     * @test
     * -# Test for UploadTreeController::setClearingDecision() for setting a clearing decision
     * -# Check if response status is 200 and response body matches
     */
    public function testSetClearingDecisionReturnsOk()
    {
      $upload_pk = 1;
      $item_pk = 200;
      $rq = [
        "decisionType" => 3,
        "globalDecision" => false,
      ];
      $dummyDecisionTypes = array_map(function ($i) {
        return $i;
      }, range(1, 7));

      $this->decisionTypes->shouldReceive('getMap')
        ->andReturn($dummyDecisionTypes);
      $this->uploadDao->shouldReceive("getUploadtreeTableName")->withArgs([$item_pk])->andReturn("uploadtree");
      $this->dbHelper->shouldReceive('doesIdExist')
        ->withArgs(["uploadtree", "uploadtree_pk", $item_pk])->andReturn(true);


      $this->viewLicensePlugin->shouldReceive('updateLastItem')->withArgs([2, 2, $item_pk, $item_pk]);

      $info = new Info(200, "Successfully set decision", InfoType::INFO);

      $expectedResponse = (new ResponseHelper())->withJson($info->getArray(), $info->getCode());
      $reqBody = $this->streamFactory->createStream(json_encode(
        $rq
      ));
      $requestHeaders = new Headers();
      $requestHeaders->setHeader('Content-Type', 'application/json');
      $request = new Request("PUT", new Uri("HTTP", "localhost"),
        $requestHeaders, [], [], $reqBody);
      $actualResponse = $this->uploadTreeController->setClearingDecision($request, new ResponseHelper(), ['id' => $upload_pk, 'itemId' => $item_pk]);

      $this->assertEquals($expectedResponse->getStatusCode(),
        $actualResponse->getStatusCode());
      $this->assertEquals($this->getResponseJson($expectedResponse),
        $this->getResponseJson($actualResponse));
    }

    /**
     * @test
     * -# Test for UploadTreeController::setClearingDecision() for setting a clearing decision
     * -# Check if response status is 400, if the given decisionType is invalid
     */
    public function testSetClearingDecisionReturnsError()
    {
      $upload_pk = 1;
      $item_pk = 200;
      $rq = [
        "decisionType" => 40,
        "globalDecision" => false,
      ];
      $dummyDecisionTypes = array_map(function ($i) {
        return $i;
      }, range(1, 7));

      $this->decisionTypes->shouldReceive('getMap')
        ->andReturn($dummyDecisionTypes);
      $this->uploadDao->shouldReceive("getUploadtreeTableName")->withArgs([$item_pk])->andReturn("uploadtree");
      $this->dbHelper->shouldReceive('doesIdExist')
        ->withArgs(["uploadtree", "uploadtree_pk", $item_pk])->andReturn(true);

      $this->viewLicensePlugin->shouldReceive('updateLastItem')->withArgs([2, 2, $item_pk, $item_pk]);

      $info = new Info(400, "Decision Type should be one of the following keys: " . implode(", ", $dummyDecisionTypes), InfoType::ERROR);

      $expectedResponse = (new ResponseHelper())->withJson($info->getArray(), $info->getCode());
      $reqBody = $this->streamFactory->createStream(json_encode(
        $rq
      ));
      $requestHeaders = new Headers();
      $requestHeaders->setHeader('Content-Type', 'application/json');
      $request = new Request("PUT", new Uri("HTTP", "localhost"),
        $requestHeaders, [], [], $reqBody);

      $actualResponse = $this->uploadTreeController->setClearingDecision($request, new ResponseHelper(), ['id' => $upload_pk, 'itemId' => $item_pk]);
      $this->assertEquals($expectedResponse->getStatusCode(), $actualResponse->getStatusCode());
    }
    /**
     * @test
     * -# Test for UploadTreeController::getNextPreviousItem()
     * -# Check if response status is 200 and RES body matches
     */
    public function testGetNextPreviousItem()
    {
      $itemId = 200;
      $uploadId = 1;
      $nextItemId = 915;
      $prevItemId = 109;
      $itemTreeBounds1 = new ItemTreeBounds($nextItemId, 'uploadtree_a', $uploadId, 1, 2);
      $itemTreeBounds2 = new ItemTreeBounds($prevItemId, 'uploadtree_a', $uploadId, 1, 2);

      $item1 = new Item($itemTreeBounds1, 1, 1, 1, "fileName");
      $item2 = new Item($itemTreeBounds2, 1, 1, 1, "fileName");

      $result = array(
        "prevItemId" => $prevItemId,
        "nextItemId" => $nextItemId
      );
      $options = array('skipThese' => "", 'groupId' => $this->groupId);

      $this->dbHelper->shouldReceive('doesIdExist')
        ->withArgs(["upload", "upload_pk", $uploadId])->andReturn(true);
      $this->uploadDao->shouldReceive('getUploadtreeTableName')->withArgs([$uploadId])->andReturn("uploadtree");
      $this->dbHelper->shouldReceive('doesIdExist')
        ->withArgs(["uploadtree", "uploadtree_pk", $itemId])->andReturn(true);

      $this->uploadDao->shouldReceive('getNextItem')->withArgs([$uploadId, $itemId, $options])->andReturn($item1);
      $this->uploadDao->shouldReceive('getPreviousItem')->withArgs([$uploadId, $itemId, $options])->andReturn($item2);
      $expectedResponse = (new ResponseHelper())->withJson($result, 200);
      $queryParams = ['selection' => null];
      $request = $this->getMockBuilder(ServerRequestInterface::class)
        ->getMock();
      $request->expects($this->any())
        ->method('getQueryParams')
        ->willReturn($queryParams);

      $actualResponse = $this->uploadTreeController->getNextPreviousItem($request, new ResponseHelper(), ['id' => $uploadId, 'itemId' => $itemId]);
      $this->assertEquals($expectedResponse->getStatusCode(), $actualResponse->getStatusCode());
      $this->assertEquals($this->getResponseJson($expectedResponse), $this->getResponseJson($actualResponse));
    }

    /**
     * @test
     * -# Test for UploadTreeController::getNextPreviousItem()
     * -# Check if response status 400 & response body matches
     */
    public function testGetNextPreviousItem_isSelectionValid()
    {
      $itemId = 200;
      $uploadId = 1;
      $nextItemId = 915;
      $prevItemId = 109;
      $itemTreeBounds1 = new ItemTreeBounds($nextItemId, 'uploadtree_a', $uploadId, 1, 2);
      $itemTreeBounds2 = new ItemTreeBounds($prevItemId, 'uploadtree_a', $uploadId, 1, 2);

      $item1 = new Item($itemTreeBounds1, 1, 1, 1, "fileName");
      $item2 = new Item($itemTreeBounds2, 1, 1, 1, "fileName");

      $options = array('skipThese' => "", 'groupId' => $this->groupId);

      $this->dbHelper->shouldReceive('doesIdExist')
        ->withArgs(["upload", "upload_pk", $uploadId])->andReturn(true);
      $this->uploadDao->shouldReceive('getUploadtreeTableName')->withArgs([$uploadId])->andReturn("uploadtree");
      $this->dbHelper->shouldReceive('doesIdExist')
        ->withArgs(["uploadtree", "uploadtree_pk", $itemId])->andReturn(true);

      $this->uploadDao->shouldReceive('getNextItem')->withArgs([$uploadId, $itemId, $options])->andReturn($item1);
      $this->uploadDao->shouldReceive('getPreviousItem')->withArgs([$uploadId, $itemId, $options])->andReturn($item2);

      $info = new Info(400, "selection should be either 'withLicenses' or 'noClearing'", InfoType::ERROR);
      $expectedResponse = (new ResponseHelper())->withJson($info->getArray(), 400);
      $queryParams = ['selection' => "invalidSelection"];
      $request = $this->getMockBuilder(ServerRequestInterface::class)
        ->getMock();
      $request->expects($this->any())
        ->method('getQueryParams')
        ->willReturn($queryParams);

      $actualResponse = $this->uploadTreeController->getNextPreviousItem($request, new ResponseHelper(), ['id' => $uploadId, 'itemId' => $itemId]);
      $this->assertEquals($expectedResponse->getStatusCode(), $actualResponse->getStatusCode());
      $this->assertEquals($this->getResponseJson($expectedResponse), $this->getResponseJson($actualResponse));
    }

    /**
     * @test
     * -# Test for UploadTreeController::getClearingHistory()
     * -# Check if response status is 200 and RES body matches
     */
    public function testGetBulkHistory()
    {
      $itemId = 200;
      $uploadId = 1;
      $itemTreeBounds = new ItemTreeBounds($itemId, 'uploadtree', $uploadId, 1, 2);

      $res[] = array(
        "bulkId" => 1,
        "id" => 1,
        "text" => "test",
        "matched" => true,
        "tried" => true,
        "addedLicenses" => [],
        "removedLicenses" => [],
      );

      $obj = new BulkHistory(1, 1, "test", true, true, [], []);
      $updatedRes[] = $obj->getArray();

      $this->dbHelper->shouldReceive('doesIdExist')
        ->withArgs(["upload", "upload_pk", $uploadId])->andReturn(true);

      $this->uploadDao->shouldReceive('getUploadtreeTableName')->withArgs([$uploadId])->andReturn("uploadtree");
      $this->dbHelper->shouldReceive('doesIdExist')
        ->withArgs(["uploadtree", "uploadtree_pk", $itemId])->andReturn(true);

      $this->uploadDao->shouldReceive("getItemTreeBounds")
        ->withArgs([$itemId, "uploadtree"])->andReturn($itemTreeBounds);

      $this->clearingDao->shouldReceive("getBulkHistory")
        ->withArgs([$itemTreeBounds, $this->groupId])->andReturn($res);
      $expectedResponse = (new ResponseHelper())->withJson($updatedRes, 200);
      $actualResponse = $this->uploadTreeController->getBulkHistory(null, new ResponseHelper(), ['id' => $uploadId, 'itemId' => $itemId]);
      $this->assertEquals($expectedResponse->getStatusCode(), $actualResponse->getStatusCode());
      $this->assertEquals($this->getResponseJson($expectedResponse), $this->getResponseJson($actualResponse));
    }

    /**
     * @test
     * -# Test for UploadTreeController::getClearingHistory()
     * -# Check if response status is 200 and RES body matches
     */
    public function testGetClearingHistory()
    {
      $itemId = 200;
      $uploadId = 1;
      $itemTreeBounds = new ItemTreeBounds($itemId, 'uploadtree_a', $uploadId, 1, 2);
      $fileClearings[] = new ClearingDecision(1, 1, $itemId, 1, 1, 1, 3, 1, 1, [], 1, 1, 1);
      $obj = new ClearingHistory(
        date('Y-m-d', 1),
        1,
        "global",
        "TO_BE_DISCUSSED",
        [],
        [],
      );
      $result[] = $obj->getArray();

      $this->dbHelper->shouldReceive('doesIdExist')
        ->withArgs(["upload", "upload_pk", $uploadId])->andReturn(true);

      $this->uploadDao->shouldReceive('getUploadtreeTableName')->withArgs([$uploadId])->andReturn("uploadtree");
      $this->dbHelper->shouldReceive('doesIdExist')
        ->withArgs(["uploadtree", "uploadtree_pk", $itemId])->andReturn(true);

      $this->uploadDao->shouldReceive("getItemTreeBoundsFromUploadId")
        ->withArgs([$itemId, $uploadId])->andReturn($itemTreeBounds);
      $this->clearingDao->shouldReceive("getFileClearings")
        ->withArgs([$itemTreeBounds, $this->groupId, false, true])->andReturn($fileClearings);
      $this->decisionTypes->shouldReceive("getTypeName")
        ->withArgs([$fileClearings[0]->getType()])->andReturn("test");
      $this->decisionScopes->shouldReceive("getTypeName")->withArgs([$fileClearings[0]->getScope()])->andReturn("test");
      $this->decisionTypes->shouldReceive("getConstantNameFromKey")
        ->withArgs([$fileClearings[0]->getType()])->andReturn("TO_BE_DISCUSSED");
      $expectedResponse = (new ResponseHelper())->withJson($result, 200);
      $actualResponse = $this->uploadTreeController->getClearingHistory(null, new ResponseHelper(), ['id' => $uploadId, 'itemId' => $itemId]);
      $this->assertEquals($expectedResponse->getStatusCode(), $actualResponse->getStatusCode());
      $this->assertEquals($this->getResponseJson($expectedResponse), $this->getResponseJson($actualResponse));
    }

    /**
     * @test
     * -# Test for UploadController::viewLicenseFile() with valid status
     * -# Check if response status is 200 and the body has the expected contents
     */
    public function testGetHighlightEntries()
    {
      $uploadId = 1;
      $itemId = 200;
      $res [] = array(
        "start" => 70,
        "end" => 70,
        "type" => "MD",
        "licenseId" => null,
        "refStart" => 0,
        "refEnd" => 53,
        "infoText" => "MIT: 'MIT License\n\nCopyright (c) <year> <copyright holders>'",
        "htmlElement" => null
      );

      $highlight = new Highlight($res[0]["start"], $res[0]["end"], $res[0]["type"], $res[0]["refStart"], $res[0]["refEnd"], $res[0]["infoText"]);
      $itemTreeBounds = new ItemTreeBounds($itemId, 'uploadtree', $uploadId, 1, 2);

      $this->dbHelper->shouldReceive('doesIdExist')
        ->withArgs(["upload", "upload_pk", $uploadId])->andReturn(true);
      $this->uploadDao->shouldReceive("getUploadtreeTableName")->withArgs([$uploadId])->andReturn("uploadtree");
      $this->dbHelper->shouldReceive('doesIdExist')
        ->withArgs(["uploadtree", "uploadtree_pk", $itemId])->andReturn(true);
      $this->uploadDao->shouldReceive("getItemTreeBounds")->withArgs([$itemId, "uploadtree"])->andReturn($itemTreeBounds);
      $this->viewLicensePlugin->shouldReceive('getSelectedHighlighting')->withArgs([$itemTreeBounds, null, null, null, null, $uploadId])->andReturn([$highlight]);

      $expectedResponse = (new ResponseHelper())->withJson($res, 200);
      $queryParams = ['clearingId' => null, 'agentId' => null, 'highlightId' => null, 'licenseId' => null];
      $request = $this->getMockBuilder(ServerRequestInterface::class)
        ->getMock();
      $request->expects($this->any())
        ->method('getQueryParams')
        ->willReturn($queryParams);

      $actualResponse = $this->uploadTreeController->getHighlightEntries($request, new ResponseHelper(), ['id' => $uploadId, 'itemId' => $itemId]);
      $this->assertEquals($expectedResponse->getStatusCode(), $actualResponse->getStatusCode());
      $this->assertEquals($this->getResponseJson($expectedResponse), $this->getResponseJson($actualResponse));
    }
  }
}
