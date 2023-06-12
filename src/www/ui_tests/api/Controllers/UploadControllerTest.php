<?php
/*
 SPDX-FileCopyrightText: © 2020 Siemens AG
 Author: Gaurav Mishra <mishra.gaurav@siemens.com>
 SPDX-FileCopyrightText: © 2022 Samuel Dushimimana <dushsam100@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @file
 * @brief Tests for UploadController
 */

namespace Fossology\UI\Api\Test\Controllers;

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\AgentDao;
use Fossology\Lib\Dao\ClearingDao;
use Fossology\Lib\Dao\FolderDao;
use Fossology\Lib\Dao\LicenseDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Dao\UserDao;
use Fossology\Lib\Data\Tree\ItemTreeBounds;
use Fossology\Lib\Data\UploadStatus;
use Fossology\Lib\Db\DbManager;
use Fossology\UI\Api\Controllers\UploadController;
use Fossology\UI\Api\Helper\DbHelper;
use Fossology\UI\Api\Helper\ResponseHelper;
use Fossology\UI\Api\Helper\RestHelper;
use Fossology\UI\Api\Models\Hash;
use Fossology\UI\Api\Models\Info;
use Fossology\UI\Api\Models\InfoType;
use Fossology\UI\Api\Models\License;
use Fossology\UI\Api\Models\Upload;
use Mockery as M;
use Slim\Psr7\Factory\StreamFactory;
use Slim\Psr7\Headers;
use Slim\Psr7\Request;
use Slim\Psr7\Uri;

function TryToDelete($uploadpk, $user_pk, $group_pk, $uploadDao)
{
  return UploadControllerTest::$functions->TryToDelete($uploadpk, $user_pk,
    $group_pk, $uploadDao);
}

/**
 * @class UploadControllerTest
 * @brief Unit tests for UploadController
 */
class UploadControllerTest extends \PHPUnit\Framework\TestCase
{
  /**
   * @var \Mockery\MockInterface $functions
   * Public function mock
   */
  public static $functions;

  /**
   * @var integer $assertCountBefore
   * Assertions before running tests
   */
  private $assertCountBefore;

  /**
   * @var integer $userId
   * User ID to mock
   */
  private $userId;

  /**
   * @var integer $groupId
   * Group ID to mock
   */
  private $groupId;

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
   * @var UploadController $uploadController
   * UploadController mock
   */
  private $uploadController;

  /**
   * @var UploadDao $uploadDao
   * UploadDao mock
   */
  private $uploadDao;

  /**
   * @var FolderDao $folderDao
   * FolderDao mock
   */
  private $folderDao;

  /**
   * @var AgentDao $agentDao
   * AgentDao mock
   */
  private $agentDao;

  /**
   * @var ClearingDao $clearingDao
   * ClearingDao mock
   */
  private $clearingDao;

  /**
   * @var LicenseDao $licenseDao
   * LicenseDao mock
   */
  private $licenseDao;


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
    $this->userId = 2;
    $this->groupId = 2;
    $container = M::mock('ContainerBuilder');
    self::$functions = M::mock();
    $this->dbHelper = M::mock(DbHelper::class);
    $this->dbManager = M::mock(DbManager::class);
    $this->restHelper = M::mock(RestHelper::class);
    $this->uploadDao = M::mock(UploadDao::class);
    $this->folderDao = M::mock(FolderDao::class);
    $this->agentDao = M::mock(AgentDao::class);
    $this->userDao = M::mock(UserDao::class);
    $this->clearingDao = M::mock(ClearingDao::class);
    $this->licenseDao = M::mock(LicenseDao::class);


    $this->dbManager->shouldReceive('getSingleRow')
      ->withArgs([M::any(), [$this->groupId, UploadStatus::OPEN,
        Auth::PERM_READ]]);
    $this->dbHelper->shouldReceive('getDbManager')->andReturn($this->dbManager);

    $this->restHelper->shouldReceive('getDbHelper')->andReturn($this->dbHelper);
    $this->restHelper->shouldReceive('getGroupId')->andReturn($this->groupId);
    $this->restHelper->shouldReceive('getUserId')->andReturn($this->userId);
    $this->restHelper->shouldReceive('getUploadDao')
      ->andReturn($this->uploadDao);
    $this->restHelper->shouldReceive('getFolderDao')
      ->andReturn($this->folderDao);
    $this->restHelper->shouldReceive('getUserDao')
      ->andReturn($this->userDao);
    $container->shouldReceive('get')->withArgs(['dao.license'])->andReturn(
      $this->licenseDao);
    $container->shouldReceive('get')->withArgs(array(
      'dao.clearing'))->andReturn($this->clearingDao);
    $container->shouldReceive('get')->withArgs(array(
      'helper.restHelper'))->andReturn($this->restHelper);
    $container->shouldReceive('get')->withArgs(array(
      'dao.agent'))->andReturn($this->agentDao);
    $container->shouldReceive('get')->withArgs(array('dao.license'))->andReturn($this->licenseDao);
    $this->uploadController = new UploadController($container);
    $this->assertCountBefore = \Hamcrest\MatcherAssert::getCount();
    $this->streamFactory = new StreamFactory();
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
   * @param Response $response
   * @return array Decoded response
   */
  private function getResponseJson($response)
  {
    $response->getBody()->seek(0);
    return json_decode($response->getBody()->getContents(), true);
  }

