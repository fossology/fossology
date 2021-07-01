<?php
/***************************************************************
 * Copyright (C) 2020 Siemens AG
 * Author: Gaurav Mishra <mishra.gaurav@siemens.com>
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 ***************************************************************/
/**
 * @file
 * @brief Tests for UploadController
 */

namespace Fossology\UI\Api\Test\Controllers;

use Mockery as M;
use Fossology\UI\Api\Controllers\UploadController;
use Fossology\UI\Api\Helper\DbHelper;
use Fossology\Lib\Db\DbManager;
use Fossology\UI\Api\Helper\RestHelper;
use Fossology\Lib\Data\UploadStatus;
use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Data\Tree\ItemTreeBounds;
use Fossology\UI\Api\Models\Upload;
use Slim\Http\Response;
use Fossology\UI\Api\Models\Info;
use Fossology\UI\Api\Models\InfoType;
use Fossology\DelAgent\UI\DeleteResponse;
use Fossology\DelAgent\UI\DeleteMessages;
use Slim\Http\Request;
use Slim\Http\Headers;
use Slim\Http\Body;
use Slim\Http\Uri;
use Fossology\Lib\Dao\FolderDao;
use Fossology\Lib\Dao\AgentDao;
use Fossology\UI\Api\Models\Hash;

