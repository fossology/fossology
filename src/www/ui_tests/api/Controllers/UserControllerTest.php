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

require_once dirname(dirname(dirname(dirname(__DIR__)))) .
  '/lib/php/Plugin/FO_Plugin.php';

use Mockery as M;
use Fossology\UI\Api\Controllers\UserController;
use Fossology\UI\Api\Helper\DbHelper;
use Fossology\UI\Api\Helper\RestHelper;
use Fossology\UI\Api\Models\User;
use Fossology\UI\Api\Models\Info;
use Fossology\UI\Api\Models\InfoType;
use Fossology\UI\Api\Helper\ResponseHelper;
use Fossology\Lib\Dao\UserDao;

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
        "user$userId@example.com", $accessLevel, 2, 4, "", 2);
      $userArray[] = $user->getArray();
    }
    return $userArray;
  }

  /**
   * @test
   * -# Test UserController::getUsers() for specific user id
   * -# Check if response contains only one user info
   */
  public function testGetSpecificUser()
  {
    $userId = 2;
    $user = $this->getUsers([$userId]);
    $this->dbHelper->shouldReceive('doesIdExist')
      ->withArgs(["users", "user_pk", $userId])->andReturn(true);
    $this->dbHelper->shouldReceive('getUsers')->withArgs([$userId])
      ->andReturn($user);
    $expectedResponse = (new ResponseHelper())->withJson($user[0], 200);
    $actualResponse = $this->userController->getUsers(null, new ResponseHelper(),
      ['id' => $userId]);
    $this->assertEquals($expectedResponse->getStatusCode(),
      $actualResponse->getStatusCode());
    $this->assertEquals($this->getResponseJson($expectedResponse),
      $this->getResponseJson($actualResponse));
  }

  /**
   * @test
   * -# Test UserController::getUsers() for invalid user id
   * -# Check if response status is 404
   */
  public function testGetSpecificUserNotFound()
  {
    $userId = 6;
    $this->dbHelper->shouldReceive('doesIdExist')
      ->withArgs(["users", "user_pk", $userId])->andReturn(false);
    $error = new Info(404, "UserId doesn't exist", InfoType::ERROR);
    $expectedResponse = (new ResponseHelper())->withJson($error->getArray(),
      $error->getCode());
    $actualResponse = $this->userController->getUsers(null, new ResponseHelper(),
      ['id' => $userId]);
    $this->assertEquals($expectedResponse->getStatusCode(),
      $actualResponse->getStatusCode());
    $this->assertEquals($this->getResponseJson($expectedResponse),
      $this->getResponseJson($actualResponse));
  }

  /**
   * @test
   * -# Test UserController::getUsers() for all users
   * -# Check if the response is list of user info
   */
  public function testGetAllUsers()
  {
    $users = $this->getUsers([2, 3, 4]);
    $this->dbHelper->shouldReceive('getUsers')->withArgs([null])
      ->andReturn($users);
    $expectedResponse = (new ResponseHelper())->withJson($users, 200);
    $actualResponse = $this->userController->getUsers(null, new ResponseHelper(), []);
    $this->assertEquals($expectedResponse->getStatusCode(),
      $actualResponse->getStatusCode());
    $this->assertEquals($this->getResponseJson($expectedResponse),
      $this->getResponseJson($actualResponse));
  }

  /**
   * @test
   * -# Test UserController::deleteUser() for valid delete request
   * -# Check if response status is 202
   */
  public function testDeleteUser()
  {
    $userId = 4;
    $this->dbHelper->shouldReceive('doesIdExist')
      ->withArgs(["users", "user_pk", $userId])->andReturn(true);
    $this->dbHelper->shouldReceive('deleteUser')->withArgs([$userId]);
    $info = new Info(202, "User will be deleted", InfoType::INFO);
    $expectedResponse = (new ResponseHelper())->withJson($info->getArray(),
      $info->getCode());
    $actualResponse = $this->userController->deleteUser(null, new ResponseHelper(),
      ['id' => $userId]);
    $this->assertEquals($expectedResponse->getStatusCode(),
      $actualResponse->getStatusCode());
    $this->assertEquals($this->getResponseJson($expectedResponse),
      $this->getResponseJson($actualResponse));
  }

  /**
   * @test
   * -# Test UserController::deleteUser() for invalid user id
   * -# Check if response status is 404
   */
  public function testDeleteUserDoesNotExists()
  {
    $userId = 8;
    $this->dbHelper->shouldReceive('doesIdExist')
      ->withArgs(["users", "user_pk", $userId])->andReturn(false);
    $info = new Info(404, "UserId doesn't exist", InfoType::ERROR);
    $expectedResponse = (new ResponseHelper())->withJson($info->getArray(),
      $info->getCode());
    $actualResponse = $this->userController->deleteUser(null, new ResponseHelper(),
      ['id' => $userId]);
    $this->assertEquals($expectedResponse->getStatusCode(),
      $actualResponse->getStatusCode());
    $this->assertEquals($this->getResponseJson($expectedResponse),
      $this->getResponseJson($actualResponse));
  }

  /**
   * @test
   * -# Test UserController::getCurrentUser()
   * -# Check if response contains current user's info
   */
  public function testGetCurrentUser()
  {
    $userId = 2;
    $user = $this->getUsers([$userId]);
    $user[0]["default_group"] = "fossy";
    $this->restHelper->shouldReceive('getUserId')->andReturn($userId);
    $this->dbHelper->shouldReceive('getUsers')->withArgs([$userId])
      ->andReturn($user);
    $expectedResponse = (new ResponseHelper())->withJson($user[0], 200);
    $this->userDao->shouldReceive('getUserAndDefaultGroupByUserName')->withArgs([$user[0]["name"]])
      ->andReturn(["group_name" => "fossy"]);
    $actualResponse = $this->userController->getCurrentUser(null,
      new ResponseHelper(), []);
    $this->assertEquals($expectedResponse->getStatusCode(),
      $actualResponse->getStatusCode());
    $this->assertEquals($this->getResponseJson($expectedResponse),
      $this->getResponseJson($actualResponse));
  }
}