  /**
   * Helper function to generate uploads bounds
   * @param integer $id Upload id (if > 4, return false)
   * @return false|ItemTreeBounds
   */
  private function getUploadBounds($id)
  {
    if ($id > 4) {
      return false;
    }
    $itemId = ($id * 100) + 1;
    $left = ($id * 100) + 2;
    $right = ($id * 100) + 50;
    return new ItemTreeBounds($itemId, 'uploadtree_a', $id, $left, $right);
  }

  /**
   * Helper function to generate uploads
   * @param integer $id Upload id (if > 4, return NULL)
   * @return NULL|Upload
   */
  private function getUpload($id)
  {
    $uploadName = "";
    $description = "";
    $uploadDate = "";
    $folderId = 2;
    $folderName = "SR";
    $fileSize = 0;
    switch ($id) {
      case 2:
        $uploadName = "top$id";
        $uploadDate = "01-01-2020";
        $fileSize = 123;
        break;
      case 3:
        $uploadName = "child$id";
        $uploadDate = "02-01-2020";
        $fileSize = 133;
        break;
      case 4:
        $uploadName = "child$id";
        $uploadDate = "03-01-2020";
        $fileSize = 153;
        break;
      default:
        return null;
    }
    $hash = new Hash('sha1checksum', 'md5checksum', 'sha256checksum', $fileSize);
    return new Upload($folderId, $folderName, $id, $description,
      $uploadName, $uploadDate, null, $hash);
  }

  /**
   * @test
   * -# Test for UploadController::getUploads() to fetch single upload
   * -# Check if response is 200
   */
  public function testGetSingleUpload()
  {
    $uploadId = 3;
    $options = [
      "folderId" => null,
      "name"     => null,
      "status"   => null,
      "assignee" => null,
      "since"    => null
    ];
    $upload = $this->getUpload($uploadId);
    $requestHeaders = new Headers();
    $body = $this->streamFactory->createStream();
    $request = new Request("GET", new Uri("HTTP", "localhost", 80,
      "/uploads/$uploadId"), $requestHeaders, [], [], $body);
    $this->dbHelper->shouldReceive('doesIdExist')
      ->withArgs(["upload", "upload_pk", $uploadId])->andReturn(true);
    $this->uploadDao->shouldReceive('isAccessible')
      ->withArgs([$uploadId, $this->groupId])->andReturn(true);
    $this->uploadDao->shouldReceive('getParentItemBounds')
      ->withArgs([$uploadId])->andReturn($this->getUploadBounds($uploadId));
    $this->dbHelper->shouldReceive('getUploads')
      ->withArgs([$this->userId, $this->groupId, 100, 1, $uploadId, $options,
      true])->andReturn([1, [$upload->getArray()]]);
    $expectedResponse = (new ResponseHelper())->withJson($upload->getArray(), 200);
    $actualResponse = $this->uploadController->getUploads($request,
      new ResponseHelper(), ['id' => $uploadId]);
    $this->assertEquals($expectedResponse->getStatusCode(),
      $actualResponse->getStatusCode());
    $this->assertEquals($this->getResponseJson($expectedResponse),
      $this->getResponseJson($actualResponse));
  }

  /**
   * @test
   * -# Test for UploadController::getUploads() for inaccessible upload
   * -# Check if response status is 403
   */
  public function testGetSingleUploadInAccessible()
  {
    $uploadId = 3;
    $requestHeaders = new Headers();
    $body = $this->streamFactory->createStream();
    $request = new Request("GET", new Uri("HTTP", "localhost", 80,
      "/uploads/$uploadId"), $requestHeaders, [], [], $body);
    $this->dbHelper->shouldReceive('doesIdExist')
      ->withArgs(["upload", "upload_pk", $uploadId])->andReturn(true);
    $this->uploadDao->shouldReceive('isAccessible')
      ->withArgs([$uploadId, $this->groupId])->andReturn(false);
    $expectedResponse = new Info(403, "Upload is not accessible",
      InfoType::ERROR);
    $actualResponse = $this->uploadController->getUploads($request,
      new ResponseHelper(), ['id' => $uploadId]);
    $this->assertEquals($expectedResponse->getCode(),
      $actualResponse->getStatusCode());
    $this->assertEquals($expectedResponse->getArray(),
      $this->getResponseJson($actualResponse));
  }