function TryToDelete($uploadpk, $user_pk, $group_pk, $uploadDao)
{
  return UploadControllerTest::$function->TryToDelete($uploadpk, $user_pk,
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
   * @brief Setup test objects
   * @see PHPUnit_Framework_TestCase::setUp()
   */
  protected function setUp()
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

    $container->shouldReceive('get')->withArgs(array(
      'helper.restHelper'))->andReturn($this->restHelper);
    $container->shouldReceive('get')->withArgs(array(
      'dao.agent'))->andReturn($this->agentDao);
    $this->uploadController = new UploadController($container);
    $this->assertCountBefore = \Hamcrest\MatcherAssert::getCount();
  }

  /**
   * @brief Remove test objects
   * @see PHPUnit_Framework_TestCase::tearDown()
   */
  protected function tearDown()
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
    $upload = $this->getUpload($uploadId);
    $requestHeaders = new Headers();
    $body = new Body(fopen('php://temp', 'r+'));
    $request = new Request("GET", new Uri("HTTP", "localhost", 80,
      "/uploads/$uploadId"), $requestHeaders, [], [], $body);
    $this->dbHelper->shouldReceive('doesIdExist')
      ->withArgs(["upload", "upload_pk", $uploadId])->andReturn(true);
    $this->uploadDao->shouldReceive('isAccessible')
      ->withArgs([$uploadId, $this->groupId])->andReturn(true);
    $this->uploadDao->shouldReceive('getParentItemBounds')
      ->withArgs([$uploadId])->andReturn($this->getUploadBounds($uploadId));
    $this->dbHelper->shouldReceive('getUploads')
      ->withArgs([$this->userId, $this->groupId, 100, 1, $uploadId, null, true])
      ->andReturn([1, [$upload->getArray()]]);
    $expectedResponse = (new Response())->withJson($upload->getArray(), 200);
    $actualResponse = $this->uploadController->getUploads($request,
      new Response(), ['id' => $uploadId]);
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
    $body = new Body(fopen('php://temp', 'r+'));
    $request = new Request("GET", new Uri("HTTP", "localhost", 80,
      "/uploads/$uploadId"), $requestHeaders, [], [], $body);
    $this->dbHelper->shouldReceive('doesIdExist')
      ->withArgs(["upload", "upload_pk", $uploadId])->andReturn(true);
    $this->uploadDao->shouldReceive('isAccessible')
      ->withArgs([$uploadId, $this->groupId])->andReturn(false);
    $expectedResponse = new Info(403, "Upload is not accessible",
      InfoType::ERROR);
    $actualResponse = $this->uploadController->getUploads($request,
      new Response(), ['id' => $uploadId]);
    $this->assertEquals($expectedResponse->getCode(),
      $actualResponse->getStatusCode());
    $this->assertEquals($expectedResponse->getArray(),
      $this->getResponseJson($actualResponse));
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
    $requestHeaders = new Headers();
    $body = new Body(fopen('php://temp', 'r+'));
    $request = new Request("GET", new Uri("HTTP", "localhost", 80,
      "/uploads/"), $requestHeaders, [], [], $body);
    $this->dbHelper->shouldReceive('getUploads')
      ->withArgs([$this->userId, $this->groupId, 100, 1, null, null, true])
      ->andReturn([1, $uploads]);
    $expectedResponse = (new Response())->withJson($uploads, 200);
    $actualResponse = $this->uploadController->getUploads($request,
      new Response(), []);
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
    $body = new Body(fopen('php://temp', 'r+'));
    $request = new Request("GET", new Uri("HTTP", "localhost", 80,
      "/uploads/"), $requestHeaders, [], [], $body);
    $this->dbHelper->shouldReceive('doesIdExist')
      ->withArgs(["upload", "upload_pk", $uploadId])->andReturn(true);
    $this->uploadDao->shouldReceive('isAccessible')
      ->withArgs([$uploadId, $this->groupId])->andReturn(true);
    $this->uploadDao->shouldReceive('getParentItemBounds')
      ->withArgs([$uploadId])->andReturn(false);

    $expectedResponse = new Response();
    $returnVal = new Info(503,
      "Ununpack job not started. Please check job status at " .
      "/api/v1/jobs?upload=" . $uploadId, InfoType::INFO);
    $expectedResponse = $expectedResponse->withHeader('Retry-After', '60')
      ->withHeader('Look-at', "/api/v1/jobs?upload=" . $uploadId)
      ->withJson($returnVal->getArray(), $returnVal->getCode());
    $actualResponse = $this->uploadController->getUploads($request,
      new Response(), ['id' => $uploadId]);
    $this->assertEquals($expectedResponse->getStatusCode(),
      $actualResponse->getStatusCode());
    $this->assertEquals($this->getResponseJson($expectedResponse),
      $this->getResponseJson($actualResponse));
    $this->assertEquals($expectedResponse->getHeaders(),
      $actualResponse->getHeaders());
  }

  /**
   * @test
   * -# Test for UploadController::copyUpload()
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
    $expectedResponse = (new Response())->withJson($info->getArray(),
      $info->getCode());

    $requestHeaders = new Headers();
    $requestHeaders->set('folderId', $folderId);
    $body = new Body(fopen('php://temp', 'r+'));
    $request = new Request("PUT", new Uri("HTTP", "localhost"),
      $requestHeaders, [], [], $body);
    $actualResponse = $this->uploadController->copyUpload($request,
      new Response(), ['id' => $uploadId]);
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

    $expectedResponse = (new Response())->withJson($info->getArray(),
      $info->getCode());

    $requestHeaders = new Headers();
    $requestHeaders->set('folderId', 'alpha');
    $body = new Body(fopen('php://temp', 'r+'));
    $request = new Request("PATCH", new Uri("HTTP", "localhost"),
      $requestHeaders, [], [], $body);

    $actualResponse = $this->uploadController->moveUpload($request,
      new Response(), ['id' => $uploadId]);

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
    $uploadDescription = "Test Upload";

    $requestHeaders = new Headers();
    $requestHeaders->set('folderId', $folderId);
    $requestHeaders->set('uploadDescription', $uploadDescription);
    $requestHeaders->set('ignoreScm', true);
    $body = new Body(fopen('php://temp', 'r+'));
    $request = new Request("POST", new Uri("HTTP", "localhost"),
      $requestHeaders, [], [], $body);

    $uploadHelper = M::mock('overload:Fossology\UI\Api\Helper\UploadHelper');
    $uploadHelper->shouldReceive('createNewUpload')
      ->withArgs([$request, $folderId, $uploadDescription, 'protected', true,
        'vcs'])
      ->andReturn([true, '', '', 20]);

    $this->folderDao->shouldReceive('getAllFolderIds')->andReturn([2,3,4]);
    $this->folderDao->shouldReceive('isFolderAccessible')
      ->withArgs([$folderId])->andReturn(true);

    $info = new Info(201, intval(20), InfoType::INFO);
    $expectedResponse = (new Response())->withJson($info->getArray(),
      $info->getCode());
    $actualResponse = $this->uploadController->postUpload($request,
      new Response(), []);
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
    $requestHeaders->set('folderId', $folderId);
    $requestHeaders->set('uploadDescription', $uploadDescription);
    $requestHeaders->set('ignoreScm', true);
    $body = new Body(fopen('php://temp', 'r+'));
    $request = new Request("POST", new Uri("HTTP", "localhost"),
      $requestHeaders, [], [], $body);

    $uploadHelper = M::mock('overload:Fossology\UI\Api\Helper\UploadHelper');

    $this->folderDao->shouldReceive('getAllFolderIds')->andReturn([2,3,4]);
    $this->folderDao->shouldReceive('isFolderAccessible')
      ->withArgs([$folderId])->andReturn(false);

    $info = new Info(403, "folderId $folderId is not accessible!",
      InfoType::ERROR);
    $expectedResponse = (new Response())->withJson($info->getArray(),
      $info->getCode());
    $actualResponse = $this->uploadController->postUpload($request,
      new Response(), []);
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
    $requestHeaders->set('folderId', $folderId);
    $requestHeaders->set('uploadDescription', $uploadDescription);
    $requestHeaders->set('ignoreScm', true);
    $body = new Body(fopen('php://temp', 'r+'));
    $request = new Request("POST", new Uri("HTTP", "localhost"),
      $requestHeaders, [], [], $body);

    $uploadHelper = M::mock('overload:Fossology\UI\Api\Helper\UploadHelper');

    $this->folderDao->shouldReceive('getAllFolderIds')->andReturn([2,3,4]);

    $info = new Info(404, "folderId $folderId does not exists!",
      InfoType::ERROR);
    $expectedResponse = (new Response())->withJson($info->getArray(),
      $info->getCode());
    $actualResponse = $this->uploadController->postUpload($request,
      new Response(), []);
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
    $requestHeaders->set('folderId', $folderId);
    $requestHeaders->set('uploadDescription', $uploadDescription);
    $requestHeaders->set('ignoreScm', true);
    $body = new Body(fopen('php://temp', 'r+'));
    $request = new Request("POST", new Uri("HTTP", "localhost"),
      $requestHeaders, [], [], $body);

    $uploadHelper = M::mock('overload:Fossology\UI\Api\Helper\UploadHelper');
    $uploadHelper->shouldReceive('createNewUpload')
      ->withArgs([$request, $folderId, $uploadDescription, 'protected', true,
        'vcs'])
      ->andReturn([false, $errorMessage, $errorDesc, -1]);

    $this->folderDao->shouldReceive('getAllFolderIds')->andReturn([2,3,4]);
    $this->folderDao->shouldReceive('isFolderAccessible')
    ->withArgs([$folderId])->andReturn(true);

    $info = new Info(500, $errorMessage . "\n" . $errorDesc, InfoType::ERROR);
    $expectedResponse = (new Response())->withJson($info->getArray(),
      $info->getCode());
    $actualResponse = $this->uploadController->postUpload($request,
      new Response(), []);
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
    $body = new Body(fopen('php://temp', 'r+'));
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
      ->withArgs([$uploadId, ['nomos', 'monk'], false])
      ->andReturn($licenseResponse);

    $expectedResponse = (new Response())->withJson($licenseResponse, 200);

    $actualResponse = $this->uploadController->getUploadLicenses($request,
      new Response(), ['id' => $uploadId]);
    $this->assertEquals($expectedResponse->getStatusCode(),
      $actualResponse->getStatusCode());
    $this->assertEquals($this->getResponseJson($expectedResponse),
      $this->getResponseJson($actualResponse));
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
    $body = new Body(fopen('php://temp', 'r+'));
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
    $expectedResponse = (new Response())->withHeader('Retry-After', '60')
      ->withHeader('Look-at', "/api/v1/jobs?upload=" . $uploadId)
      ->withJson($info->getArray(), $info->getCode());

    $actualResponse = $this->uploadController->getUploadLicenses($request,
      new Response(), ['id' => $uploadId]);
    $this->assertEquals($expectedResponse->getStatusCode(),
      $actualResponse->getStatusCode());
    $this->assertEquals($this->getResponseJson($expectedResponse),
      $this->getResponseJson($actualResponse));
    $this->assertEquals($expectedResponse->getHeaders(),
      $actualResponse->getHeaders());
  }
}
