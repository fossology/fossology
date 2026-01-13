<?php
/*
 SPDX-FileCopyrightText: © 2020 Siemens AG
 Author: Gaurav Mishra <mishra.gaurav@siemens.com>
 SPDX-FileCopyrightText: © 2022 Samuel Dushimimana <dushsam100@gmail.com>
 SPDX-FileContributor: Kaushlendra Pratap <kaushlendra-pratap.singh@siemens.com>

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @file
 * @brief Tests for UploadController
 */

namespace Fossology\UI\Api\Test\Controllers;

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\BusinessRules\ReuseReportProcessor;
use Fossology\Lib\Dao\AgentDao;
use Fossology\Lib\Dao\ClearingDao;
use Fossology\Lib\Dao\FolderDao;
use Fossology\Lib\Dao\LicenseDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Dao\UploadPermissionDao;
use Fossology\Lib\Dao\UserDao;
use Fossology\Lib\Data\AgentRef;
use Fossology\Lib\Data\Tree\ItemTreeBounds;
use Fossology\Lib\Data\UploadStatus;
use Fossology\Lib\Db\DbManager;
use Fossology\UI\Api\Controllers\UploadController;
use Fossology\UI\Api\Exceptions\HttpBadRequestException;
use Fossology\UI\Api\Exceptions\HttpForbiddenException;
use Fossology\UI\Api\Exceptions\HttpInternalServerErrorException;
use Fossology\UI\Api\Exceptions\HttpNotFoundException;
use Fossology\UI\Api\Exceptions\HttpServiceUnavailableException;
use Fossology\UI\Api\Helper\DbHelper;
use Fossology\UI\Api\Helper\ResponseHelper;
use Fossology\UI\Api\Helper\RestHelper;
use Fossology\UI\Api\Models\Agent;
use Fossology\UI\Api\Models\ApiVersion;
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
   * @var UploadPermissionDao $uploadPermissionDao
   * UploadPermissionDao mock
   */
  private $uploadPermissionDao;

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
   * @var ReuseReportProcessor $reuseReportProcess
   * ReuseReportProcessor mock
   */
  private $reuseReportProcess;

  /**
   * @var StreamFactory $streamFactory
   * Stream factory to create body streams.
   */
  private $streamFactory;

  /**
   * @var M\MockInterface $copyrightPlugin
   */
  private $copyrightPlugin;

  /**
   * @var M\MockInterface $downloadPlugin
   */
  private $downloadPlugin;

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
    $this->reuseReportProcess = M::mock(ReuseReportProcessor::class);
    $this->uploadPermissionDao = M::mock(UploadPermissionDao::class);
    $this->downloadPlugin = M::mock("download");

    $this->dbManager->shouldReceive('getSingleRow')
      ->withArgs([M::any(), [$this->groupId, UploadStatus::OPEN,
        Auth::PERM_READ]]);
    $this->dbHelper->shouldReceive('getDbManager')->andReturn($this->dbManager);

    $this->restHelper->shouldReceive('getDbHelper')->andReturn($this->dbHelper);
    $this->restHelper->shouldReceive('getGroupId')->andReturn($this->groupId);
    $this->restHelper->shouldReceive('getUserId')->andReturn($this->userId);
    $this->restHelper->shouldReceive('getPlugin')->withArgs(array("download"))->andReturn($this->downloadPlugin);
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
    $container->shouldReceive('get')->withArgs(array(
      'dao.upload'))->andReturn($this->uploadDao);
    $container->shouldReceive('get')->withArgs(
      ['db.manager'])->andReturn($this->dbManager);
    $container->shouldReceive('get')->withArgs(array('dao.license'))->andReturn($this->licenseDao);
    $container->shouldReceive('get')->withArgs(array(
      'businessrules.reusereportprocessor'))->andReturn($this->reuseReportProcess);
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
   * -# Test for UploadController::getUploads() to fetch single upload when version is V1
   * -# Check if response is 200
   */
  public function testGetSingleUploadV1()
  {
    $this->testGetSingleUpload(ApiVersion::V1);
  }
  /**
   * @test
   * -# Test for UploadController::getUploads() to fetch single upload when version is V2
   * -# Check if response is 200
   */
  public function testGetSingleUploadV2()
  {
    $this->testGetSingleUpload(ApiVersion::V2);
  }

  /**
   * @param $version
   * @return void
   * @throws \Fossology\UI\Api\Exceptions\HttpErrorException
   */
  private function testGetSingleUpload($version)
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
    if ($version == ApiVersion::V2) {
      $request = $request->withAttribute(ApiVersion::ATTRIBUTE_NAME,
        ApiVersion::V2);
    }
    $this->dbHelper->shouldReceive('doesIdExist')
      ->withArgs(["upload", "upload_pk", $uploadId])->andReturn(true);
    $this->uploadDao->shouldReceive('isAccessible')
      ->withArgs([$uploadId, $this->groupId])->andReturn(true);
    $this->uploadDao->shouldReceive('getParentItemBounds')
      ->withArgs([$uploadId])->andReturn($this->getUploadBounds($uploadId));
    $this->dbHelper->shouldReceive('getUploads')
      ->withArgs([$this->userId, $this->groupId, 100, 1, $uploadId, $options,
        true, $version])->andReturn([1, [$upload->getArray()]]);
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
    $this->expectException(HttpForbiddenException::class);

    $this->uploadController->getUploads($request, new ResponseHelper(),
      ['id' => $uploadId]);
  }

  /**
   * @test
   * -# Test for UploadController::getUploads() with filters when version is V1
   * -# Setup various options according to query parameter which will be passed
   *    to DbHelper::getUploads()
   * -# Make sure all mocks are having once() set to force mockery
   */
  public function testGetUploadWithFiltersV1()
  {
    $this->testGetUploadWithFilters(ApiVersion::V1);
  }
  /**
   * @test
   * -# Test for UploadController::getUploads() with filters when version is V2
   * -# Setup various options according to query parameter which will be passed
   *    to DbHelper::getUploads()
   * -# Make sure all mocks are having once() set to force mockery
   */
  public function testGetUploadWithFiltersV2()
  {
    $this->testGetUploadWithFilters(ApiVersion::V2);
  }

  private function testGetUploadWithFilters($version)
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
    if ($version == ApiVersion::V2) {
      $request = $request->withAttribute(ApiVersion::ATTRIBUTE_NAME,
        ApiVersion::V2);
    }
    $this->folderDao->shouldReceive('isFolderAccessible')
      ->withArgs([$folderId, $this->userId])->andReturn(true)->once();
    $this->dbHelper->shouldReceive('getUploads')
      ->withArgs([$this->userId, $this->groupId, 100, 1, null, $folderOptions,
        true, $version])->andReturn([1, []])->once();
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
    if ($version == ApiVersion::V2) {
      $request = $request->withAttribute(ApiVersion::ATTRIBUTE_NAME,
        ApiVersion::V2);
    }
    $this->dbHelper->shouldReceive('getUploads')
      ->withArgs([$this->userId, $this->groupId, 100, 1, null, $nameOptions,
        true, $version])->andReturn([1, []])->once();
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
    if ($version == ApiVersion::V2) {
      $request = $request->withAttribute(ApiVersion::ATTRIBUTE_NAME,
        ApiVersion::V2);
    }
    $this->dbHelper->shouldReceive('getUploads')
      ->withArgs([$this->userId, $this->groupId, 100, 1, null, $statusOptions,
        true, $version])->andReturn([1, []])->once();
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
    if ($version == ApiVersion::V2) {
      $request = $request->withAttribute(ApiVersion::ATTRIBUTE_NAME,
        ApiVersion::V2);
    }
    $this->dbHelper->shouldReceive('getUploads')
      ->withArgs([$this->userId, $this->groupId, 100, 1, null, $assigneeOptions,
        true, $version])->andReturn([1, []])->once();
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
    if ($version == ApiVersion::V2) {
      $request = $request->withAttribute(ApiVersion::ATTRIBUTE_NAME,
        ApiVersion::V2);
    }
    $this->dbHelper->shouldReceive('getUploads')
      ->withArgs([$this->userId, $this->groupId, 100, 1, null, $sinceOptions,
        true, $version])->andReturn([1, []])->once();
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
    if ($version == ApiVersion::V2) {
      $request = $request->withAttribute(ApiVersion::ATTRIBUTE_NAME,
        ApiVersion::V2);
    }
    $this->dbHelper->shouldReceive('getUploads')
      ->withArgs([$this->userId, $this->groupId, 100, 1, null, $combOptions,
        true, $version])->andReturn([1, []])->once();
    $this->uploadController->getUploads($request, new ResponseHelper(), []);
  }

  /**
   * @test
   * -# Test UploadController::getUploads() for all uploads when version is V1
   * -# Check if the response is array with status 200
   */
  public function testGetUploadsV1(){
    $this->testGetUploads(ApiVersion::V1);
  }
  /**
   * @test
   * -# Test UploadController::getUploads() for all uploads when version is V2
   * -# Check if the response is array with status 200
   */
  public function testGetUploadsV2(){
    $this->testGetUploads(ApiVersion::V2);
  }
  private function testGetUploads($version)
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
    if ($version == ApiVersion::V2) {
      $request = $request->withAttribute(ApiVersion::ATTRIBUTE_NAME,
        ApiVersion::V2);
    }
    $this->dbHelper->shouldReceive('getUploads')
      ->withArgs([$this->userId, $this->groupId, 100, 1, null, $options, true, $version])
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
    $this->expectException(HttpServiceUnavailableException::class);

    $this->uploadController->getUploads($request, new ResponseHelper(),
      ['id' => $uploadId]);
  }

  /**
   * @test
   * -# Test for UploadController::moveUpload() for a copy action when version is V1
   * -# Check if response status is 202
   */
  public function testCopyUploadV1()
  {
    $this->testCopyUpload(ApiVersion::V1);
  }
  /**
   * @test
   * -# Test for UploadController::moveUpload() for a copy action when version is V2
   * -# Check if response status is 202
   */
  public function testCopyUploadV2()
  {
    $this->testCopyUpload(ApiVersion::V2);
  }
  /**
   * @param $version version to test
   * @return void
   * -# Test the data format returned by Upload::getArray($version) model
   */
  private function testCopyUpload($version)
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
    $body = $this->streamFactory->createStream();

    if($version==ApiVersion::V1){
      $requestHeaders->setHeader('folderId', $folderId);
      $requestHeaders->setHeader('action', 'copy');
      $request = new Request("PUT", new Uri("HTTP", "localhost"),
        $requestHeaders, [], [], $body);
      $actualResponse = $this->uploadController->moveUpload($request,
        new ResponseHelper(), ['id' => $uploadId]);
    }
    else{
      $request = new Request("PUT", new Uri("HTTP", "localhost"),
        $requestHeaders, [], [], $body);
      if ($version == ApiVersion::V2) {
        $request = $request->withAttribute(ApiVersion::ATTRIBUTE_NAME,
          ApiVersion::V2);
      }
      $actualResponse = $this->uploadController->moveUpload($request->withUri($request->getUri()->withQuery("folderId=$folderId&action=copy")),
        new ResponseHelper(), ['id' => $uploadId]);
    }
    $this->assertEquals($expectedResponse->getStatusCode(),
      $actualResponse->getStatusCode());
    $this->assertEquals($this->getResponseJson($expectedResponse),
      $this->getResponseJson($actualResponse));
  }
  /**
   * getUploadSummary, getLicensesHistogram, setUploadPermissions, getUploadCopyrights,deleteUpload
   */

  /**
   * @test
   * -# Test for UploadController::moveUpload() with invalid folder id with version 1
   * -# Check if response status is 400
   */
  public function testMoveUploadInvalidFolderV1()
  {
    $this->testMoveUploadInvalidFolder(ApiVersion::V1);
  }
  /**
   * @test
   * -# Test for UploadController::moveUpload() with invalid folder id with version 2
   * -# Check if response status is 400
   */
  public function testMoveUploadInvalidFolderV2()
  {
    $this->testMoveUploadInvalidFolder();
  }
  /**
   * @param $version
   * @return void
   */
  private function testMoveUploadInvalidFolder($version = ApiVersion::V2)
  {
    $uploadId = 3;

    $requestHeaders = new Headers();
    $requestHeaders->setHeader('folderId', 'alpha');
    $requestHeaders->setHeader('action', 'move');
    $body = $this->streamFactory->createStream();
    $request = new Request("PATCH", new Uri("HTTP", "localhost"),
      $requestHeaders, [], [], $body);
    if ($version==ApiVersion::V2) {
      $request = $request->withQueryParams(['folderId' => 'alpha', 'action' => 'move']);
    } else {
      $request = $request->withHeader("folderId", "alpha")
        ->withHeader("action", "move");
    }
    $request = $request->withAttribute(ApiVersion::ATTRIBUTE_NAME,$version);
    $this->expectException(HttpBadRequestException::class);

    $this->uploadController->moveUpload($request, new ResponseHelper(),
      ['id' => $uploadId]);
  }

  /**
   * @runInSeparateProcess
   * @preserveGlobalState disabled
   * @test
   * -# Test for UploadController::postUpload() with V1 parameters
   * -# Check if response status is 201 with upload id
   */
  public function testPostUploadV1()
  {
    $this->testPostUpload(ApiVersion::V1);
  }

  /**
   * @runInSeparateProcess
   * @preserveGlobalState disabled
   * @test
   * -# Test for UploadController::postUpload() with V2 parameters
   * -# Check if response status is 201 with upload id
   */
  public function testPostUploadV2()
  {
    $this->testPostUpload(ApiVersion::V2);
  }

  /**
   * @runInSeparateProcess
   * @preserveGlobalState disabled
   * @test
   * -# Test for UploadController::postUpload() with V1 file upload where scanOptions
   *    are provided as an array (multipart/form-data style).
   * -# This should not throw and should schedule analysis.
   */
  public function testPostUploadFileScanOptionsArrayV1()
  {
    $folderId = 2;
    $uploadId = 21;
    $uploadDescription = "Test Upload";
    $scanOptions = [
      "analysis" => [
        "nomos" => true
      ]
    ];

    $requestHeaders = new Headers();
    $requestHeaders->setHeader('Content-Type', 'multipart/form-data');
    $requestHeaders->setHeader('folderId', $folderId);
    $requestHeaders->setHeader('uploadDescription', $uploadDescription);
    $requestHeaders->setHeader('uploadType', 'file');

    $body = $this->streamFactory->createStream();
    $request = new Request("POST", new Uri("HTTP", "localhost"),
      $requestHeaders, [], [], $body);
    $request = $request->withParsedBody([
      "scanOptions" => $scanOptions
    ]);

    $uploadHelper = M::mock('overload:Fossology\UI\Api\Helper\UploadHelper');
    $uploadHelper->shouldReceive('createNewUpload')
      ->withArgs([[], $folderId, $uploadDescription, 'protected', '', 'file',
        false])
      ->andReturn([true, '', '', $uploadId]);

    $info = new Info(201, intval($uploadId), InfoType::INFO);
    $uploadHelper->shouldReceive('handleScheduleAnalysis')
      ->withArgs([$uploadId, $folderId, $scanOptions, true])
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
   * @param int $version Version to test
   * @return void
   */
  private function testPostUpload(int $version)
  {
    $folderId = 2;
    $uploadId = 20;
    $uploadDescription = "Test Upload";
    $scanOptions = [
      "analysis" => [
        "nomos" => true
      ]
    ];

    $requestHeaders = new Headers();
    $requestHeaders->setHeader('Content-Type', 'application/json');
    if ($version == ApiVersion::V2) {
      $reqBody = [
        "location" => "data",
        "folderId" => $folderId,
        "uploadDescription" => $uploadDescription,
        "ignoreScm" => "true",
        "scanOptions" => $scanOptions,
        "uploadType" => "vcs",
        "excludefolder" => false
      ];
    } else {
      $reqBody = [
        "location" => "data",
        "scanOptions" => $scanOptions
      ];
      $requestHeaders->setHeader('folderId', $folderId);
      $requestHeaders->setHeader('uploadDescription', $uploadDescription);
      $requestHeaders->setHeader('ignoreScm', 'true');
      $requestHeaders->setHeader('Content-Type', 'application/json');
      $requestHeaders->setHeader('uploadType', 'vcs');
    }

    $body = $this->streamFactory->createStream(json_encode(
      $reqBody
    ));
    $request = new Request("POST", new Uri("HTTP", "localhost"),
      $requestHeaders, [], [], $body);
    if ($version == ApiVersion::V2) {
      $request = $request->withAttribute(ApiVersion::ATTRIBUTE_NAME,
        ApiVersion::V2);
    }
    $uploadHelper = M::mock('overload:Fossology\UI\Api\Helper\UploadHelper');
    if ($version == ApiVersion::V2) {
      $uploadHelper->shouldReceive('createNewUpload')
        ->withArgs([$reqBody["location"], $folderId, $uploadDescription, 'protected', 'true',
          'vcs', false, false])
        ->andReturn([true, '', '', $uploadId]);
    } else {
      $uploadHelper->shouldReceive('createNewUpload')
        ->withArgs([$reqBody["location"], $folderId, $uploadDescription, 'protected', 'true',
          'vcs', false])
        ->andReturn([true, '', '', $uploadId]);
    }

    $info = new Info(201, intval(20), InfoType::INFO);

    $uploadHelper->shouldReceive('handleScheduleAnalysis')->withArgs([$uploadId,$folderId,$reqBody["scanOptions"],true])
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
   * -# Test for UploadController::postUpload() with inaccessible folder with V1 parameters
   * -# Check if response status is 403
   */
  public function testPostUploadFolderNotAccessibleV1()
  {
    $this->testPostUploadFolderNotAccessible(ApiVersion::V1);
  }

  /**
   * @runInSeparateProcess
   * @preserveGlobalState disabled
   * @test
   * -# Test for UploadController::postUpload() with inaccessible folder with V2 parameters
   * -# Check if response status is 403
   */
  public function testPostUploadFolderNotAccessibleV2()
  {
    $this->testPostUploadFolderNotAccessible(ApiVersion::V2);
  }

  /**
   * @param int $version Version to test
   * @return void
   */
  private function testPostUploadFolderNotAccessible(int $version)
  {
    $folderId = 2;
    $uploadDescription = "Test Upload";
    $scanOptions = [
      "analysis" => [
        "nomos" => true
      ]
    ];

    $requestHeaders = new Headers();
    $requestHeaders->setHeader('Content-type', 'application/json');
    if ($version == ApiVersion::V2) {
      $body = $this->streamFactory->createStream(json_encode([
        "location" => "data",
        "folderId" => $folderId,
        "uploadDescription" => $uploadDescription,
        "ignoreScm" => "true",
        "scanOptions" => $scanOptions,
        "uploadType" => "vcs"
      ]));
    } else {
      $body = $this->streamFactory->createStream(json_encode([
        "location" => "data",
        "scanOptions" => $scanOptions
      ]));
      $requestHeaders->setHeader('folderId', $folderId);
      $requestHeaders->setHeader('uploadDescription', $uploadDescription);
      $requestHeaders->setHeader('ignoreScm', 'true');
      $requestHeaders->setHeader('Content-Type', 'application/json');
      $requestHeaders->setHeader('uploadType', 'vcs');
    }
    $request = new Request("POST", new Uri("HTTP", "localhost"),
      $requestHeaders, [], [], $body);
    if ($version == ApiVersion::V2) {
      $request = $request->withAttribute(ApiVersion::ATTRIBUTE_NAME,
        ApiVersion::V2);
    }

    $uploadHelper = M::mock('overload:Fossology\UI\Api\Helper\UploadHelper');

    $this->folderDao->shouldReceive('getAllFolderIds')->andReturn([2,3,4]);
    $this->folderDao->shouldReceive('isFolderAccessible')
      ->withArgs([$folderId])->andReturn(false);
    $this->expectException(HttpForbiddenException::class);

    $this->uploadController->postUpload($request, new ResponseHelper(),
      []);
  }

  /**
   * @runInSeparateProcess
   * @preserveGlobalState disabled
   * @test
   * -# Test for UploadController::postUpload() with invalid folder id with V1 parameters
   * -# Check if response status is 404
   */
  public function testPostUploadFolderNotFoundV1()
  {
    $this->testPostUploadFolderNotFound(ApiVersion::V1);
  }

  /**
   * @runInSeparateProcess
   * @preserveGlobalState disabled
   * @test
   * -# Test for UploadController::postUpload() with invalid folder id with V2 parameters
   * -# Check if response status is 404
   */
  public function testPostUploadFolderNotFoundV2()
  {
    $this->testPostUploadFolderNotFound(ApiVersion::V2);
  }

  /**
   * @param int $version Version to test
   * @return void
   */
  private function testPostUploadFolderNotFound(int $version)
  {
    $folderId = 8;
    $uploadDescription = "Test Upload";
    $scanOptions = [
      "analysis" => [
        "nomos" => true
      ]
    ];

    $requestHeaders = new Headers();
    $requestHeaders->setHeader('Content-type', 'application/json');
    if ($version == ApiVersion::V2) {
      $body = $this->streamFactory->createStream(json_encode([
        "location" => "vcsData",
        "folderId" => $folderId,
        "uploadDescription" => $uploadDescription,
        "ignoreScm" => "true",
        "scanOptions" => $scanOptions,
        "uploadType" => "vcs"
      ]));
    } else {
      $body = $this->streamFactory->createStream(json_encode([
        "location" => "vcsData",
        "scanOptions" => $scanOptions
      ]));
      $requestHeaders->setHeader('folderId', $folderId);
      $requestHeaders->setHeader('uploadDescription', $uploadDescription);
      $requestHeaders->setHeader('ignoreScm', 'true');
      $requestHeaders->setHeader('Content-Type', 'application/json');
      $requestHeaders->setHeader('uploadType', 'vcs');
    }
    $request = new Request("POST", new Uri("HTTP", "localhost"),
      $requestHeaders, [], [], $body);
    if ($version == ApiVersion::V2) {
      $request = $request->withAttribute(ApiVersion::ATTRIBUTE_NAME,
        ApiVersion::V2);
    }

    $uploadHelper = M::mock('overload:Fossology\UI\Api\Helper\UploadHelper');

    $this->folderDao->shouldReceive('getAllFolderIds')->andReturn([2,3,4]);
    $this->expectException(HttpNotFoundException::class);

    $this->uploadController->postUpload($request, new ResponseHelper(),
      []);
  }

  /**
   * @runInSeparateProcess
   * @preserveGlobalState disabled
   * @test
   * -# Test for UploadController::postUpload() with internal error with V1 parameters
   * -# Check if response status is 500 with error messages set
   */
  public function testPostUploadInternalErrorV1()
  {
    $this->testPostUploadInternalError(ApiVersion::V1);
  }

  /**
   * @runInSeparateProcess
   * @preserveGlobalState disabled
   * @test
   * -# Test for UploadController::postUpload() with internal error with V2 parameters
   * -# Check if response status is 500 with error messages set
   */
  public function testPostUploadInternalErrorV2()
  {
    $this->testPostUploadInternalError(ApiVersion::V2);
  }

  /**
   * @param int $version Version to test
   * @return void
   */
  /**
   * @test
   * -# Test for UploadController::postUpload() with invalid folderId (non-numeric) for V1
   * -# Check if response status is 400
   */
  public function testPostUploadInvalidFolderIdNonNumericV1()
  {
    $this->testPostUploadInvalidFolderIdNonNumeric(ApiVersion::V1);
  }

  /**
   * @test
   * -# Test for UploadController::postUpload() with invalid folderId (non-numeric) for V2
   * -# Check if response status is 400
   */
  public function testPostUploadInvalidFolderIdNonNumericV2()
  {
    $this->testPostUploadInvalidFolderIdNonNumeric(ApiVersion::V2);
  }

  /**
   * @param int $version API version to test
   * @return void
   */
  private function testPostUploadInvalidFolderIdNonNumeric(int $version)
  {
    $folderId = "abc";
    $uploadDescription = "Test Upload";

    $requestHeaders = new Headers();
    $body = $this->streamFactory->createStream();
    $request = new Request("POST", new Uri("HTTP", "localhost"),
      $requestHeaders, [], [], $body);

    if ($version == ApiVersion::V2) {
      $request = $request->withAttribute(ApiVersion::ATTRIBUTE_NAME,
        ApiVersion::V2);
      $request = $request->withParsedBody([
        "folderId" => $folderId,
        "uploadDescription" => $uploadDescription,
        "uploadType" => "file"
      ]);
    } else {
      $requestHeaders->setHeader('folderId', $folderId);
      $requestHeaders->setHeader('uploadDescription', $uploadDescription);
      $requestHeaders->setHeader('uploadType', 'file');
      $request = new Request("POST", new Uri("HTTP", "localhost"),
        $requestHeaders, [], [], $body);
    }

    $this->expectException(HttpBadRequestException::class);
    $this->expectExceptionMessage("folderId must be a positive integer!");

    $this->uploadController->postUpload($request, new ResponseHelper(), []);
  }

  /**
   * @test
   * -# Test for UploadController::postUpload() with invalid folderId (zero) for V1
   * -# Check if response status is 400
   */
  public function testPostUploadInvalidFolderIdZeroV1()
  {
    $this->testPostUploadInvalidFolderIdZero(ApiVersion::V1);
  }

  /**
   * @test
   * -# Test for UploadController::postUpload() with invalid folderId (zero) for V2
   * -# Check if response status is 400
   */
  public function testPostUploadInvalidFolderIdZeroV2()
  {
    $this->testPostUploadInvalidFolderIdZero(ApiVersion::V2);
  }

  /**
   * @param int $version API version to test
   * @return void
   */
  private function testPostUploadInvalidFolderIdZero(int $version)
  {
    $folderId = "0";
    $uploadDescription = "Test Upload";

    $requestHeaders = new Headers();
    $body = $this->streamFactory->createStream();
    $request = new Request("POST", new Uri("HTTP", "localhost"),
      $requestHeaders, [], [], $body);

    if ($version == ApiVersion::V2) {
      $request = $request->withAttribute(ApiVersion::ATTRIBUTE_NAME,
        ApiVersion::V2);
      $request = $request->withParsedBody([
        "folderId" => $folderId,
        "uploadDescription" => $uploadDescription,
        "uploadType" => "file"
      ]);
    } else {
      $requestHeaders->setHeader('folderId', $folderId);
      $requestHeaders->setHeader('uploadDescription', $uploadDescription);
      $requestHeaders->setHeader('uploadType', 'file');
      $request = new Request("POST", new Uri("HTTP", "localhost"),
        $requestHeaders, [], [], $body);
    }

    $this->expectException(HttpBadRequestException::class);
    $this->expectExceptionMessage("folderId must be a positive integer!");

    $this->uploadController->postUpload($request, new ResponseHelper(), []);
  }

  /**
   * @test
   * -# Test for UploadController::postUpload() with invalid folderId (negative) for V1
   * -# Check if response status is 400
   */
  public function testPostUploadInvalidFolderIdNegativeV1()
  {
    $this->testPostUploadInvalidFolderIdNegative(ApiVersion::V1);
  }

  /**
   * @test
   * -# Test for UploadController::postUpload() with invalid folderId (negative) for V2
   * -# Check if response status is 400
   */
  public function testPostUploadInvalidFolderIdNegativeV2()
  {
    $this->testPostUploadInvalidFolderIdNegative(ApiVersion::V2);
  }

  /**
   * @param int $version API version to test
   * @return void
   */
  private function testPostUploadInvalidFolderIdNegative(int $version)
  {
    $folderId = "-1";
    $uploadDescription = "Test Upload";

    $requestHeaders = new Headers();
    $body = $this->streamFactory->createStream();
    $request = new Request("POST", new Uri("HTTP", "localhost"),
      $requestHeaders, [], [], $body);

    if ($version == ApiVersion::V2) {
      $request = $request->withAttribute(ApiVersion::ATTRIBUTE_NAME,
        ApiVersion::V2);
      $request = $request->withParsedBody([
        "folderId" => $folderId,
        "uploadDescription" => $uploadDescription,
        "uploadType" => "file"
      ]);
    } else {
      $requestHeaders->setHeader('folderId', $folderId);
      $requestHeaders->setHeader('uploadDescription', $uploadDescription);
      $requestHeaders->setHeader('uploadType', 'file');
      $request = new Request("POST", new Uri("HTTP", "localhost"),
        $requestHeaders, [], [], $body);
    }

    $this->expectException(HttpBadRequestException::class);
    $this->expectExceptionMessage("folderId must be a positive integer!");

    $this->uploadController->postUpload($request, new ResponseHelper(), []);
  }

  private function testPostUploadInternalError(int $version)
  {
    $folderId = 3;
    $uploadDescription = "Test Upload";
    $errorMessage = "Failed to insert upload record";
    $errorDesc = "";
    $scanOptions = [
      "analysis" => [
        "nomos" => true
      ]
    ];

    $requestHeaders = new Headers();
    $requestHeaders->setHeader('Content-type', 'application/json');
    if ($version == ApiVersion::V2) {
      $body = $this->streamFactory->createStream(json_encode([
        "location" => "vcsData",
        "folderId" => $folderId,
        "uploadDescription" => $uploadDescription,
        "ignoreScm" => "true",
        "scanOptions" => $scanOptions,
        "uploadType" => "vcs",
        "excludefolder" => false
      ]));
    } else {
      $body = $this->streamFactory->createStream(json_encode([
        "location" => "vcsData",
        "scanOptions" => $scanOptions
      ]));
      $requestHeaders->setHeader('folderId', $folderId);
      $requestHeaders->setHeader('uploadDescription', $uploadDescription);
      $requestHeaders->setHeader('ignoreScm', 'true');
      $requestHeaders->setHeader('Content-Type', 'application/json');
      $requestHeaders->setHeader('uploadType', 'vcs');
    }

    $request = new Request("POST", new Uri("HTTP", "localhost"),
      $requestHeaders, [], [], $body);
    if ($version == ApiVersion::V2) {
      $request = $request->withAttribute(ApiVersion::ATTRIBUTE_NAME,
        ApiVersion::V2);
    }

    $uploadHelper = M::mock('overload:Fossology\UI\Api\Helper\UploadHelper');
    if ($version == ApiVersion::V2) {
      $uploadHelper->shouldReceive('createNewUpload')
        ->withArgs(['vcsData', $folderId, $uploadDescription, 'protected', 'true',
          'vcs', false, false])
        ->andReturn([false, $errorMessage, $errorDesc, [-1]]);
    } else {
      $uploadHelper->shouldReceive('createNewUpload')
        ->withArgs(['vcsData', $folderId, $uploadDescription, 'protected', 'true',
          'vcs', false])
        ->andReturn([false, $errorMessage, $errorDesc, [-1]]);
    }

    $this->folderDao->shouldReceive('getAllFolderIds')->andReturn([2,3,4]);
    $this->folderDao->shouldReceive('isFolderAccessible')
      ->withArgs([$folderId])->andReturn(true);
    $this->expectException(HttpInternalServerErrorException::class);

    $this->uploadController->postUpload($request, new ResponseHelper(), []);
  }

  /**
   * @runInSeparateProcess
   * @preserveGlobalState disabled
   * @test
   * -# Test for UploadController::getUploadLicenses() when version is V1
   * -# Check if the response status is 200 with correct information
   */
  public function testGetUploadLicensesV1()
  {
    $this->testGetUploadLicenses(ApiVersion::V1);
  }
  /**
   * @runInSeparateProcess
   * @preserveGlobalState disabled
   * @test
   * -# Test for UploadController::getUploadLicenses() when version is V2
   * -# Check if the response status is 200 with correct information
   */
  public function testGetUploadLicensesV2()
  {
    $this->testGetUploadLicenses(ApiVersion::V2);
  }
  private function testGetUploadLicenses($version)
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
    if ($version == ApiVersion::V2) {
      $request = $request->withAttribute(ApiVersion::ATTRIBUTE_NAME,
        ApiVersion::V2);
    }
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
      ->withArgs([$uploadId, ['nomos', 'monk'], false, true, false, 0, 50, $version])
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
   * -# Test for UploadController::getUploadLicenses() when agents pending with version 1 params
   * -# Check if response status is 503
   * -# Check if response headers `Retry-After` and `Look-at` set
   */
  public function testGetUploadLicensesPendingScanV1()
  {
    $this->testGetUploadLicensesPendingScan(ApiVersion::V1);
  }
  /**
   * @runInSeparateProcess
   * @preserveGlobalState disabled
   * @test
   * -# Test for UploadController::getUploadLicenses() when agents pending with version 2 params
   * -# Check if response status is 503
   * -# Check if response headers `Retry-After` and `Look-at` set
   */
  public function testGetUploadLicensesPendingScanV2()
  {
    $this->testGetUploadLicensesPendingScan();
  }
  /**
   * @param $version to test
   * @return void
   */
  private function testGetUploadLicensesPendingScan($version = ApiVersion::V2)
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
    if ($version == ApiVersion::V2) {
      $request = $request->withQueryParams(['page' => 1, 'limit' => 2, "agent" =>"nomos,monk" ]);
    } else {
      $request = $request->withHeader("limit",2)
        ->withHeader("page",1);
    }
    $request = $request->withAttribute(ApiVersion::ATTRIBUTE_NAME,$version);

    $this->agentDao->shouldReceive("arsTableExists")->withAnyArgs()->andReturn(true);
    $this->agentDao->shouldReceive("getRunningAgentIds")->withAnyArgs()->andReturn([$agentsRun]);
    $this->agentDao->shouldReceive("getSuccessfulAgentEntries")->withAnyArgs()->andReturn([]);
    $scanJobProxy = M::mock('overload:Fossology\Lib\Proxy\ScanJobProxy');
    $this->dbHelper->shouldReceive('doesIdExist')
      ->withArgs(["upload", "upload_pk", $uploadId])->andReturn(true);
    $this->uploadDao->shouldReceive('isAccessible')
      ->withArgs([$uploadId, $this->groupId])->andReturn(true);
    $this->uploadDao->shouldReceive('getParentItemBounds')
      ->withArgs([$uploadId])->andReturn($this->getUploadBounds($uploadId));
    $this->agentDao->shouldReceive('arsTableExists')
      ->withArgs([M::anyOf('nomos', 'monk')])->andReturn(true);

    $scanJobProxy->shouldReceive('createAgentStatus')
      ->withArgs([['nomos', 'monk']])
      ->andReturn($agentsRun);
    $this->expectException(HttpServiceUnavailableException::class);

    $this->uploadController->getUploadLicenses($request, new ResponseHelper(),
      ['id' => $uploadId]);
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
    $this->dbManager->shouldReceive('getSingleRow')
      ->withArgs([M::any(), [$upload], M::any()])
      ->andReturn(["exists" => ""]);

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
    $this->expectException(HttpForbiddenException::class);

    $this->uploadController->updateUpload($request, new ResponseHelper(),
      ['id' => $upload]);
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

    $this->uploadDao->shouldReceive('isAccessible')
      ->withArgs([$uploadId, $this->groupId])->andReturn(true);
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
    $this->uploadDao->shouldReceive('isAccessible')
      ->withArgs([$uploadId, $this->groupId])->andReturn(true);
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
    $this->uploadDao->shouldReceive('isAccessible')
      ->withArgs([$uploadId, $this->groupId])->andReturn(true);
    $this->dbHelper->shouldReceive('doesIdExist')
      ->withArgs(["upload", "upload_pk", $uploadId])->andReturn(true);
    $this->licenseDao->shouldReceive('getLicenseByShortName')
      ->withArgs([$shortName, $this->groupId])->andReturn($license);
    $this->clearingDao->shouldReceive('getMainLicenseIds')->withArgs([$uploadId, $this->groupId])->andReturn($licenseIds);
    $this->clearingDao->shouldReceive('makeMainLicense')
      ->withArgs([$uploadId, $this->groupId, $license->getId()])->andReturn(null);

    $reqBody = $this->streamFactory->createStream(json_encode(
      $rq
    ));
    $requestHeaders = new Headers();
    $requestHeaders->setHeader('Content-Type', 'application/json');
    $request = new Request("POST", new Uri("HTTP", "localhost"),
      $requestHeaders, [], [], $reqBody);
    $this->expectException(HttpBadRequestException::class);

    $this->uploadController->setMainLicense($request, new ResponseHelper(), ['id' => $uploadId]);
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

    $this->uploadDao->shouldReceive('isAccessible')
      ->withArgs([$uploadId, $this->groupId])->andReturn(true);
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

  /**
   * @test
   * -# Test for UploadController::getClearingProgressInfo()
   * -# Check if response status is 200 and the body matches
   */
  public function testGetClearingProgressInfo()
  {
    $uploadId = 3;
    $this->uploadDao->shouldReceive('isAccessible')
      ->withArgs([$uploadId, $this->groupId])->andReturn(true);
    $this->dbHelper->shouldReceive('doesIdExist')
      ->withArgs(["upload", "upload_pk", $uploadId])->andReturn(true);
    $this->uploadDao->shouldReceive("getUploadtreeTableName")->withArgs([$uploadId])->andReturn("uploadtree");
    $this->uploadDao->shouldReceive("getGlobalDecisionSettingsFromInfo")->andReturn(false);
    $this->agentDao->shouldReceive("arsTableExists")->andReturn(true);
    $this->agentDao->shouldReceive("getSuccessfulAgentEntries")->andReturn([['agent_id' => 1, 'agent_rev' => 1]]);
    $this->agentDao->shouldReceive("getCurrentAgentRef")->andReturn(new AgentRef(1, "agent", 1));
    $this->dbManager->shouldReceive("getSingleRow")
      ->withArgs([M::any(), [], 'no_license_uploadtree' . $uploadId])
      ->andReturn(['count' => 1]);
    $this->dbManager->shouldReceive("getSingleRow")
      ->withArgs([M::any(), [], 'already_cleared_uploadtree' . $uploadId])
      ->andReturn(['count' => 0]);
    $this->licenseDao->shouldReceive('getLicenseByShortName')
      ->withArgs(['No_license_found'])->andReturn(null);
    $this->licenseDao->shouldReceive('getLicenseByShortName')
      ->withArgs(['Void'])->andReturn(null);
    $res = [
      "totalFilesOfInterest" => 1,
      "totalFilesCleared" => 1,
    ];
    $expectedResponse = (new ResponseHelper())->withJson($res, 200);
    $actualResponse = $this->uploadController->getClearingProgressInfo(null,
      new ResponseHelper(), ['id' => $uploadId]);
    $this->assertEquals($expectedResponse->getStatusCode(),
      $actualResponse->getStatusCode());
    $this->assertEquals($this->getResponseJson($expectedResponse),
      $this->getResponseJson($actualResponse));
  }
  /**
   * @test
   * -# Test for UploadController::getReuseReportSummary()
   * -# Check if response status is 200 & Response body is correct
   */
  public function testGetReuseReportSummary()
  {
    $uploadId = 2;
    $reuseReportSummary = [
      'declearedLicense' => "",
      'clearedLicense' => "MIT, BSD-3-Clause",
      'usedLicense' => "",
      'unusedLicense' => "",
      'missingLicense' => "MIT, BSD-3-Clause",
    ];
    $this->uploadDao->shouldReceive('isAccessible')
      ->withArgs([$uploadId, $this->groupId])->andReturn(true);
    $this->dbHelper->shouldReceive('doesIdExist')
      ->withArgs(["upload", "upload_pk", $uploadId])->andReturn(true);
    $this->reuseReportProcess->shouldReceive('getReuseSummary')
      ->withArgs([$uploadId])->andReturn($reuseReportSummary);

    $expectedResponse = (new ResponseHelper())->withJson($reuseReportSummary,
      200);
    $actualResponse = $this->uploadController->getReuseReportSummary(
      null, new ResponseHelper(), ['id' => $uploadId]);
    $this->assertEquals($expectedResponse->getStatusCode(),
      $actualResponse->getStatusCode());
    $this->assertEquals($this->getResponseJson($expectedResponse),
      $this->getResponseJson($actualResponse));
  }


  /**
   * @test
   *   -# Test UploadController::setGroupsWithPermissions()
   *   -# Check if the statusCode is 200
   * /
   */

  public function testSetGroupsWithPermissions()
  {
    $publicPerm = 0;
    $uploadId = 2;

    $this->uploadDao->shouldReceive('isAccessible')
      ->withArgs([$uploadId, $this->groupId])->andReturn(true);
    $this->dbHelper->shouldReceive('doesIdExist')
      ->withArgs(["upload", "upload_pk", $uploadId])->andReturn(true);

    $this->restHelper->shouldReceive('getUploadPermissionDao')->andReturn($this->uploadPermissionDao);
    $this->uploadPermissionDao->shouldReceive("getPublicPermission")->withAnyArgs()->andReturn($publicPerm);
    $this->uploadPermissionDao->shouldReceive("getPermissionGroups")->withAnyArgs()->andReturn([]);
    $this->restHelper->shouldReceive("getGroupId")->andReturn($this->groupId);
    $this->restHelper->shouldReceive("getUserId")->andReturn($this->userId);
    $this->restHelper->shouldReceive("getUploadDao")->andReturn($this->uploadDao);

    $body = $this->streamFactory->createStream();
    $requestHeaders = new Headers();
    $request = new Request("PATCH", new Uri("HTTP", "localhost"),
      $requestHeaders, [], [], $body);
    $response = new ResponseHelper();
    $actualResponse = $this->uploadController->getGroupsWithPermissions($request,$response,["id"=>$this->groupId]);
    $this->assertEquals(200,$actualResponse->getStatusCode());

  }

  /**
   * @test
   *   -# Test UploadController::setGroupsWithPermissions()
   *   -# Check if  the HttpNotFoundException is thrown
   * /
   */
  public function testSetGroupsWithPermissionsNotFound()
  {
    $uploadId = 2;
    $this->dbHelper->shouldReceive('doesIdExist')->withAnyArgs()->andReturn(false);

    $this->dbHelper->shouldReceive('doesIdExist')
      ->withArgs(["upload", "upload_pk", $uploadId])->andReturn(false);
    $body = $this->streamFactory->createStream();
    $requestHeaders = new Headers();
    $request = new Request("PATCH", new Uri("HTTP", "localhost"),
      $requestHeaders, [], [], $body);
    $response = new ResponseHelper();
    $this->expectException(HttpNotFoundException::class);
    $this->uploadController->getGroupsWithPermissions($request,$response,["id"=>$this->groupId]);

  }

  /**
   * @test
   *   -# Test UploadController::setGroupsWithPermissions()
   *   -# Check if the HttpForbiddenException is thrown
   * /
   */
  public function testSetGroupsWithPermissionsUploadNotAccessible()
  {
    $this->dbHelper->shouldReceive('doesIdExist')->withAnyArgs()->andReturn(true);
    $this->uploadDao->shouldReceive('isAccessible')->withAnyArgs()->andReturn(false);

    $body = $this->streamFactory->createStream();
    $requestHeaders = new Headers();
    $request = new Request("PATCH", new Uri("HTTP", "localhost"),
      $requestHeaders, [], [], $body);
    $response = new ResponseHelper();
    $this->expectException(HttpForbiddenException::class);
    $this->uploadController->getGroupsWithPermissions($request,$response,["id"=>$this->groupId]);

  }

  /**
   * @runInSeparateProcess
   * @preserveGlobalState disabled
   */
  public function testGetAllAgents()
  {
    $groupId = 2;
    $uploadId = 3;
    $agentsRun = [
      ["uploadId" => $uploadId, 'agentName' => 'nomos', 'successfulAgents'=> [], 'currentAgentId' => 2, 'isAgentRunning' => false],
      ["uploadId" => $uploadId,'agentName' => 'monk',  "successfulAgents" => [], 'currentAgentId' => 3, 'isAgentRunning' => false]
    ];

    $this->restHelper->shouldReceive("getGroupId")->andReturn($groupId);
    $this->agentDao->shouldReceive("getCurrentAgentRef")->withAnyArgs()->andReturn(new AgentRef($uploadId,"momoa",45));

    $this->uploadDao->shouldReceive("isAccessible")->withAnyArgs()->andReturn(true);
    $this->dbHelper->shouldReceive('doesIdExist')->withAnyArgs()->andReturn(true);
    $scanJobProxy = M::mock('overload:Fossology\Lib\Proxy\ScanJobProxy');
    $scanJobProxy->shouldReceive('createAgentStatus')
      ->withAnyArgs()
      ->andReturn($agentsRun);
    $this->agentDao->shouldReceive("arsTableExists")->withAnyArgs()->andReturn(true);
    $this->agentDao->shouldReceive("getRunningAgentIds")->withAnyArgs()->andReturn([$agentsRun]);
    $this->agentDao->shouldReceive("getSuccessfulAgentEntries")->withAnyArgs()->andReturn([]);

    $body = $this->streamFactory->createStream();
    $requestHeaders = new Headers();
    $request = new Request("POST", new Uri("HTTP", "localhost"),
      $requestHeaders, [], [], $body);

    $actualResponse = $this->uploadController->getAllAgents($request, new ResponseHelper(),["id"=>$uploadId]);
    $this->assertEquals(200,$actualResponse->getStatusCode());

  }
  /**
   * @test
   *   -# Test UploadController::getAllAgentsUpload()
   *   -# Check if the HttpNotFoundException is thrown
   * /
   */
  public function testGetAllAgentsUploadNotFound()
  {
    $uploadId = 3;
    $this->dbHelper->shouldReceive('doesIdExist')->withAnyArgs()->andReturn(false);
    $this->dbHelper->shouldReceive('doesIdExist')
      ->withArgs(["upload", "upload_pk", $uploadId])->andReturn(false);

    $this->expectException(HttpNotFoundException::class);
    $this->uploadController->getAllAgents(null, new ResponseHelper(),["id"=>$uploadId]);

  }
  /**
   * @test
   *   -# Test UploadController::getAllAgentsUpload()
   *   -# Check if the HttpForbiddenException is thrown
   * /
   */
  public function testGetAllAgentsUploadNotAccessible()
  {
    $uploadId = 3;

    $this->uploadDao->shouldReceive('isAccessible')
      ->withArgs([$uploadId, $this->groupId])->andReturn(false);
    $this->dbHelper->shouldReceive('doesIdExist')
      ->withArgs(["upload", "upload_pk", $uploadId])->andReturn(true);

    $this->expectException(HttpForbiddenException::class);
    $this->uploadController->getAllAgents(null, new ResponseHelper(),["id"=>$uploadId]);

  }

  /**
   * @test
   *   -# Test UploadController::getEditedLicense()
   *   -# Check if  the statusCode is 200.
   * /
   */
  public function testGetEditedLicenses()
  {
    $groupId = 2;
    $uploadId = 3;
    $uploadName = "Testing name";
    $this->uploadDao->shouldReceive('getParentItemBounds')
      ->withAnyArgs()->andReturn($this->getUploadBounds($uploadId));
    $this->uploadDao->shouldReceive("isAccessible")->withAnyArgs()->andReturn(true);
    $this->dbHelper->shouldReceive('doesIdExist')->withAnyArgs()->andReturn(true);
    $this->restHelper->shouldReceive('getUploadDao')->andReturn($this->uploadDao);
    $this->uploadDao->shouldReceive("getUploadtreeTableName")->withArgs([$uploadId])->andReturn($uploadName);
    $this->clearingDao->shouldReceive("getClearedLicenseIdAndMultiplicities")->withAnyArgs()->andReturn([]);

    $actualResponse = $this->uploadController->getEditedLicenses(null,new ResponseHelper(),["id"=>$uploadId]);
    $this->assertEquals(200,$actualResponse->getStatusCode());
  }

  /**
   * @test
   *   -# Test UploadController::getEditedLicense()
   *   -# Check if HttpNotFoundException is thrown.
   * /
   */
  public function testGetEditedLicensesNotFound()
  {
    $groupId = 2;
    $uploadId = 3;
    $this->uploadDao->shouldReceive('getParentItemBounds')
      ->withAnyArgs()->andReturn($this->getUploadBounds($uploadId));
    $this->uploadDao->shouldReceive("isAccessible")->withAnyArgs()->andReturn(true);
    $this->dbHelper->shouldReceive('doesIdExist')->withAnyArgs()->andReturn(false);

    $this->expectException(HttpNotFoundException::class);
    $this->uploadController->getEditedLicenses(null,new ResponseHelper(),["id"=>$groupId]);
  }

  /**
   * @test
   *   -# Test UploadController::getEditedLicense()
   *   -# Check if HttpForbiddenException is thrown.
   * /
   */
  public function testGetEditedLicensesForbidden()
  {
    $groupId = 2;
    $uploadId = 3;
    $this->uploadDao->shouldReceive('getParentItemBounds')
      ->withAnyArgs()->andReturn($this->getUploadBounds($uploadId));
    $this->uploadDao->shouldReceive("isAccessible")->withAnyArgs()->andReturn(false);
    $this->dbHelper->shouldReceive('doesIdExist')->withAnyArgs()->andReturn(true);

    $this->expectException(HttpForbiddenException::class);
    $this->uploadController->getEditedLicenses(null,new ResponseHelper(),["id"=>$groupId]);
  }

  /**
   * @test
   *   -# Test UploadController::getScannedLicense()
   *   -# Check if HttpNotFoundException is thrown.
   * /
   */
  public function testGetScannedLicensesNotFound()
  {
    $uploadId = 3;
    $this->uploadDao->shouldReceive('isAccessible')
      ->withArgs([$uploadId, $this->groupId])->andReturn(true);
    $this->dbHelper->shouldReceive('doesIdExist')
      ->withArgs(["upload", "upload_pk", $uploadId])->andReturn(false);

    $body = $this->streamFactory->createStream();
    $requestHeaders = new Headers();
    $request = new Request("GET", new Uri("HTTP", "localhost"),
      $requestHeaders, [], [], $body);

    $this->expectException(HttpNotFoundException::class);
    $this->uploadController->getScannedLicenses($request,new ResponseHelper(),["id"=>$uploadId]);
  }

  /**
   * @test
   *   -# Test UploadController::agentsRevision()
   *   -# Check if is the statusCode is 200.
   * @runInSeparateProcess
   * @preserveGlobalState disabled
   */
  public function testAgentsRevision()
  {
    $uploadId = 3;
    $agentsRun = [
      ["uploadId" => $uploadId, 'agentName' => 'nomos', 'successfulAgents'=> [], 'currentAgentId' => 2, 'isAgentRunning' => false],
      ["uploadId" => $uploadId,'agentName' => 'monk',  "successfulAgents" => [], 'currentAgentId' => 3, 'isAgentRunning' => false]
    ];
    $agent = new Agent([],$uploadId,"MOMO agent",45,"4.4.0.37.072417",false,"");

    $this->uploadDao->shouldReceive('isAccessible')
      ->withArgs([$uploadId, $this->groupId])->andReturn(true);
    $this->dbHelper->shouldReceive('doesIdExist')
      ->withArgs(["upload", "upload_pk", $uploadId])->andReturn(true);
    $scanJobProxy = M::mock('overload:Fossology\Lib\Proxy\ScanJobProxy');

    $scanJobProxy->shouldReceive('createAgentStatus')
      ->withAnyArgs()
      ->andReturn($agentsRun);
    $scanJobProxy->shouldReceive("getSuccessfulAgents")->andReturn($agent);
    $this->agentDao->shouldReceive("arsTableExists")->withAnyArgs()->andReturn(true);
    $this->agentDao->shouldReceive("getSuccessfulAgentEntries")->withAnyArgs()->andReturn([]);
    $this->agentDao->shouldReceive("getRunningAgentIds")->withAnyArgs()->andReturn([$agent]);

    $actualResponse = $this->uploadController->getAgentsRevision(null,new ResponseHelper(),["id"=>$uploadId]);
    $this->assertEquals(200,$actualResponse->getStatusCode());
  }

  /**
   * @test
   *   -# Test UploadController::agentsRevision()
   *   -# Check if HttpNotFoundException is thrown.
   * /
   */
  public function testAgentsRevisionNotFound()
  {
    $uploadId = 3;

    $this->dbHelper->shouldReceive('doesIdExist')
      ->withArgs(["upload", "upload_pk", $uploadId])->andReturn(false);

    $this->expectException(HttpNotFoundException::class);
    $this->uploadController->getAgentsRevision(null,new ResponseHelper(),["id"=>$uploadId]);
  }

  /**
   * @test
   *   -# Test UploadController::agentsRevision()
   *   -# Check if HttpForbiddenException  is thrown.
   * /
   */
  public function testAgentsRevisionForbidden()
  {
    $uploadId = 3;

    $this->uploadDao->shouldReceive('isAccessible')
      ->withArgs([$uploadId, $this->groupId])->andReturn(false);
    $this->dbHelper->shouldReceive('doesIdExist')
      ->withArgs(["upload", "upload_pk", $uploadId])->andReturn(true);

    $this->expectException(HttpForbiddenException::class);
    $this->uploadController->getAgentsRevision(null,new ResponseHelper(),["id"=>$uploadId]);
  }

  /**
   * @test
   *   -# Test UploadController::getTopItem()
   *   -# Check if response status is 404
   * /
   */
  public function testGetTopItem()
  {
    $uploadId = 2;

    $this->dbHelper->shouldReceive('doesIdExist')
      ->withArgs(["upload", "upload_pk", $uploadId])->andReturn(true);
    $this->uploadDao->shouldReceive('getParentItemBounds')
      ->withAnyArgs()->andReturn($this->getUploadBounds($uploadId));
    $this->uploadDao->shouldReceive("getUploadtreeTableName")->withArgs([$uploadId])->andReturn("uploadtree");

    $actualResponse = $this->uploadController->getTopItem(null,new ResponseHelper(),["id"=>$uploadId]);
    $itemTreeBounds = $this->getUploadBounds($uploadId);
    $info = new Info(200, $itemTreeBounds->getItemId(), InfoType::INFO);
    $expectedResponse = (new ResponseHelper())->withJson($info->getArray(), $info->getCode());

    $this->assertEquals($expectedResponse->getStatusCode(),$actualResponse->getStatusCode());

  }

  /**
   * @test
   *   -# Test UploadController::getTopItem()
   *   -# Check if response status is 404
   * /
   */
  public function testGetTopItemUploadNotFound()
  {
    $uploadId = 2;

    $this->dbHelper->shouldReceive('doesIdExist')
      ->withArgs(["upload", "upload_pk", $uploadId])->andReturn(false);

    $actualResponse = $this->uploadController->getTopItem(null,new ResponseHelper(),["id"=>$uploadId]);
    $info = new Info(404, "Upload does not exist", InfoType::ERROR);
    $expectedResponse = (new ResponseHelper())->withJson($info->getArray(), $info->getCode());
    $this->assertEquals($expectedResponse->getStatusCode(), $actualResponse->getStatusCode());

  }


  /**
   * @test
   *   -# Test UploadController::getTopItem()
   *   -# Check if response status is 500
   * /
   */

  public function testGetTopItemInternalServerError()
  {
    $uploadId = 12;

    $this->dbHelper->shouldReceive('doesIdExist')
      ->withArgs(["upload", "upload_pk", $uploadId])->andReturn(true);
    $this->uploadDao->shouldReceive('getParentItemBounds')
      ->withAnyArgs()->andReturn($this->getUploadBounds($uploadId));
    $this->uploadDao->shouldReceive("getUploadtreeTableName")->withArgs([$uploadId])->andReturn("uploadtree");

    $actualResponse = $this->uploadController->getTopItem(null,new ResponseHelper(),["id"=>$uploadId]);


    $this->assertEquals(500,$actualResponse->getStatusCode());

  }
}