  /**
   * @test
   * -# Test for UploadController::getUploads() with filters
   * -# Setup various options according to query parameter which will be passed
   *    to DbHelper::getUploads()
   * -# Make sure all mocks are having once() set to force mockery
   */
  public function testGetUploadWithFilters()
  {
    $options = [
      "folderId" => null,
      "name"     => null,
      "status"   => null,
      "assignee" => null,
      "since"    => null
    ];

    // Test for folder filter
    $folderId = 2;
    $folderOptions = $options;
    $folderOptions["folderId"] = $folderId;
    $requestHeaders = new Headers();
    $body = $this->streamFactory->createStream();
    $request = new Request("GET", new Uri("HTTP", "localhost", 80,
      "/uploads", UploadController::FOLDER_PARAM . "=$folderId"),
      $requestHeaders, [], [], $body);
    $this->folderDao->shouldReceive('isFolderAccessible')
      ->withArgs([$folderId, $this->userId])->andReturn(true)->once();
    $this->dbHelper->shouldReceive('getUploads')
      ->withArgs([$this->userId, $this->groupId, 100, 1, null, $folderOptions,
      true])->andReturn([1, []])->once();
    $this->uploadController->getUploads($request, new ResponseHelper(), []);

    // Test for name filter
    $name = "foss";
    $nameOptions = $options;
    $nameOptions["name"] = $name;
    $requestHeaders = new Headers();
    $body = $this->streamFactory->createStream();
    $request = new Request("GET", new Uri("HTTP", "localhost", 80,
      "/uploads", UploadController::FILTER_NAME . "=$name"), $requestHeaders,
      [], [], $body);
    $this->dbHelper->shouldReceive('getUploads')
      ->withArgs([$this->userId, $this->groupId, 100, 1, null, $nameOptions,
      true])->andReturn([1, []])->once();
    $this->uploadController->getUploads($request, new ResponseHelper(), []);

    // Test for status filter
    $statusString = "InProgress";
    $status = UploadStatus::IN_PROGRESS;
    $statusOptions = $options;
    $statusOptions["status"] = $status;
    $requestHeaders = new Headers();
    $body = $this->streamFactory->createStream();
    $request = new Request("GET", new Uri("HTTP", "localhost", 80,
      "/uploads", UploadController::FILTER_STATUS . "=$statusString"),
      $requestHeaders, [], [], $body);
    $this->dbHelper->shouldReceive('getUploads')
      ->withArgs([$this->userId, $this->groupId, 100, 1, null, $statusOptions,
      true])->andReturn([1, []])->once();
    $this->uploadController->getUploads($request, new ResponseHelper(), []);

    // Test for assignee filter
    $assignee = "-me-";
    $assigneeOptions = $options;
    $assigneeOptions["assignee"] = $this->userId;
    $requestHeaders = new Headers();
    $body = $this->streamFactory->createStream();
    $request = new Request("GET", new Uri("HTTP", "localhost", 80,
      "/uploads", UploadController::FILTER_ASSIGNEE . "=$assignee"),
      $requestHeaders, [], [], $body);
    $this->dbHelper->shouldReceive('getUploads')
      ->withArgs([$this->userId, $this->groupId, 100, 1, null, $assigneeOptions,
      true])->andReturn([1, []])->once();
    $this->uploadController->getUploads($request, new ResponseHelper(), []);

    // Test for since filter
    $since = "2021-02-28";
    $sinceOptions = $options;
    $sinceOptions["since"] = strtotime($since);
    $requestHeaders = new Headers();
    $body = $this->streamFactory->createStream();
    $request = new Request("GET", new Uri("HTTP", "localhost", 80,
      "/uploads", UploadController::FILTER_DATE . "=$since"),
      $requestHeaders, [], [], $body);
    $this->dbHelper->shouldReceive('getUploads')
      ->withArgs([$this->userId, $this->groupId, 100, 1, null, $sinceOptions,
      true])->andReturn([1, []])->once();
    $this->uploadController->getUploads($request, new ResponseHelper(), []);

    // Test for status and since filter
    $statusString = "Open";
    $status = UploadStatus::OPEN;
    $since = "2021-02-28";
    $combOptions = $options;
    $combOptions["since"] = strtotime($since);
    $combOptions["status"] = $status;
    $requestHeaders = new Headers();
    $body = $this->streamFactory->createStream();
    $request = new Request("GET", new Uri("HTTP", "localhost", 80,
      "/uploads", UploadController::FILTER_DATE . "=$since&" .
      UploadController::FILTER_STATUS . "=$statusString"),
      $requestHeaders, [], [], $body);
    $this->dbHelper->shouldReceive('getUploads')
      ->withArgs([$this->userId, $this->groupId, 100, 1, null, $combOptions,
      true])->andReturn([1, []])->once();
    $this->uploadController->getUploads($request, new ResponseHelper(), []);
  }

  /**
   * @test
   * -# Test UploadController::getUploads() for all uploads
   * -# Check if the response is array with status 200
   */
  public function testGetUploads()
  {
    $uploads = [
      $this->getUpload(2)->getArray(),
      $this->getUpload(3)->getArray(),
      $this->getUpload(4)->getArray()
    ];
    $options = [
      "folderId" => null,
      "name"     => null,
      "status"   => null,
      "assignee" => null,
      "since"    => null
    ];
    $requestHeaders = new Headers();
    $body = $this->streamFactory->createStream();
    $request = new Request("GET", new Uri("HTTP", "localhost", 80,
      "/uploads/"), $requestHeaders, [], [], $body);
    $this->dbHelper->shouldReceive('getUploads')
      ->withArgs([$this->userId, $this->groupId, 100, 1, null, $options, true])
      ->andReturn([1, $uploads]);
    $expectedResponse = (new ResponseHelper())->withJson($uploads, 200);
    $actualResponse = $this->uploadController->getUploads($request,
      new ResponseHelper(), []);
    $this->assertEquals($expectedResponse->getStatusCode(),
      $actualResponse->getStatusCode());
    $this->assertEquals($this->getResponseJson($expectedResponse),
      $this->getResponseJson($actualResponse));
  }

