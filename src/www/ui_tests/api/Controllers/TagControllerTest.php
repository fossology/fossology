<?php
/*
 SPDX-FileCopyrightText: © 2026 FOSSology contributors

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @file
 * @brief Tests for TagController
 */

namespace Fossology\UI\Api\Test\Controllers;

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\UploadDao;
use Fossology\UI\Api\Controllers\TagController;
use Fossology\UI\Api\Exceptions\HttpBadRequestException;
use Fossology\UI\Api\Exceptions\HttpConflictException;
use Fossology\UI\Api\Exceptions\HttpForbiddenException;
use Fossology\UI\Api\Exceptions\HttpNotFoundException;
use Fossology\UI\Api\Helper\DbHelper;
use Fossology\UI\Api\Helper\ResponseHelper;
use Fossology\UI\Api\Helper\RestHelper;
use Fossology\UI\Api\Models\Info;
use Fossology\UI\Api\Models\InfoType;
use Mockery as M;
use Slim\Psr7\Factory\StreamFactory;
use Slim\Psr7\Headers;
use Slim\Psr7\Request;
use Slim\Psr7\Uri;
use Symfony\Component\HttpFoundation\Response;

/**
 * @class TagControllerTest
 * @brief Unit tests for TagController
 *
 * TagController delegates tag creation and display management to the
 * legacy admin_tag / admin_tag_manage plugins (see admin-tag.php and
 * admin-tag-manage.php), so these tests mock those plugins rather than
 * the database layer directly.
 */
