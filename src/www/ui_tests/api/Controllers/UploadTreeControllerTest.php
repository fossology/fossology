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

  use AjaxClearingView;
  use ChangeLicenseBulk;
  use ClearingView;
  use Fossology\Lib\Auth\Auth;
  use Fossology\Lib\BusinessRules\ClearingDecisionProcessor;
  use Fossology\Lib\BusinessRules\LicenseMap;
  use Fossology\Lib\Dao\ClearingDao;
  use Fossology\Lib\Dao\HighlightDao;
  use Fossology\Lib\Dao\LicenseDao;
  use Fossology\Lib\Dao\UploadDao;
  use Fossology\Lib\Data\Clearing\ClearingEvent;
  use Fossology\Lib\Data\Clearing\ClearingEventTypes;
  use Fossology\Lib\Data\Clearing\ClearingLicense;
  use Fossology\Lib\Data\Clearing\ClearingResult;
  use Fossology\Lib\Data\ClearingDecision;
  use Fossology\Lib\Data\DecisionScopes;
  use Fossology\Lib\Data\DecisionTypes;
  use Fossology\Lib\Data\Highlight;
  use Fossology\Lib\Data\LicenseRef;
  use Fossology\Lib\Data\Tree\Item;
  use Fossology\Lib\Data\Tree\ItemTreeBounds;
  use Fossology\Lib\Data\UploadStatus;
  use Fossology\Lib\Db\DbManager;
  use Fossology\UI\Api\Controllers\UploadTreeController;
  use Fossology\UI\Api\Exceptions\HttpBadRequestException;
  use Fossology\UI\Api\Exceptions\HttpForbiddenException;
  use Fossology\UI\Api\Exceptions\HttpNotFoundException;
  use Fossology\UI\Api\Helper\DbHelper;
  use Fossology\UI\Api\Helper\ResponseHelper;
  use Fossology\UI\Api\Helper\RestHelper;
  use Fossology\UI\Api\Models\BulkHistory;
  use Fossology\UI\Api\Models\ClearingHistory;
  use Fossology\UI\Api\Models\Info;
  use Fossology\UI\Api\Models\InfoType;
  use Fossology\UI\Api\Models\License;
  use Fossology\UI\Api\Models\LicenseDecision;
  use Mockery as M;
  use Psr\Http\Message\ServerRequestInterface;
  use Slim\Psr7\Factory\StreamFactory;
  use Slim\Psr7\Headers;
  use Slim\Psr7\Request;
  use Slim\Psr7\Response;
  use Slim\Psr7\Uri;
  use Symfony\Component\HttpFoundation\JsonResponse;

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
     * @var LicenseDao $licenseDao
     * LicenseDao mock
     */
    private $licenseDao;

    /**
     * @var AjaxClearingView $concludeLicensePlugin ;
     * AjaxClearingView mock
     */
    private $concludeLicensePlugin;

    /**
     * @var HighlightDao $highlightDao
     * ClearingDao mock
     */
    private $highlightDao;

    /**
     * @var ClearingEventTypes $clearingEventTypes
     * ClearingDao mock
     */
    private $clearingEventTypes;

    /**
     * @var ClearingDao $clearingDao
     * ClearingDao mock
     */
    private $streamFactory;

    /**
     * @var DecisionScopes $decisionScopes
     * Decision types object
     */
    private $decisionScopes;

    /**
     * @var ChangeLicenseBulk $changeLicenseBulk
     * ChangeLicenseBulk mock
     */
    private $changeLicenseBulk;

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
     * @var ItemTreeBounds $itemTreeBoundsMock
     * ItemTreeBounds Mock
     */
    private $itemTreeBoundsMock;

    /** @var ClearingDecisionProcessor $clearingDecisionEventProcessor
     * ClearingDecisionProcessor Mock
     */
    private $clearingDecisionEventProcessor;

    /**
     * @brief Setup test objects
     * @see PHPUnit_Framework_TestCase::setUp()
     */

    /**
     * @var integer $groupId
     * Group ID to mock
     */
    private $groupId;

    /**
     * @var integer $uploadId
     */
    private $uploadId;
    /**
     * @var integer $agentId
     */
    private $agentId;

    /**
     * @var string $tagId
     */
    private $tagId;

    protected function setUp(): void
    {
      global $container;
      $this->userId = 2;
      $this->groupId = 2;
      $this->uploadId = 3;
      $this->agentId = 5;
      $this->tagId = 7;
      $container = M::mock('ContainerBuilder');
      $this->dbHelper = M::mock(DbHelper::class);
      $this->dbManager = M::mock(DbManager::class);
      $this->restHelper = M::mock(RestHelper::class);
      $this->uploadDao = M::mock(UploadDao::class);
      $this->decisionTypes = M::mock(DecisionTypes::class);
      $this->licenseDao = M::mock(LicenseDao::class);
      $this->clearingDecisionEventProcessor = M::mock(ClearingDecisionProcessor::class);
      $this->viewFilePlugin = M::mock('ui_view');
      $this->viewLicensePlugin = M::mock(ClearingView::class);
      $this->clearingDao = M::mock(ClearingDao::class);
      $this->decisionScopes = M::mock(DecisionScopes::class);
      $this->highlightDao = M::mock(HighlightDao::class);
      $this->clearingEventTypes = M::mock(ClearingEventTypes::class);
      $this->itemTreeBoundsMock = M::mock(ItemTreeBounds::class);
      $this->concludeLicensePlugin = M::mock(AjaxClearingView::class);
      $this->licenseDao = M::mock(LicenseDao::class);
      $this->changeLicenseBulk = M::mock(ChangeLicenseBulk::class);
      $this->licenseDao = M::mock(LicenseDao::class);

      $this->restHelper->shouldReceive('getPlugin')
        ->withArgs(array('view'))->andReturn($this->viewFilePlugin);
      $this->restHelper->shouldReceive('getPlugin')
        ->withArgs(array('view-license'))->andReturn($this->viewLicensePlugin);
      $this->restHelper->shouldReceive('getPlugin')
        ->withArgs(array('conclude-license'))->andReturn($this->concludeLicensePlugin);
      $this->restHelper->shouldReceive('getPlugin')
        ->withArgs(array('change-license-bulk'))->andReturn($this->changeLicenseBulk);

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
      $container->shouldReceive('get')->withArgs(['dao.license'])->andReturn($this->licenseDao);

      $container->shouldReceive('get')->withArgs(['businessrules.clearing_decision_processor'])->andReturn($this->clearingDecisionEventProcessor);
      $container->shouldReceive('get')->withArgs(['dao.highlight'])->andReturn($this->highlightDao);
      $container->shouldReceive('get')->withArgs(['dao.license'])->andReturn($this->licenseDao);
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

      $this->uploadDao->shouldReceive('isAccessible')
        ->withArgs([$upload_pk, $this->groupId])->andReturn(true);
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
      $this->uploadDao->shouldReceive("getUploadtreeTableName")->withArgs([$upload_pk])->andReturn("uploadtree");
      $this->uploadDao->shouldReceive('isAccessible')
        ->withArgs([$upload_pk, $this->groupId])->andReturn(true);
      $this->dbHelper->shouldReceive('doesIdExist')
        ->withArgs(["upload", "upload_pk", $upload_pk])->andReturn(true);
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
      $this->uploadDao->shouldReceive("getUploadtreeTableName")->withArgs([$upload_pk])->andReturn("uploadtree");
      $this->uploadDao->shouldReceive('isAccessible')
        ->withArgs([$upload_pk, $this->groupId])->andReturn(true);
      $this->dbHelper->shouldReceive('doesIdExist')
        ->withArgs(["upload", "upload_pk", $upload_pk])->andReturn(true);
      $this->dbHelper->shouldReceive('doesIdExist')
        ->withArgs(["uploadtree", "uploadtree_pk", $item_pk])->andReturn(true);

      $this->viewLicensePlugin->shouldReceive('updateLastItem')->withArgs([2, 2, $item_pk, $item_pk]);

      $reqBody = $this->streamFactory->createStream(json_encode(
        $rq
      ));
      $requestHeaders = new Headers();
      $requestHeaders->setHeader('Content-Type', 'application/json');
      $request = new Request("PUT", new Uri("HTTP", "localhost"),
        $requestHeaders, [], [], $reqBody);
      $this->expectException(HttpBadRequestException::class);

      $this->uploadTreeController->setClearingDecision($request,
        new ResponseHelper(), ['id' => $upload_pk, 'itemId' => $item_pk]);
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

      $this->uploadDao->shouldReceive('isAccessible')
        ->withArgs([$uploadId, $this->groupId])->andReturn(true);
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

      $this->uploadDao->shouldReceive('isAccessible')
        ->withArgs([$uploadId, $this->groupId])->andReturn(true);
      $this->dbHelper->shouldReceive('doesIdExist')
        ->withArgs(["upload", "upload_pk", $uploadId])->andReturn(true);
      $this->uploadDao->shouldReceive('getUploadtreeTableName')->withArgs([$uploadId])->andReturn("uploadtree");
      $this->dbHelper->shouldReceive('doesIdExist')
        ->withArgs(["uploadtree", "uploadtree_pk", $itemId])->andReturn(true);

      $this->uploadDao->shouldReceive('getNextItem')->withArgs([$uploadId, $itemId, $options])->andReturn($item1);
      $this->uploadDao->shouldReceive('getPreviousItem')->withArgs([$uploadId, $itemId, $options])->andReturn($item2);

      $queryParams = ['selection' => "invalidSelection"];
      $request = $this->getMockBuilder(ServerRequestInterface::class)
        ->getMock();
      $request->expects($this->any())
        ->method('getQueryParams')
        ->willReturn($queryParams);
      $this->expectException(HttpBadRequestException::class);

      $this->uploadTreeController->getNextPreviousItem($request,
      new ResponseHelper(), ['id' => $uploadId, 'itemId' => $itemId]);
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

      $this->uploadDao->shouldReceive('isAccessible')
        ->withArgs([$uploadId, $this->groupId])->andReturn(true);
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
      $body = $this->streamFactory->createStream();
      $request = new Request("GET", new Uri("HTTP", "localhost", 80,
        "/uploads/$uploadId/item/$itemId/clearing-history"),
        new Headers(), [], [], $body);

      $this->uploadDao->shouldReceive('isAccessible')
        ->withArgs([$uploadId, $this->groupId])->andReturn(true);
      $this->dbHelper->shouldReceive('doesIdExist')
        ->withArgs(["upload", "upload_pk", $uploadId])->andReturn(true);

      $this->uploadDao->shouldReceive('getUploadtreeTableName')->withArgs([$uploadId])->andReturn("uploadtree");
      $this->dbHelper->shouldReceive('doesIdExist')
        ->withArgs(["uploadtree", "uploadtree_pk", $itemId])->andReturn(true);

      $this->uploadDao->shouldReceive("getItemTreeBoundsFromUploadId")
        ->withArgs([$itemId, $uploadId])->andReturn($itemTreeBounds);
      $this->clearingDao->shouldReceive("getFileClearings")
        ->withArgs([$itemTreeBounds, $this->groupId, false, true, false])->andReturn($fileClearings);
      $this->decisionTypes->shouldReceive("getTypeName")
        ->withArgs([$fileClearings[0]->getType()])->andReturn("test");
      $this->decisionScopes->shouldReceive("getTypeName")->withArgs([$fileClearings[0]->getScope()])->andReturn("test");
      $this->decisionTypes->shouldReceive("getConstantNameFromKey")
        ->withArgs([$fileClearings[0]->getType()])->andReturn("TO_BE_DISCUSSED");
      $expectedResponse = (new ResponseHelper())->withJson($result, 200);
      $actualResponse = $this->uploadTreeController->getClearingHistory($request, new ResponseHelper(), ['id' => $uploadId, 'itemId' => $itemId]);
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

      $this->uploadDao->shouldReceive('isAccessible')
        ->withArgs([$uploadId, $this->groupId])->andReturn(true);
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

    /**@test
     *  -# Test for UploadTreeController::getTreeView()
     *  -# Check if the HttpNotFoundException is thrown
     * @return void
     * @throws \Fossology\UI\Api\Exceptions\HttpErrorException
     */
      public function testGetTreeViewUploadNotFound(){
        $sort = "sortDir";

        $this->dbHelper->shouldReceive('doesIdExist')
          ->withArgs(["upload", "upload_pk", $this->uploadId])->andReturn(false);


        $reqBody = $this->streamFactory->createStream();
        $requestHeaders = new Headers();

        $queryParams = [
          "agentId" => $this->agentId,
          'sort' => $sort,
          "showQuick" => false
        ];
        $uri = (new Uri('HTTP', 'localhost'))
          ->withQuery(http_build_query($queryParams));

        $requestHeaders->setHeader('Content-Type', 'application/json');
        $request = new Request("POST", $uri,
        $requestHeaders, [], [], $reqBody);

        $this->expectException(HttpNotFoundException::class);
        $this->uploadTreeController->getTreeView($request, new ResponseHelper(), ["id" => $this->uploadId, "itemId"=> 10]);

      }

    /**
     * @test
     *   - # Test UploadTreeController::getTreeView
     *   - # Check if HttpNotFound Exception is thrown for unavailable agent.
     * @return void
     * @throws \Fossology\UI\Api\Exceptions\HttpErrorException
     */
    public function testGetTreeViewAgentNotFound(){
      $sort = "sortDir";

      $this->dbHelper->shouldReceive('doesIdExist')
        ->withArgs(["upload", "upload_pk", $this->uploadId])->andReturn(false);
      $this->dbHelper->shouldReceive('doesIdExist')
        ->withArgs(["agent", "agent_pk", $this->agentId])->andReturn(false);
      $this->uploadDao->shouldReceive('isAccessible')
        ->withArgs([$this->uploadId, $this->groupId])->andReturn(true);

      $reqBody = $this->streamFactory->createStream();
      $requestHeaders = new Headers();

      $queryParams = [
        "agentId" => $this->agentId,
        'sort' => $sort,
        "showQuick" => false
      ];
      $uri = (new Uri('HTTP', 'localhost'))
        ->withQuery(http_build_query($queryParams));

      $requestHeaders->setHeader('Content-Type', 'application/json');
      $request = new Request("POST", $uri,
        $requestHeaders, [], [], $reqBody);

      $this->expectException(HttpNotFoundException::class);
      $this->uploadTreeController->getTreeView($request, new ResponseHelper(), ["id" => $this->uploadId, "itemId"=> 10]);

    }

    /**
     * @test
     *   - # Test UploadTreeController::getTreeView
     *   - # Check if HttpNotFound Exception is thrown for unavailable tag.
     * @return void
     * @throws \Fossology\UI\Api\Exceptions\HttpErrorException
     */
    public function testGetTreeViewTagNotFound(){
      $sort = "sortDir";

      $this->dbHelper->shouldReceive('doesIdExist')
        ->withArgs(["upload", "upload_pk", $this->uploadId])->andReturn(false);
      $this->dbHelper->shouldReceive('doesIdExist')
        ->withArgs(["tag", "tag_pk", $this->tagId])->andReturn(false);
      $this->uploadDao->shouldReceive('isAccessible')
        ->withArgs([$this->uploadId, $this->groupId])->andReturn(true);

      $reqBody = $this->streamFactory->createStream();
      $requestHeaders = new Headers();

      $queryParams = [
        "agentId" => $this->agentId,
        'sort' => $sort,
        "showQuick" => false
      ];
      $uri = (new Uri('HTTP', 'localhost'))
        ->withQuery(http_build_query($queryParams));

      $requestHeaders->setHeader('Content-Type', 'application/json');
      $request = new Request("POST", $uri,
        $requestHeaders, [], [], $reqBody);

      $this->expectException(HttpNotFoundException::class);
      $this->uploadTreeController->getTreeView($request, new ResponseHelper(), ["id" => $this->uploadId, "itemId"=> 10]);

    }

    /**
     * @test
     *   - # Test UploadTreeController::getTreeView
     *   - # Check if HttpForbiden exception is thrown for unaccessible upload
     * @return void
     * @throws \Fossology\UI\Api\Exceptions\HttpErrorException
     */
    public function testGetTreeViewUploadNotAccessible(){
      $sort = "sortDir";

      $this->dbHelper->shouldReceive('doesIdExist')
        ->withArgs(["upload", "upload_pk", $this->uploadId])->andReturn(true);
      $this->uploadDao->shouldReceive('isAccessible')
        ->withArgs([$this->uploadId, $this->groupId])->andReturn(false);

      $reqBody = $this->streamFactory->createStream();
      $requestHeaders = new Headers();

      $queryParams = [
        "agentId" => $this->agentId,
        'sort' => $sort,
        "showQuick" => false
      ];
      $uri = (new Uri('HTTP', 'localhost'))
        ->withQuery(http_build_query($queryParams));
      $requestHeaders->setHeader('Content-Type', 'application/json');
      $request = new Request("POST", $uri,
        $requestHeaders, [], [], $reqBody);
      $this->expectException(HttpForbiddenException::class);
      $this->uploadTreeController->getTreeView($request, new ResponseHelper(), ["id" => $this->uploadId, "itemId"=> 10]);
    }



    /**
     * @test
     * -# Test for UploadTreeController::getLicenseDecisions()
     * -# Check if response status is 200 and RES body matches
     * @throws \Fossology\Lib\Exception
     */
    public function testGetLicenseDecisions()
    {
      $itemId = 200;
      $uploadId = 1;
      $licenseId = 123;
      $licenses = array();

      $itemTreeBounds = new ItemTreeBounds($itemId, 'uploadtree_a', $uploadId, 0, 1);
      $addedClearingResults = [];

      $result = array(
        'name' => 'Imported decision',
        'clearingId' => null,
        'agentId' => null,
        'highlightId' => null,
        'page' => 0,
        'percentage' => null
      );

      $license = new License($licenseId, "MIT", "MIT License", "text", "url", [],
        null, false);
      $licenseDecision = new LicenseDecision($licenseId, "MIT", "MIT License", 'text', "url", array($result),
        '', '', false, [], null, false);

      $licenseRef = new LicenseRef($licenseId, "MIT", "MIT LICENSE", "spx");
      $clearingEvent = new ClearingEvent(1, $itemId, 12, $this->userId, $this->groupId, 4, new ClearingLicense($licenseRef, false, "", "", "", ""));
      $licenseDecisionResult = new ClearingResult($clearingEvent, []);
      $addedClearingResults[$licenseId] = $licenseDecisionResult;

      $body = $this->streamFactory->createStream();
      $request = new Request("GET", new Uri("HTTP", "localhost", 80,
        "/uploads/$uploadId/item/$itemId/licenses"),
        new Headers(), [], [], $body);

      $this->uploadDao->shouldReceive('isAccessible')
        ->withArgs([$uploadId, $this->groupId])->andReturn(true);
      $this->dbHelper->shouldReceive('doesIdExist')
        ->withArgs(["upload", "upload_pk", $uploadId])->andReturn(true);

      $this->uploadDao->shouldReceive('getUploadtreeTableName')->withArgs([$uploadId])->andReturn("uploadtree");
      $this->dbHelper->shouldReceive('doesIdExist')
        ->withArgs(["uploadtree", "uploadtree_pk", $itemId])->andReturn(true);

      $this->uploadDao->shouldReceive("getItemTreeBoundsFromUploadId")->withArgs([$itemId, $uploadId])->andReturn($itemTreeBounds);
      $this->itemTreeBoundsMock->shouldReceive("containsFiles")->andReturn(false);
      $this->clearingDao->shouldReceive('getMainLicenseIds')->andReturn([]);
      $this->clearingEventTypes->shouldReceive('getTypeName')->withArgs([$clearingEvent->getEventType()])->andReturn($result['name']);
      $this->highlightDao->shouldReceive('getHighlightRegion')->withArgs([1])->andReturn([""]);
      $this->clearingDecisionEventProcessor->shouldReceive("getCurrentClearings")->withArgs([$itemTreeBounds, $this->groupId, LicenseMap::CONCLUSION, false])->andReturn([$addedClearingResults, []]);
      $this->licenseDao->shouldReceive('getLicenseObligations')->withArgs([[$licenseId], false])->andReturn([]);
      $this->licenseDao->shouldReceive('getLicenseObligations')->withArgs([[$licenseId], true])->andReturn([]);
      $this->licenseDao->shouldReceive('getLicenseById')->withArgs([$licenseId, $this->groupId])->andReturn($license);
      $licenses[] = $licenseDecision->getArray();

      $expectedResponse = (new ResponseHelper())->withJson($licenses, 200);
      $actualResponse = $this->uploadTreeController->getLicenseDecisions($request, new ResponseHelper(), ['id' => $uploadId, 'itemId' => $itemId]);
      $this->assertEquals($expectedResponse->getStatusCode(), $actualResponse->getStatusCode());
      $this->assertEquals($this->getResponseJson($expectedResponse), $this->getResponseJson($actualResponse));
    }

    /**
     * @test
     * -# Test for UploadTreeController::handleAddEditAndDeleteLicenseDecision() for adding, editing, and deleting license decision
     * -# Check if response status is 200 and response body matches
     */
    public function testHandleAddEditAndDeleteLicenseDecision_Add()
    {
      $uploadId = 1;
      $itemId = 200;
      $shortName = "MIT";
      $licenseId = 23;
      $license = new License($licenseId, "MIT", "MIT License", "risk", "texts", [],
        'type', false);
      $itemTreeBounds = new ItemTreeBounds($itemId, 'uploadtree_a', $uploadId, 1, 2);
      $rq = [
        array(
          "shortName" => "MIT",
          "add" => true
        )
      ];

      $existingLicenses = array(['DT_RowId' => "$itemId,400", 'DT_RowClass' => 'removed']);

      $this->uploadDao->shouldReceive('isAccessible')
        ->withArgs([$uploadId, $this->groupId])->andReturn(true);
      $this->dbHelper->shouldReceive('doesIdExist')
        ->withArgs(["upload", "upload_pk", $uploadId])->andReturn(true);
      $this->uploadDao->shouldReceive("getUploadtreeTableName")->withArgs([$uploadId])->andReturn("uploadtree");
      $this->dbHelper->shouldReceive('doesIdExist')
        ->withArgs(["uploadtree", "uploadtree_pk", $itemId])->andReturn(true);
      $this->uploadDao->shouldReceive('getItemTreeBoundsFromUploadId')->withArgs([$itemId, $uploadId])->andReturn($itemTreeBounds);
      $this->concludeLicensePlugin->shouldReceive('getCurrentSelectedLicensesTableData')->withArgs([$itemTreeBounds, $this->groupId, true])->andReturn($existingLicenses);
      $this->licenseDao->shouldReceive('getLicenseByShortName')
        ->withArgs([$shortName, $this->groupId])->andReturn($license);
      $this->clearingDao->shouldReceive('insertClearingEvent')
        ->withArgs([$itemId, $this->userId, $this->groupId, $licenseId, false])->andReturn(null);

      $info = new Info(200, 'Successfully added MIT as a new license decision.', InfoType::INFO);
      $res = [
        'success' => [$info->getArray()],
        'errors' => []
      ];
      $expectedResponse = (new ResponseHelper())->withJson($res, 200);
      $reqBody = $this->streamFactory->createStream(json_encode(
        $rq
      ));
      $requestHeaders = new Headers();
      $requestHeaders->setHeader('Content-Type', 'application/json');
      $request = new Request("PUT", new Uri("HTTP", "localhost"),
        $requestHeaders, [], [], $reqBody);
      $actualResponse = $this->uploadTreeController->handleAddEditAndDeleteLicenseDecision($request, new ResponseHelper(), ['id' => $uploadId, 'itemId' => $itemId]);

      $this->assertEquals($expectedResponse->getStatusCode(),
        $actualResponse->getStatusCode());
      $this->assertEquals($this->getResponseJson($expectedResponse),
        $this->getResponseJson($actualResponse));
    }

    /**
     * @test
     * -# Test for UploadTreeController::handleAddEditAndDeleteLicenseDecision() for adding, editing, and deleting license decision
     * -# Check if response status is 200 and response body matches
     */
    public function testHandleAddEditAndDeleteLicenseDecision_Edit()
    {
      $uploadId = 1;
      $itemId = 200;
      $shortName = "MIT";
      $licenseId = 23;
      $license = new License($licenseId, "MIT", "MIT License", "risk", "texts", [],
        'type', false);
      $itemTreeBounds = new ItemTreeBounds($itemId, 'uploadtree_a', $uploadId, 1, 2);
      $rq = [
        array(
          "shortName" => "MIT",
          "add" => true,
          "text" => "Updated license text",
        )
      ];

      $existingLicenses = array(['DT_RowId' => "$itemId,$licenseId", 'DT_RowClass' => 'removed']);

      $this->uploadDao->shouldReceive('isAccessible')
        ->withArgs([$uploadId, $this->groupId])->andReturn(true);
      $this->dbHelper->shouldReceive('doesIdExist')
        ->withArgs(["upload", "upload_pk", $uploadId])->andReturn(true);
      $this->uploadDao->shouldReceive("getUploadtreeTableName")->withArgs([$uploadId])->andReturn("uploadtree");
      $this->dbHelper->shouldReceive('doesIdExist')
        ->withArgs(["uploadtree", "uploadtree_pk", $itemId])->andReturn(true);
      $this->uploadDao->shouldReceive('getItemTreeBoundsFromUploadId')->withArgs([$itemId, $uploadId])->andReturn($itemTreeBounds);
      $this->concludeLicensePlugin->shouldReceive('getCurrentSelectedLicensesTableData')->withArgs([$itemTreeBounds, $this->groupId, true])->andReturn($existingLicenses);
      $this->licenseDao->shouldReceive('getLicenseByShortName')
        ->withArgs([$shortName, $this->groupId])->andReturn($license);
      $this->clearingDao->shouldReceive('updateClearingEvent')
        ->withArgs([$itemId, $this->userId, $this->groupId, $licenseId, 'reportinfo', "Updated license text"])->andReturn(null);

      $info = new Info(200, "Successfully updated MIT's license reportinfo", InfoType::INFO);
      $res = [
        'success' => [$info->getArray()],
        'errors' => []
      ];
      $expectedResponse = (new ResponseHelper())->withJson($res, 200);
      $reqBody = $this->streamFactory->createStream(json_encode(
        $rq
      ));
      $requestHeaders = new Headers();
      $requestHeaders->setHeader('Content-Type', 'application/json');
      $request = new Request("PUT", new Uri("HTTP", "localhost"),
        $requestHeaders, [], [], $reqBody);
      $actualResponse = $this->uploadTreeController->handleAddEditAndDeleteLicenseDecision($request, new ResponseHelper(), ['id' => $uploadId, 'itemId' => $itemId]);

      $this->assertEquals($expectedResponse->getStatusCode(),
        $actualResponse->getStatusCode());
      $this->assertEquals($this->getResponseJson($expectedResponse),
        $this->getResponseJson($actualResponse));
    }

    /**
     * @test
     * -# Test for UploadTreeController::handleAddEditAndDeleteLicenseDecision() for adding, editing, and deleting license decision
     * -# Check if response status is 200 and response body matches
     */
    public function testHandleAddEditAndDeleteLicenseDecision_Delete()
    {
      $uploadId = 1;
      $itemId = 200;
      $shortName = "MIT";
      $licenseId = 23;
      $license = new License($licenseId, "MIT", "MIT License", "risk", "texts", [],
        'type', false);
      $itemTreeBounds = new ItemTreeBounds($itemId, 'uploadtree_a', $uploadId, 1, 2);
      $rq = [
        array(
          "shortName" => "MIT",
          "add" => false,
          "text" => "Updated license text",
        )
      ];

      $existingLicenses = array(['DT_RowId' => "$itemId,$licenseId", 'DT_RowClass' => 'removed']);

      $this->uploadDao->shouldReceive('isAccessible')
        ->withArgs([$uploadId, $this->groupId])->andReturn(true);
      $this->dbHelper->shouldReceive('doesIdExist')
        ->withArgs(["upload", "upload_pk", $uploadId])->andReturn(true);
      $this->uploadDao->shouldReceive("getUploadtreeTableName")->withArgs([$uploadId])->andReturn("uploadtree");
      $this->dbHelper->shouldReceive('doesIdExist')
        ->withArgs(["uploadtree", "uploadtree_pk", $itemId])->andReturn(true);
      $this->uploadDao->shouldReceive('getItemTreeBoundsFromUploadId')->withArgs([$itemId, $uploadId])->andReturn($itemTreeBounds);
      $this->concludeLicensePlugin->shouldReceive('getCurrentSelectedLicensesTableData')->withArgs([$itemTreeBounds, $this->groupId, true])->andReturn($existingLicenses);
      $this->licenseDao->shouldReceive('getLicenseByShortName')
        ->withArgs([$shortName, $this->groupId])->andReturn($license);
      $this->clearingDao->shouldReceive('insertClearingEvent')
        ->withArgs([$itemId, $this->userId, $this->groupId, $licenseId, true])->andReturn(null);

      $info = new Info(200, 'Successfully deleted MIT from license decision list.', InfoType::INFO);
      $res = [
        'success' => [$info->getArray()],
        'errors' => []
      ];
      $expectedResponse = (new ResponseHelper())->withJson($res, 200);
      $reqBody = $this->streamFactory->createStream(json_encode(
        $rq
      ));
      $requestHeaders = new Headers();
      $requestHeaders->setHeader('Content-Type', 'application/json');
      $request = new Request("PUT", new Uri("HTTP", "localhost"),
        $requestHeaders, [], [], $reqBody);
      $actualResponse = $this->uploadTreeController->handleAddEditAndDeleteLicenseDecision($request, new ResponseHelper(), ['id' => $uploadId, 'itemId' => $itemId]);

      $this->assertEquals($expectedResponse->getStatusCode(),
        $actualResponse->getStatusCode());
      $this->assertEquals($this->getResponseJson($expectedResponse),
        $this->getResponseJson($actualResponse));
    }

    /**
     * @test
     * -# Test for UploadTreeController::getNextPreviousItem()
     * -# Check if response status is 200 and RES body matches
     */
    public function testScheduleBulkScan()
    {
      $licenseId = 357;
      $licenseShortName = "MIT";
      $existingLicense = new License( $licenseId, $licenseShortName, "FULL NAME", "text", "url", [], "1");
      $body = [
        'bulkActions' => [
          [
            'licenseShortName' => $licenseShortName,
            'licenseText' => '',
            'acknowledgement' => '',
            'comment' => '',
            'licenseAction' => 'ADD'
          ]
        ],
        'refText' => 'Copyright (c) 2011-2023 The Bootstrap Authors',
        'bulkScope' => 'folder',
        'forceDecision' => 0,
        'ignoreIrre' => 0,
        'delimiters' => 'DEFAULT',
        'scanOnlyFindings' => 0
      ];
      $jobId = 122;
      $res = new JsonResponse(array("jqid" => $jobId));

      $itemId = 1;
      $uploadId = 1;

      $this->uploadDao->shouldReceive('isAccessible')
        ->withArgs([$uploadId, $this->groupId])->andReturn(true);
      $this->dbHelper->shouldReceive('doesIdExist')
        ->withArgs(["upload", "upload_pk", $uploadId])->andReturn(true);
      $this->uploadDao->shouldReceive('getUploadtreeTableName')->withArgs([$uploadId])->andReturn("uploadtree");
      $this->dbHelper->shouldReceive('doesIdExist')
        ->withArgs(["uploadtree", "uploadtree_pk", $itemId])->andReturn(true);
      $this->dbHelper->shouldReceive('doesIdExist')
        ->withArgs(["license_ref", "rf_pk", $licenseId])->andReturn(true);
      $this->licenseDao->shouldReceive('getLicenseByShortName')
        ->withArgs([$licenseShortName, $this->groupId])
        ->andReturn($existingLicense);
      $this->changeLicenseBulk->shouldReceive('handle')
        ->withArgs([M::any()])
        ->andReturn($res);

      $info = new Info(201, json_decode($res->getContent(), true)["jqid"], InfoType::INFO);

      $reqBody = $this->streamFactory->createStream(json_encode(
        $body
      ));

      $requestHeaders = new Headers();
      $requestHeaders->setHeader('Content-Type', 'application/json');
      $request = new Request("POST", new Uri("HTTP", "localhost"),
        $requestHeaders, [], [], $reqBody);

      $expectedResponse = (new ResponseHelper())->withJson($info->getArray(), $info->getCode());
      $actualResponse = $this->uploadTreeController->scheduleBulkScan($request, new ResponseHelper(), ['id' => $uploadId, 'itemId' => $itemId]);

      $this->assertEquals($expectedResponse->getStatusCode(),
        $actualResponse->getStatusCode());
      $this->assertEquals($this->getResponseJson($expectedResponse),
        $this->getResponseJson($actualResponse));
    }
  }
}