  /**
   * @test
   * -# Test for UploadController::getUploads() where ununpack not finished
   * -# Check if the response status is 503
   * -# Check if the response has headers `Retry-After` and `Look-at` set
   */
  public function testGetSingleUploadNotUnpacked()
  {
    $uploadId = 3;
    $requestHeaders = new Headers();
    $body = $this->streamFactory->createStream();
    $request = new Request("GET", new Uri("HTTP", "localhost", 80,
      "/uploads/"), $requestHeaders, [], [], $body);
    $this->dbHelper->shouldReceive('doesIdExist')
      ->withArgs(["upload", "upload_pk", $uploadId])->andReturn(true);
    $this->uploadDao->shouldReceive('isAccessible')
      ->withArgs([$uploadId, $this->groupId])->andReturn(true);
    $this->uploadDao->shouldReceive('getParentItemBounds')
      ->withArgs([$uploadId])->andReturn(false);

    $expectedResponse = new ResponseHelper();
    $returnVal = new Info(503,
      "Ununpack job not started. Please check job status at " .
      "/api/v1/jobs?upload=" . $uploadId, InfoType::INFO);
    $expectedResponse = $expectedResponse->withHeader('Retry-After', '60')
      ->withHeader('Look-at', "/api/v1/jobs?upload=" . $uploadId)
      ->withJson($returnVal->getArray(), $returnVal->getCode());
    $actualResponse = $this->uploadController->getUploads($request,
      new ResponseHelper(), ['id' => $uploadId]);
    $this->assertEquals($expectedResponse->getStatusCode(),
      $actualResponse->getStatusCode());
    $this->assertEquals($this->getResponseJson($expectedResponse),
      $this->getResponseJson($actualResponse));
    $this->assertEquals($expectedResponse->getHeaders(),
      $actualResponse->getHeaders());
  }

  /**
   * @test
   * -# Test for UploadController::moveUpload() for a copy action
   * -# Check if response status is 202
   */
  public function testCopyUpload()
  {
    $uploadId = 3;
    $folderId = 5;
    $info = new Info(202, "Upload $uploadId will be copied to folder $folderId",
      InfoType::INFO);

    $this->restHelper->shouldReceive('copyUpload')
      ->withArgs([$uploadId, $folderId, true])->andReturn($info);
    $expectedResponse = (new ResponseHelper())->withJson($info->getArray(),
      $info->getCode());

    $requestHeaders = new Headers();
    $requestHeaders->setHeader('folderId', $folderId);
    $requestHeaders->setHeader('action', 'copy');
    $body = $this->streamFactory->createStream();
    $request = new Request("PUT", new Uri("HTTP", "localhost"),
      $requestHeaders, [], [], $body);
    $actualResponse = $this->uploadController->moveUpload($request,
      new ResponseHelper(), ['id' => $uploadId]);
    $this->assertEquals($expectedResponse->getStatusCode(),
      $actualResponse->getStatusCode());
    $this->assertEquals($this->getResponseJson($expectedResponse),
      $this->getResponseJson($actualResponse));
  }

  /**
   * @test
   * -# Test for UploadController::moveUpload() with invalid folder id
   * -# Check if response status is 400
   */
  public function testMoveUploadInvalidFolder()
  {
    $uploadId = 3;
    $info = new Info(400, "folderId header should be an integer!",
      InfoType::ERROR);

    $expectedResponse = (new ResponseHelper())->withJson($info->getArray(),
      $info->getCode());

    $requestHeaders = new Headers();
    $requestHeaders->setHeader('folderId', 'alpha');
    $requestHeaders->setHeader('action', 'move');
    $body = $this->streamFactory->createStream();
    $request = new Request("PATCH", new Uri("HTTP", "localhost"),
      $requestHeaders, [], [], $body);

    $actualResponse = $this->uploadController->moveUpload($request,
      new ResponseHelper(), ['id' => $uploadId]);

    $this->assertEquals($expectedResponse->getStatusCode(),
      $actualResponse->getStatusCode());
    $this->assertEquals($this->getResponseJson($expectedResponse),
      $this->getResponseJson($actualResponse));
  }

  /**
   * @runInSeparateProcess
   * @preserveGlobalState disabled
   * @test
   * -# Test for UploadController::postUpload()
   * -# Check if response status is 201 with upload id
   */
  public function testPostUpload()
  {
    $folderId = 2;
    $uploadId = 20;
    $uploadDescription = "Test Upload";
    $reqBody = [
      "location" => "data",
      "scanOptions" => "scanOptions"
    ];

    $requestHeaders = new Headers();
    $requestHeaders->setHeader('folderId', $folderId);
    $requestHeaders->setHeader('uploadDescription', $uploadDescription);
    $requestHeaders->setHeader('ignoreScm', 'true');
    $requestHeaders->setHeader('Content-Type', 'application/json');

    $body = $this->streamFactory->createStream(json_encode(
      $reqBody
    ));
    $request = new Request("POST", new Uri("HTTP", "localhost"),
      $requestHeaders, [], [], $body);

    $uploadHelper = M::mock('overload:Fossology\UI\Api\Helper\UploadHelper');
    $uploadHelper->shouldReceive('createNewUpload')
      ->withArgs([$reqBody["location"], $folderId, $uploadDescription, 'protected', 'true',
        'vcs', false])
      ->andReturn([true, '', '', $uploadId]);

    $info = new Info(201, intval(20), InfoType::INFO);

    $uploadHelper->shouldReceive('handleScheduleAnalysis')->withArgs([$uploadId,$folderId,$reqBody["scanOptions"],false])
    ->andReturn($info);

    $this->folderDao->shouldReceive('getAllFolderIds')->andReturn([2,3,4]);
    $this->folderDao->shouldReceive('isFolderAccessible')
      ->withArgs([$folderId])->andReturn(true);


    $expectedResponse = (new ResponseHelper())->withJson($info->getArray(),
      $info->getCode());
    $actualResponse = $this->uploadController->postUpload($request,
      new ResponseHelper(), []);
    $this->assertEquals($expectedResponse->getStatusCode(),
      $actualResponse->getStatusCode());
    $this->assertEquals($this->getResponseJson($expectedResponse),
      $this->getResponseJson($actualResponse));
  }

