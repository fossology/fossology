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
use Slim\Psr7\Factory\StreamFactory;
use Fossology\Lib\Exceptions\DuplicateTokenKeyException;
use Fossology\Lib\Exceptions\DuplicateTokenNameException;
use Fossology\UI\Api\Exceptions\HttpBadRequestException;
use Fossology\UI\Api\Exceptions\HttpConflictException;
use Fossology\UI\Api\Exceptions\HttpTooManyRequestException;

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
  private $userEditPlugin;
  private $manageTokenPlugin;
  private $streamFactory;

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
    $this->streamFactory = new StreamFactory();
     $this->userEditPlugin  = M::mock('UserEdit');
    $this->manageTokenPlugin = M::mock('ManageToken');
    $this->restHelper->shouldReceive('getPlugin')
        ->withArgs(['user_edit'])->andReturn($this->userEditPlugin);
    $this->restHelper->shouldReceive('getPlugin')
        ->withArgs(['manage-token'])->andReturn($this->manageTokenPlugin);
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
  private function mockV2TokenRequest(array $reqBody)
{
    $request = M::mock(Request::class);

    $request->shouldReceive('getAttribute')
        ->with('apiVersion', 1)
        ->andReturn(ApiVersion::V2);

    $request->shouldReceive('getAttribute')
        ->with('version')
        ->andReturn(ApiVersion::V2);

    $request->shouldReceive('getParsedBody')
        ->andReturn($reqBody);

    $request->shouldReceive('getBody')
        ->andReturn($this->streamFactory->createStream(json_encode($reqBody)));

    $request->shouldReceive('getHeaderLine')
        ->with('Content-Type')
        ->andReturn('application/json');

    return $request;
}
  // ----------------------------------------------------------------
  // createRestApiToken — success
  // ----------------------------------------------------------------

  public function testCreateRestApiTokenSuccess()
  {
    $tokenValue = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.test';
    $reqBody = [
      'tokenName'   => 'myTestToken',
      'tokenScope'  => 'read',
      'tokenExpire' => date('Y-m-d', strtotime('+30 days')),
    ];
    $request = $this->mockV2TokenRequest($reqBody);
    $this->userEditPlugin->shouldReceive('generateNewToken')
      ->once()->andReturn($tokenValue);
    $actualResponse = $this->userController->createRestApiToken(
      $request, new ResponseHelper(), []
    );
    $this->assertEquals(201, $actualResponse->getStatusCode());
    $body = $this->getResponseJson($actualResponse);
    $this->assertArrayHasKey('token', $body);
    $this->assertEquals($tokenValue, $body['token']);
  }

  // ----------------------------------------------------------------
  // createRestApiToken — DuplicateTokenKeyException -> HTTP 429
  // ----------------------------------------------------------------

  public function testCreateRestApiTokenDuplicateKey()
  {
    $reqBody = [
      'tokenName'   => 'duplicateKeyToken',
      'tokenScope'  => 'read',
      'tokenExpire' => date('Y-m-d', strtotime('+30 days')),
    ];
    $request = $this->mockV2TokenRequest($reqBody);
    $this->userEditPlugin->shouldReceive('generateNewToken')
      ->once()->andThrow(new DuplicateTokenKeyException("Duplicate key"));
    $this->expectException(HttpTooManyRequestException::class);
    $this->userController->createRestApiToken(
      $request, new ResponseHelper(), []
    );
  }

  // ----------------------------------------------------------------
  // createRestApiToken — DuplicateTokenNameException -> HTTP 409
  // ----------------------------------------------------------------

  public function testCreateRestApiTokenDuplicateName()
  {
    $reqBody = [
      'tokenName'   => 'duplicateNameToken',
      'tokenScope'  => 'read',
      'tokenExpire' => date('Y-m-d', strtotime('+30 days')),
    ];
    $request = $this->mockV2TokenRequest($reqBody);
    $this->userEditPlugin->shouldReceive('generateNewToken')
      ->once()->andThrow(new DuplicateTokenNameException("Duplicate name"));
    $this->expectException(HttpConflictException::class);
    $this->userController->createRestApiToken(
      $request, new ResponseHelper(), []
    );
  }

  // ----------------------------------------------------------------
  // createRestApiToken — UnexpectedValueException -> HTTP 400
  // ----------------------------------------------------------------

  public function testCreateRestApiTokenInvalidValue()
  {
    $reqBody = [
      'tokenName'   => 'badToken',
      'tokenScope'  => 'read',
      'tokenExpire' => date('Y-m-d', strtotime('+30 days')),
    ];
    $request = $this->mockV2TokenRequest($reqBody);
    $this->userEditPlugin->shouldReceive('generateNewToken')
      ->once()->andThrow(new \UnexpectedValueException("Invalid scope"));
    $this->expectException(HttpBadRequestException::class);
    $this->userController->createRestApiToken(
      $request, new ResponseHelper(), []
    );
  }

  // ----------------------------------------------------------------
  // getTokens — active tokens
  // ----------------------------------------------------------------

  public function testGetTokensActive()
  {
    $tokenId    = '42.abc123';
    $tokenPk    = '42';
    $tokenValue = 'some.jwt.token';
    $tokens = [
      ['id' => $tokenId, 'name' => 'myToken', 'created' => '2026-01-01',
       'expire' => '2026-12-31', 'scope' => 'read'],
    ];
    $request = M::mock(Request::class);
    $request->shouldReceive('getAttribute')->andReturn(ApiVersion::V2);
    $this->userEditPlugin->shouldReceive('getListOfActiveTokens')
      ->once()->andReturn($tokens);
    $this->manageTokenPlugin->shouldReceive('revealToken')
      ->withArgs([$tokenPk])->once()->andReturn(['token' => $tokenValue]);
    $actualResponse = $this->userController->getTokens(
      $request, new ResponseHelper(), ['type' => 'active']
    );
    $this->assertEquals(200, $actualResponse->getStatusCode());
    $body = $this->getResponseJson($actualResponse);
    $this->assertArrayHasKey('activeTokens', $body);
    $this->assertCount(1, $body['activeTokens']);
    $this->assertEquals($tokenValue, $body['activeTokens'][0]['token']);
  }

  // ----------------------------------------------------------------
  // getTokens — expired tokens
  // ----------------------------------------------------------------

  public function testGetTokensExpired()
  {
    $tokenId    = '7.xyz789';
    $tokenPk    = '7';
    $tokenValue = 'expired.jwt.token';
    $tokens = [
      ['id' => $tokenId, 'name' => 'oldToken', 'created' => '2025-01-01',
       'expire' => '2025-06-01', 'scope' => 'write'],
    ];
    $request = M::mock(Request::class);
    $request->shouldReceive('getAttribute')->andReturn(ApiVersion::V2);
    $this->userEditPlugin->shouldReceive('getListOfExpiredTokens')
      ->once()->andReturn($tokens);
    $this->manageTokenPlugin->shouldReceive('revealToken')
      ->withArgs([$tokenPk])->once()->andReturn(['token' => $tokenValue]);
    $actualResponse = $this->userController->getTokens(
      $request, new ResponseHelper(), ['type' => 'expired']
    );
    $this->assertEquals(200, $actualResponse->getStatusCode());
    $body = $this->getResponseJson($actualResponse);
    $this->assertArrayHasKey('expiredTokens', $body);
    $this->assertCount(1, $body['expiredTokens']);
    $this->assertEquals($tokenValue, $body['expiredTokens'][0]['token']);
  }

  // ----------------------------------------------------------------
  // getTokens — invalid type -> HTTP 400
  // ----------------------------------------------------------------

  public function testGetTokensInvalidType()
  {
    $request = M::mock(Request::class);
    $request->shouldReceive('getAttribute')->andReturn(ApiVersion::V2);
    $this->expectException(HttpBadRequestException::class);
    $this->userController->getTokens(
      $request, new ResponseHelper(), ['type' => 'invalid']
    );
  }
}