class TagControllerTest extends \PHPUnit\Framework\TestCase
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
   * @var RestHelper $restHelper
   * RestHelper mock
   */
  private $restHelper;

  /**
   * @var UploadDao $uploadDao
   * UploadDao mock
   */
  private $uploadDao;

  /**
   * @var \admin_tag $tagPlugin
   * admin_tag plugin mock
   */
  private $tagPlugin;

  /**
   * @var \admin_tag_manage $tagManagePlugin
   * admin_tag_manage plugin mock
   */
  private $tagManagePlugin;

  /**
   * @var TagController $tagController
   * TagController object under test
   */
  private $tagController;

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
    $this->restHelper = M::mock(RestHelper::class);
    $this->uploadDao = M::mock(UploadDao::class);
    $this->tagPlugin = M::mock('admin_tag');
    $this->tagManagePlugin = M::mock('admin_tag_manage');

    $this->restHelper->shouldReceive('getDbHelper')->andReturn($this->dbHelper);
    $this->restHelper->shouldReceive('getUploadDao')->andReturn($this->uploadDao);
    $this->restHelper->shouldReceive('getGroupId')->andReturn(2);
    $this->restHelper->shouldReceive('getPlugin')
      ->withArgs(["admin_tag"])->andReturn($this->tagPlugin);
    $this->restHelper->shouldReceive('getPlugin')
      ->withArgs(["admin_tag_manage"])->andReturn($this->tagManagePlugin);

    $container->shouldReceive('get')->withArgs(array(
      'helper.restHelper'))->andReturn($this->restHelper);

    $this->tagController = new TagController($container);
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
   * @param Response $response
   * @return array Decoded response
   */
  private function getResponseJson($response)
  {
    $response->getBody()->seek(0);
    return json_decode($response->getBody()->getContents(), true);
  }

  /**
   * Helper to build a JSON request with the given body.
   * @param string $method
   * @param string $path
   * @param array $requestBody
   * @return Request
   */
  private function getJsonRequest($method, $path, $requestBody)
  {
    $requestHeaders = new Headers();
    $requestHeaders->setHeader('Content-Type', 'application/json');
    $body = $this->streamFactory->createStream();
    $body->write(json_encode($requestBody));
    $body->seek(0);
    return new Request($method, new Uri("HTTP", "localhost", 80,
      $path), $requestHeaders, [], [], $body);
  }

  /**
   * @test
   * -# Test for TagController::createTag() to create a new tag
   * -# Check if response is 201 with the id returned by the admin_tag
   *    plugin
   */
  public function testCreateTag()
  {
    $requestBody = [
      "name" => "Software_Repository",
      "description" => "Tag for software repository uploads"
    ];
    $request = $this->getJsonRequest("POST", "/tags", $requestBody);

    $this->tagPlugin->shouldReceive('TagExists')
      ->withArgs([$requestBody["name"]])->andReturn(false);
    $this->tagPlugin->shouldReceive('CreateTag')
      ->withArgs([["tag_name" => $requestBody["name"],
        "tag_desc" => $requestBody["description"]]])
      ->andReturn(null);
    $this->tagPlugin->shouldReceive('GetTagId')
      ->withArgs([$requestBody["name"]])->andReturn(9);

    $info = new Info(201, 9, InfoType::INFO);
    $expectedResponse = (new ResponseHelper())->withJson($info->getArray(),
      $info->getCode());

    $actualResponse = $this->tagController->createTag($request,
      new ResponseHelper(), []);
    $this->assertEquals($expectedResponse->getStatusCode(),
      $actualResponse->getStatusCode());
    $this->assertEquals($this->getResponseJson($expectedResponse),
      $this->getResponseJson($actualResponse));
  }

  /**
   * @test
   * -# Test for TagController::createTag()
   * -# The request body has no name
   * -# Check if response is 400 with the admin_tag plugin's own
   *    validation error surfaced
   */
  public function testCreateTagNoName()
  {
    $requestBody = ["description" => "Missing name"];
    $request = $this->getJsonRequest("POST", "/tags", $requestBody);

    $this->tagPlugin->shouldReceive('CreateTag')
      ->withArgs([["tag_name" => "", "tag_desc" => "Missing name"]])
      ->andReturn("TagName must be specified. Tag Not created.");
    $this->expectException(HttpBadRequestException::class);

    $this->tagController->createTag($request, new ResponseHelper(), []);
  }

  /**
   * @test
   * -# Test for TagController::createTag()
   * -# The request body has a non-string 'description' property
   * -# Check if it is silently treated as empty rather than crashing
   */
  public function testCreateTagDescriptionNotString()
  {
    $requestBody = ["name" => "Valid", "description" => [1, 2]];
    $request = $this->getJsonRequest("POST", "/tags", $requestBody);

    $this->tagPlugin->shouldReceive('TagExists')
      ->withArgs(["Valid"])->andReturn(false);
    $this->tagPlugin->shouldReceive('CreateTag')
      ->withArgs([["tag_name" => "Valid", "tag_desc" => ""]])
      ->andReturn(null);
    $this->tagPlugin->shouldReceive('GetTagId')
      ->withArgs(["Valid"])->andReturn(11);

    $actualResponse = $this->tagController->createTag($request,
      new ResponseHelper(), []);
    $this->assertEquals(201, $actualResponse->getStatusCode());
  }

  /**
   * @test
   * -# Test for TagController::createTag()
   * -# The request body is empty/not valid JSON (parses to null)
   * -# Check if response is 400, not an uncaught TypeError
   */
  public function testCreateTagEmptyBody()
  {
    $request = $this->getJsonRequest("POST", "/tags", null);
    $this->expectException(HttpBadRequestException::class);

    $this->tagController->createTag($request, new ResponseHelper(), []);
  }

  /**
   * @test
   * -# Test for TagController::createTag()
   * -# The request body has a non-string 'name' property
   * -# Check if response is 400, not an uncaught TypeError
   */
  public function testCreateTagNameNotString()
  {
    $requestBody = ["name" => [1, 2]];
    $request = $this->getJsonRequest("POST", "/tags", $requestBody);

    $this->tagPlugin->shouldReceive('CreateTag')
      ->withArgs([["tag_name" => "", "tag_desc" => ""]])
      ->andReturn("TagName must be specified. Tag Not created.");
    $this->expectException(HttpBadRequestException::class);

    $this->tagController->createTag($request, new ResponseHelper(), []);
  }

  /**
   * @test
   * -# Test for TagController::createTag()
   * -# The request body has a name with disallowed characters
   * -# Check if response is 400 with the admin_tag plugin's own
   *    validation error surfaced
   */
  public function testCreateTagInvalidCharacters()
  {
    $requestBody = ["name" => "Bad Tag/Name"];
    $request = $this->getJsonRequest("POST", "/tags", $requestBody);

    $this->tagPlugin->shouldReceive('TagExists')
      ->withArgs(["Bad Tag/Name"])->andReturn(false);
    $this->tagPlugin->shouldReceive('CreateTag')
      ->withArgs([["tag_name" => "Bad Tag/Name", "tag_desc" => ""]])
      ->andReturn("A Tag is only allowed to contain characters from " .
        "<b>A-Za-z0-9_~-!@#$%^*.()</b>. Tag Not created.");
    $this->expectException(HttpBadRequestException::class);

    $this->tagController->createTag($request, new ResponseHelper(), []);
  }

  /**
   * @test
   * -# Test for TagController::createTag()
   * -# The request body has a name longer than the legacy UI's
   *    maxlength hint
   * -# Check that it is still accepted, since admin_tag::CreateTag()
   *    (the single source of truth for tag validation) does not enforce
   *    a length limit
   */
  public function testCreateTagLongNameAllowed()
  {
    $longName = str_repeat("a", 33);
    $requestBody = ["name" => $longName];
    $request = $this->getJsonRequest("POST", "/tags", $requestBody);

    $this->tagPlugin->shouldReceive('TagExists')
      ->withArgs([$longName])->andReturn(false);
    $this->tagPlugin->shouldReceive('CreateTag')
      ->withArgs([["tag_name" => $longName, "tag_desc" => ""]])
      ->andReturn(null);
    $this->tagPlugin->shouldReceive('GetTagId')
      ->withArgs([$longName])->andReturn(12);

    $actualResponse = $this->tagController->createTag($request,
      new ResponseHelper(), []);
    $this->assertEquals(201, $actualResponse->getStatusCode());
  }

  /**
   * @test
   * -# Test for TagController::createTag()
   * -# The request body has additional unknown properties
   * -# Check that they are ignored rather than rejected, matching the
   *    legacy plugin which only ever looks at tag_name/tag_desc
   */
  public function testCreateTagExtraPropertyIgnored()
  {
    $requestBody = ["name" => "Valid", "bogus" => "not allowed"];
    $request = $this->getJsonRequest("POST", "/tags", $requestBody);

    $this->tagPlugin->shouldReceive('TagExists')
      ->withArgs(["Valid"])->andReturn(false);
    $this->tagPlugin->shouldReceive('CreateTag')
      ->withArgs([["tag_name" => "Valid", "tag_desc" => ""]])
      ->andReturn(null);
    $this->tagPlugin->shouldReceive('GetTagId')
      ->withArgs(["Valid"])->andReturn(13);

    $actualResponse = $this->tagController->createTag($request,
      new ResponseHelper(), []);
    $this->assertEquals(201, $actualResponse->getStatusCode());
  }

  /**
   * @test
   * -# Test for TagController::createTag()
   * -# Non admin user cannot create a tag
   * -# Check if response is 403
   */
  public function testCreateTagNoAdmin()
  {
    $requestBody = ["name" => "Valid"];
    $request = $this->getJsonRequest("POST", "/tags", $requestBody);
    $_SESSION[Auth::USER_LEVEL] = Auth::PERM_WRITE;
    $this->expectException(HttpForbiddenException::class);

    $this->tagController->createTag($request, new ResponseHelper(), []);
  }

  /**
   * @test
   * -# Test for TagController::createTag()
   * -# Simulate duplicate tag name
   * -# Check if response is 409
   */
  public function testCreateTagDuplicate()
  {
    $requestBody = ["name" => "Existing"];
    $request = $this->getJsonRequest("POST", "/tags", $requestBody);

    $this->tagPlugin->shouldReceive('TagExists')
      ->withArgs(["Existing"])->andReturn(true);
    $this->expectException(HttpConflictException::class);

    $this->tagController->createTag($request, new ResponseHelper(), []);
  }

  /**
   * @test
   * -# Test for TagController::setTagDisplayStatus() enabling tag
   *    display for an upload
   * -# Check if response is 200 and the admin_tag_manage plugin is
   *    called with 'Enable'
   */
  public function testSetTagDisplayStatusEnable()
  {
    $uploadId = 5;
    $request = $this->getJsonRequest("PUT", "/uploads/$uploadId/tags/display",
      ["enabled" => true]);

    $this->dbHelper->shouldReceive('doesIdExist')
      ->withArgs(["upload", "upload_pk", $uploadId])->andReturn(true);
    $this->uploadDao->shouldReceive('isAccessible')
      ->withArgs([$uploadId, 2])->andReturn(true);
    $this->tagManagePlugin->shouldReceive('ManageTag')
      ->withArgs([null, $uploadId, 'Enable'])->once()->andReturn(1);

    $actualResponse = $this->tagController->setTagDisplayStatus($request,
      new ResponseHelper(), ["id" => $uploadId]);
    $this->assertEquals(200, $actualResponse->getStatusCode());
  }

  /**
   * @test
   * -# Test for TagController::setTagDisplayStatus() disabling tag
   *    display for an upload
   * -# Check if response is 200 and the admin_tag_manage plugin is
   *    called with 'Disable'
   */
  public function testSetTagDisplayStatusDisable()
  {
    $uploadId = 5;
    $request = $this->getJsonRequest("PUT", "/uploads/$uploadId/tags/display",
      ["enabled" => false]);

    $this->dbHelper->shouldReceive('doesIdExist')
      ->withArgs(["upload", "upload_pk", $uploadId])->andReturn(true);
    $this->uploadDao->shouldReceive('isAccessible')
      ->withArgs([$uploadId, 2])->andReturn(true);
    $this->tagManagePlugin->shouldReceive('ManageTag')
      ->withArgs([null, $uploadId, 'Disable'])->once()->andReturn(1);

    $actualResponse = $this->tagController->setTagDisplayStatus($request,
      new ResponseHelper(), ["id" => $uploadId]);
    $this->assertEquals(200, $actualResponse->getStatusCode());
  }

  /**
   * @test
   * -# Test for TagController::setTagDisplayStatus()
   * -# The request body has no 'enabled' property
   * -# Check if response is 400
   */
  public function testSetTagDisplayStatusMissingEnabled()
  {
    $uploadId = 5;
    $request = $this->getJsonRequest("PUT", "/uploads/$uploadId/tags/display",
      []);

    $this->dbHelper->shouldReceive('doesIdExist')
      ->withArgs(["upload", "upload_pk", $uploadId])->andReturn(true);
    $this->uploadDao->shouldReceive('isAccessible')
      ->withArgs([$uploadId, 2])->andReturn(true);
    $this->expectException(HttpBadRequestException::class);

    $this->tagController->setTagDisplayStatus($request, new ResponseHelper(),
      ["id" => $uploadId]);
  }

  /**
   * @test
   * -# Test for TagController::setTagDisplayStatus()
   * -# Non admin user cannot change tag display status
   * -# Check if response is 403
   */
  public function testSetTagDisplayStatusNoAdmin()
  {
    $uploadId = 5;
    $request = $this->getJsonRequest("PUT", "/uploads/$uploadId/tags/display",
      ["enabled" => true]);
    $_SESSION[Auth::USER_LEVEL] = Auth::PERM_WRITE;
    $this->expectException(HttpForbiddenException::class);

    $this->tagController->setTagDisplayStatus($request, new ResponseHelper(),
      ["id" => $uploadId]);
  }

  /**
   * @test
   * -# Test for TagController::setTagDisplayStatus()
   * -# The upload does not exist
   * -# Check if response is 404
   */
  public function testSetTagDisplayStatusUploadNotFound()
  {
    $uploadId = 5;
    $request = $this->getJsonRequest("PUT", "/uploads/$uploadId/tags/display",
      ["enabled" => true]);

    $this->dbHelper->shouldReceive('doesIdExist')
      ->withArgs(["upload", "upload_pk", $uploadId])->andReturn(false);
    $this->expectException(HttpNotFoundException::class);

    $this->tagController->setTagDisplayStatus($request, new ResponseHelper(),
      ["id" => $uploadId]);
  }

  /**
   * @test
   * -# Test for TagController::setTagDisplayStatus()
   * -# The upload is not accessible to the current group
   * -# Check if response is 403
   */
  public function testSetTagDisplayStatusUploadNotAccessible()
  {
    $uploadId = 5;
    $request = $this->getJsonRequest("PUT", "/uploads/$uploadId/tags/display",
      ["enabled" => true]);

    $this->dbHelper->shouldReceive('doesIdExist')
      ->withArgs(["upload", "upload_pk", $uploadId])->andReturn(true);
    $this->uploadDao->shouldReceive('isAccessible')
      ->withArgs([$uploadId, 2])->andReturn(false);
    $this->expectException(HttpForbiddenException::class);

    $this->tagController->setTagDisplayStatus($request, new ResponseHelper(),
      ["id" => $uploadId]);
  }
}