  /**
   * @runInSeparateProcess
   * @preserveGlobalState disabled
   * @test
   * -# Test for UploadController::postUpload() with inaccessible folder
   * -# Check if response status is 403
   */
  public function testPostUploadFolderNotAccessible()
  {
    $folderId = 2;
    $uploadDescription = "Test Upload";

    $requestHeaders = new Headers();
    $requestHeaders->setHeader('folderId', $folderId);
    $requestHeaders->setHeader('Content-type', 'application/json');
    $requestHeaders->setHeader('uploadDescription', $uploadDescription);
    $requestHeaders->setHeader('ignoreScm', 'true');
    $body = $this->streamFactory->createStream(json_encode([
      'location' => "data",
      'scanOptions' => 'scanOptions'
    ]));
    $request = new Request("POST", new Uri("HTTP", "localhost"),
      $requestHeaders, [], [], $body);

    $uploadHelper = M::mock('overload:Fossology\UI\Api\Helper\UploadHelper');

    $this->folderDao->shouldReceive('getAllFolderIds')->andReturn([2,3,4]);
    $this->folderDao->shouldReceive('isFolderAccessible')
      ->withArgs([$folderId])->andReturn(false);

    $info = new Info(403, "folderId $folderId is not accessible!",
      InfoType::ERROR);
    $expectedResponse = (new ResponseHelper())->withJson($info->getArray(),
      $info->getCode());
    $actualResponse = $this->uploadController->postUpload($request,
      new ResponseHelper(), []);
    $this->assertEquals($expectedResponse->getStatusCode(),
      $actualResponse->getStatusCode());
    $this->assertEquals($this->getResponseJson($expectedResponse),
      $this->getResponseJson($actualResponse));
  }

  /**
   * @runInSeparateProcess
   * @preserveGlobalState disabled
   * @test
   * -# Test for UploadController::postUpload() with invalid folder id
   * -# Check if response status is 404
   */
  public function testPostUploadFolderNotFound()
  {
    $folderId = 8;
    $uploadDescription = "Test Upload";

    $requestHeaders = new Headers();
    $requestHeaders->setHeader('folderId', $folderId);
    $requestHeaders->setHeader('Content-type', 'application/json');
    $requestHeaders->setHeader('uploadDescription', $uploadDescription);
    $requestHeaders->setHeader('ignoreScm', 'true');
    $body = $this->streamFactory->createStream(json_encode([
      'location' => "vcsData",
      'scanOptions' => 'scanOptions'
    ]));
    $request = new Request("POST", new Uri("HTTP", "localhost"),
      $requestHeaders, [], [], $body);

    $uploadHelper = M::mock('overload:Fossology\UI\Api\Helper\UploadHelper');

    $this->folderDao->shouldReceive('getAllFolderIds')->andReturn([2,3,4]);

    $info = new Info(404, "folderId $folderId does not exists!",
      InfoType::ERROR);
    $expectedResponse = (new ResponseHelper())->withJson($info->getArray(),
      $info->getCode());
    $actualResponse = $this->uploadController->postUpload($request,
      new ResponseHelper(), []);
    $this->assertEquals($expectedResponse->getStatusCode(),
      $actualResponse->getStatusCode());
    $this->assertEquals($this->getResponseJson($expectedResponse),
      $this->getResponseJson($actualResponse));
  }

  /**
   * @runInSeparateProcess
   * @preserveGlobalState disabled
   * @test
   * -# Test for UploadController::postUpload() with internal error
   * -# Check if response status is 500 with error messages set
   */
  public function testPostUploadInternalError()
  {
    $folderId = 3;
    $uploadDescription = "Test Upload";
    $errorMessage = "Failed to insert upload record";
    $errorDesc = "";


    $requestHeaders = new Headers();
    $requestHeaders->setHeader('folderId', $folderId);
    $requestHeaders->setHeader('Content-type', 'application/json');
    $requestHeaders->setHeader('uploadDescription', $uploadDescription);
    $requestHeaders->setHeader('ignoreScm', 'true');
    $body = $this->streamFactory->createStream(json_encode([
      'location' => "vcsData",
      'scanOptions' => 'scanOptions'
    ]));

    $request = new Request("POST", new Uri("HTTP", "localhost"),
      $requestHeaders, [], [], $body);

    $uploadHelper = M::mock('overload:Fossology\UI\Api\Helper\UploadHelper');
    $uploadHelper->shouldReceive('createNewUpload')
      ->withArgs(['vcsData', $folderId, $uploadDescription, 'protected', 'true',
        'vcs', false])
      ->andReturn([false, $errorMessage, $errorDesc, [-1]]);

    $this->folderDao->shouldReceive('getAllFolderIds')->andReturn([2,3,4]);
    $this->folderDao->shouldReceive('isFolderAccessible')
    ->withArgs([$folderId])->andReturn(true);

    $info = new Info(500, $errorMessage . "\n" . $errorDesc, InfoType::ERROR);
    $expectedResponse = (new ResponseHelper())->withJson($info->getArray(),
      $info->getCode());
    $actualResponse = $this->uploadController->postUpload($request,
      new ResponseHelper(), []);
    $this->assertEquals($expectedResponse->getStatusCode(),
      $actualResponse->getStatusCode());
    $this->assertEquals($this->getResponseJson($expectedResponse),
      $this->getResponseJson($actualResponse));
  }

  /**
   * @runInSeparateProcess
   * @preserveGlobalState disabled
   * @test
   * -# Test for UploadController::getUploadLicenses()
   * -# Check if the response status is 200 with correct information
   */
  public function testGetUploadLicenses()
  {
    $uploadId = 3;
    $agentsRun = [
      ['agentName' => 'nomos', 'currentAgentId' => 2, 'isAgentRunning' => false],
      ['agentName' => 'monk', 'currentAgentId' => 3, 'isAgentRunning' => false]
    ];
    $licenseResponse = [
      ['filePath' => 'filea', 'agentFindings' => 'MIT', 'conclusions' => 'MIT'],
      ['filePath' => 'fileb', 'agentFindings' => 'MIT',
        'conclusions' => 'No_license_found']
    ];

    $requestHeaders = new Headers();
    $body = $this->streamFactory->createStream();
    $request = new Request("POST", new Uri("HTTP", "localhost", 80,
        "/uploads/$uploadId/licenses", UploadController::AGENT_PARAM .
        "=nomos,monk&containers=false"),
      $requestHeaders, [], [], $body);

    $this->dbHelper->shouldReceive('doesIdExist')
      ->withArgs(["upload", "upload_pk", $uploadId])->andReturn(true);
    $this->uploadDao->shouldReceive('isAccessible')
      ->withArgs([$uploadId, $this->groupId])->andReturn(true);
    $this->uploadDao->shouldReceive('getParentItemBounds')
      ->withArgs([$uploadId])->andReturn($this->getUploadBounds($uploadId));
    $this->agentDao->shouldReceive('arsTableExists')
      ->withArgs([M::anyOf('nomos', 'monk')])->andReturn(true);

    $scanJobProxy = M::mock('overload:Fossology\Lib\Proxy\ScanJobProxy');
    $scanJobProxy->shouldReceive('createAgentStatus')
      ->withArgs([['nomos', 'monk']])
      ->andReturn($agentsRun);

    $uploadHelper = M::mock('overload:Fossology\UI\Api\Helper\UploadHelper');
    $uploadHelper->shouldReceive('getUploadLicenseList')
      ->withArgs([$uploadId, ['nomos', 'monk'], false, true, false, 0, 50])
      ->andReturn(([[$licenseResponse], 1]));

    $expectedResponse = (new ResponseHelper())->withJson($licenseResponse, 200);

    $actualResponse = $this->uploadController->getUploadLicenses($request,
      new ResponseHelper(), ['id' => $uploadId]);
    $this->assertEquals($expectedResponse->getStatusCode(),
      $actualResponse->getStatusCode());
    $this->assertEquals($this->getResponseJson($expectedResponse),
      $this->getResponseJson($actualResponse)[0]);
    $this->assertEquals('1',
      $actualResponse->getHeaderLine('X-Total-Pages'));
  }

  /**
   * @runInSeparateProcess
   * @preserveGlobalState disabled
   * @test
   * -# Test for UploadController::getUploadLicenses() when agents pending
   * -# Check if response status is 503
   * -# Check if response headers `Retry-After` and `Look-at` set
   */
  public function testGetUploadLicensesPendingScan()
  {
    $uploadId = 3;
    $agentsRun = [
      ['agentName' => 'nomos', 'currentAgentId' => 2, 'isAgentRunning' => true],
      ['agentName' => 'monk', 'currentAgentId' => 3, 'isAgentRunning' => false]
    ];

    $requestHeaders = new Headers();
    $body = $this->streamFactory->createStream();
    $request = new Request("POST", new Uri("HTTP", "localhost", 80,
        "/uploads/$uploadId/licenses", UploadController::AGENT_PARAM .
        "=nomos,monk&containers=false"),
      $requestHeaders, [], [], $body);

    $this->dbHelper->shouldReceive('doesIdExist')
      ->withArgs(["upload", "upload_pk", $uploadId])->andReturn(true);
    $this->uploadDao->shouldReceive('isAccessible')
      ->withArgs([$uploadId, $this->groupId])->andReturn(true);
    $this->uploadDao->shouldReceive('getParentItemBounds')
      ->withArgs([$uploadId])->andReturn($this->getUploadBounds($uploadId));
    $this->agentDao->shouldReceive('arsTableExists')
      ->withArgs([M::anyOf('nomos', 'monk')])->andReturn(true);

    $scanJobProxy = M::mock('overload:Fossology\Lib\Proxy\ScanJobProxy');
    $scanJobProxy->shouldReceive('createAgentStatus')
      ->withArgs([['nomos', 'monk']])
      ->andReturn($agentsRun);

    $info = new Info(503, "Agent nomos is running. " .
      "Please check job status at /api/v1/jobs?upload=" . $uploadId,
      InfoType::INFO);
    $expectedResponse = (new ResponseHelper())->withHeader('Retry-After', '60')
      ->withHeader('Look-at', "/api/v1/jobs?upload=" . $uploadId)
      ->withJson($info->getArray(), $info->getCode());

    $actualResponse = $this->uploadController->getUploadLicenses($request,
      new ResponseHelper(), ['id' => $uploadId]);
    $this->assertEquals($expectedResponse->getStatusCode(),
      $actualResponse->getStatusCode());
    $this->assertEquals($this->getResponseJson($expectedResponse),
      $this->getResponseJson($actualResponse));
    $this->assertEquals($expectedResponse->getHeaders(),
      $actualResponse->getHeaders());
  }

  /**
   * @test
   * -# Test for UploadController::updateUpload()
   * -# Check if response status is 202
   */
  public function testUpdateUpload()
  {
    $upload = 2;
    $assignee = 4;
    $status = UploadStatus::REJECTED;
    $comment = "Not helpful";

    $resource = fopen('data://text/plain;base64,' .
      base64_encode($comment), 'r+');
    $body = $this->streamFactory->createStreamFromResource($resource);
    $request = new Request("POST", new Uri("HTTP", "localhost", 80,
      "/uploads/$upload", UploadController::FILTER_STATUS . "=Rejected&" .
      UploadController::FILTER_ASSIGNEE . "=$assignee"),
      new Headers(), [], [], $body);

    $this->userDao->shouldReceive('isAdvisorOrAdmin')
      ->withArgs([$this->userId, $this->groupId])
      ->andReturn(true);
    $this->userDao->shouldReceive('getUserChoices')
      ->withArgs([$this->groupId])
      ->andReturn([$this->userId => "fossy", $assignee => "friendly-fossy"]);
    $this->dbManager->shouldReceive('getSingleRow')
      ->withArgs([M::any(), [$assignee, $this->groupId, $upload], M::any()]);
    $this->dbManager->shouldReceive('getSingleRow')
      ->withArgs([M::any(), [$status, $comment, $this->groupId, $upload],
        M::any()]);

    $info = new Info(202, "Upload updated successfully.", InfoType::INFO);
    $expectedResponse = (new ResponseHelper())->withJson($info->getArray(),
      $info->getCode());
    $actualResponse = $this->uploadController->updateUpload($request,
      new ResponseHelper(), ['id' => $upload]);
    $this->assertEquals($expectedResponse->getStatusCode(),
      $actualResponse->getStatusCode());
    $this->assertEquals($this->getResponseJson($expectedResponse),
      $this->getResponseJson($actualResponse));
  }

  /**
   * @test
   * -# Test for UploadController::updateUpload() without permission
   * -# Check if response status is 403
   */
  public function testUpdateUploadNoPerm()
  {
    $upload = 2;
    $assignee = 4;
    $comment = "Not helpful";

    $resource = fopen('data://text/plain;base64,' .
      base64_encode($comment), 'r+');
    $body = $this->streamFactory->createStreamFromResource($resource);
    $request = new Request("POST", new Uri("HTTP", "localhost", 80,
      "/uploads/$upload", UploadController::FILTER_STATUS . "=Rejected&" .
      UploadController::FILTER_ASSIGNEE . "=$assignee"),
      new Headers(), [], [], $body);

    $this->userDao->shouldReceive('isAdvisorOrAdmin')
      ->withArgs([$this->userId, $this->groupId])
      ->andReturn(false);

    $info = new Info(403, "Not advisor or admin of current group. " .
      "Can not update upload.", InfoType::ERROR);
    $expectedResponse = (new ResponseHelper())->withJson($info->getArray(),
      $info->getCode());
    $actualResponse = $this->uploadController->updateUpload($request,
      new ResponseHelper(), ['id' => $upload]);
    $this->assertEquals($expectedResponse->getStatusCode(),
      $actualResponse->getStatusCode());
    $this->assertEquals($this->getResponseJson($expectedResponse),
      $this->getResponseJson($actualResponse));
  }

  /**
   * @test
   * -# Test for UploadController::getMainLicenses()
   * -# Check if response status is 200 and RES body matches
   */
  public function testGetMainLicenses()
  {
    $uploadId = 1;
    $licenseIds = array();
    $licenseId = 123;
    $licenseIds[$licenseId] = $licenseId;
    $license = new License($licenseId, "MIT", "MIT License", "risk", "texts", [],
      'type', false);

    $this->dbHelper->shouldReceive('doesIdExist')
      ->withArgs(["upload", "upload_pk", $uploadId])->andReturn(true);
    $this->clearingDao->shouldReceive('getMainLicenseIds')->withArgs([$uploadId, $this->groupId])->andReturn($licenseIds);
    $this->licenseDao->shouldReceive('getLicenseObligations')->withArgs([[$licenseId], false])->andReturn([]);
    $this->licenseDao->shouldReceive('getLicenseObligations')->withArgs([[$licenseId], true])->andReturn([]);
    $this->licenseDao->shouldReceive('getLicenseById')->withArgs([$licenseId])->andReturn($license);

    $licenses[] = $license->getArray();
    $expectedResponse = (new ResponseHelper())->withJson($licenses, 200);
    $actualResponse = $this->uploadController->getMainLicenses(null, new ResponseHelper(), ['id' => $uploadId]);
    $this->assertEquals($expectedResponse->getStatusCode(), $actualResponse->getStatusCode());
    $this->assertEquals($this->getResponseJson($expectedResponse), $this->getResponseJson($actualResponse));
  }


  /**
   * @test
   * -# Test for UploadController::setMainLicense()
   * -# Check if response status is 200 and Res body matches
   */
  public function testSetMainLicense()
  {
    $uploadId = 2;
    $shortName = "MIT";
    $licenseId = 1;
    $rq = [
      "shortName" => $shortName,
    ];
    $license = new License(2, $shortName, "MIT License", "risk", "texts", [],
      'type', 1);
    $licenseIds[$licenseId] = $licenseId;
    $this->dbHelper->shouldReceive('doesIdExist')
      ->withArgs(["upload", "upload_pk", $uploadId])->andReturn(true);
    $this->licenseDao->shouldReceive('getLicenseByShortName')
      ->withArgs([$shortName, $this->groupId])->andReturn($license);
    $this->clearingDao->shouldReceive('getMainLicenseIds')->withArgs([$uploadId, $this->groupId])->andReturn($licenseIds);
    $this->clearingDao->shouldReceive('makeMainLicense')
      ->withArgs([$uploadId, $this->groupId, $license->getId()])->andReturn(null);

    $info = new Info(200, "Successfully added new main license", InfoType::INFO);

    $expectedResponse = (new ResponseHelper())->withJson($info->getArray(), $info->getCode());
    $reqBody = $this->streamFactory->createStream(json_encode(
      $rq
    ));
    $requestHeaders = new Headers();
    $requestHeaders->setHeader('Content-Type', 'application/json');
    $request = new Request("POST", new Uri("HTTP", "localhost"),
      $requestHeaders, [], [], $reqBody);

    $actualResponse = $this->uploadController->setMainLicense($request, new ResponseHelper(), ['id' => $uploadId]);

    $this->assertEquals($expectedResponse->getStatusCode(),
      $actualResponse->getStatusCode());
    $this->assertEquals($this->getResponseJson($expectedResponse),
      $this->getResponseJson($actualResponse));
  }

  /**
   * @test
   * -# Test for UploadController::setMainLicense()
   * -# Check if response status is 400, when the license already Exists and Res body matches
   */
  public function testSetMainLicense_exists()
  {
    $uploadId = 2;
    $shortName = "MIT";
    $licenseId = 1;
    $rq = [
      "shortName" => $shortName,
    ];
    $license = new License($licenseId, $shortName, "MIT License", "risk", "texts", [],
     'type', 1);
    $licenseIds[$licenseId] = $licenseId;
    $this->dbHelper->shouldReceive('doesIdExist')
      ->withArgs(["upload", "upload_pk", $uploadId])->andReturn(true);
    $this->licenseDao->shouldReceive('getLicenseByShortName')
      ->withArgs([$shortName, $this->groupId])->andReturn($license);
    $this->clearingDao->shouldReceive('getMainLicenseIds')->withArgs([$uploadId, $this->groupId])->andReturn($licenseIds);
    $this->clearingDao->shouldReceive('makeMainLicense')
      ->withArgs([$uploadId, $this->groupId, $license->getId()])->andReturn(null);

    $info = new Info(400, 'License already exists for this upload.', InfoType::ERROR);

    $expectedResponse = (new ResponseHelper())->withJson($info->getArray(), $info->getCode());
    $reqBody = $this->streamFactory->createStream(json_encode(
      $rq
    ));
    $requestHeaders = new Headers();
    $requestHeaders->setHeader('Content-Type', 'application/json');
    $request = new Request("POST", new Uri("HTTP", "localhost"),
      $requestHeaders, [], [], $reqBody);

    $actualResponse = $this->uploadController->setMainLicense($request, new ResponseHelper(), ['id' => $uploadId]);

    $this->assertEquals($expectedResponse->getStatusCode(),
      $actualResponse->getStatusCode());
    $this->assertEquals($this->getResponseJson($expectedResponse),
      $this->getResponseJson($actualResponse));
  }


  /**
   * @test
   * -# Test for UploadController::removeMainLicense()
   * -# Check if response status is 200 and the body matches
   */
  public function testRemoveMainLicense()
  {
    $uploadId = 3;
    $licenseId = 1;
    $shortName = "MIT";
    $license = new License($licenseId, $shortName, "MIT License", "risk", "texts", [],
      'type', 1);
    $licenseIds[$licenseId] = $licenseId;

    $this->dbHelper->shouldReceive('doesIdExist')
      ->withArgs(["upload", "upload_pk", $uploadId])->andReturn(true);
    $this->dbHelper->shouldReceive('doesIdExist')
      ->withArgs(["license_ref", "rf_pk", $licenseId])->andReturn(true);
    $this->clearingDao->shouldReceive('getMainLicenseIds')->withArgs([$uploadId, $this->groupId])->andReturn($licenseIds);
    
    $this->clearingDao->shouldReceive('removeMainLicense')->withArgs([$uploadId, $this->groupId, $licenseId])->andReturn(null);
    $this->licenseDao->shouldReceive('getLicenseByShortName')
      ->withArgs([$shortName, $this->groupId])->andReturn($license);

    $info = new Info(200, "Main license removed successfully.", InfoType::INFO);
    $expectedResponse = (new ResponseHelper())->withJson($info->getArray(),
      $info->getCode());
    $actualResponse = $this->uploadController->removeMainLicense(null,
      new ResponseHelper(), ['id' => $uploadId, 'shortName' => $shortName]);
    $this->assertEquals($expectedResponse->getStatusCode(),
      $actualResponse->getStatusCode());
    $this->assertEquals($this->getResponseJson($expectedResponse),
      $this->getResponseJson($actualResponse));
  }
}
