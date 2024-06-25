<?php
/*
 * SPDX-FileCopyrightText: © 2022 Samuel Dushimimana <dushsam100@gmail.com>
 *
 * SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @file
 * @brief Tests for GroupController
 */

namespace Fossology\UI\Api\Test\Controllers;

require_once dirname(__DIR__, 4) . '/lib/php/Plugin/FO_Plugin.php';


use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\UserDao;
use Fossology\Lib\Db\DbManager;
use Fossology\UI\Api\Controllers\GroupController;
use Fossology\UI\Api\Exceptions\HttpBadRequestException;
use Fossology\UI\Api\Exceptions\HttpForbiddenException;
use Fossology\UI\Api\Helper\DbHelper;
use Fossology\UI\Api\Helper\ResponseHelper;
use Fossology\UI\Api\Helper\RestHelper;
use Fossology\UI\Api\Models\Info;
use Fossology\UI\Api\Models\InfoType;
use Fossology\UI\Api\Models\ApiVersion;
use Fossology\UI\Api\Models\User;
use Fossology\UI\Api\Models\UserGroupMember;
use Mockery as M;
use Slim\Psr7\Factory\StreamFactory;
use Slim\Psr7\Headers;
use Slim\Psr7\Request;
use Slim\Psr7\Uri;


/**
 * @class GroupControllerTest
 * @brief Tests for GroupController
 */
class GroupControllerTest extends \PHPUnit\Framework\TestCase
{

  /**
   * @var string YAML_LOC
   * Location of openapi.yaml file
   */
  const YAML_LOC = __DIR__ . '/../../../ui/api/documentation/openapi.yaml';

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
   * @var M\MockInterface $adminPlugin
   * AdminGroupUsersPlugin mock
   */
  private $adminPlugin;

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
    $this->adminPlugin = M::mock('AdminGroupUsers');

    $this->restHelper->shouldReceive('getDbHelper')->andReturn($this->dbHelper);
    $this->restHelper->shouldReceive('getUserDao')
      ->andReturn($this->userDao);

    $this->restHelper->shouldReceive('getPlugin')
      ->withArgs(array('group_manage_users'))->andReturn($this->adminPlugin);

    $container->shouldReceive('get')->withArgs(array(
      'helper.restHelper'))->andReturn($this->restHelper);
    $this->groupController = new GroupController($container);
    $this->assertCountBefore = \Hamcrest\MatcherAssert::getCount();
    $this->dbManager = M::mock(DbManager::class);
    $this->dbHelper->shouldReceive('getDbManager')->andReturn($this->dbManager);
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
   * Generate array of group-members
   * @param array $userIds User ids to be generated
   * @return array[]
   */
  private function getGroupMembers($userIds)
  {
    $groupPermissions = array("NONE" => -1, UserDao::USER => 0,
      UserDao::ADMIN => 1, UserDao::ADVISOR => 2);

    $memberList = array();
    foreach ($userIds as $userId) {
      $key = array_rand($groupPermissions);
      $userGroupMember =  new UserGroupMember(new User($userId, "user$userId", "User $userId",
        null, null, null, null, null),$groupPermissions[$key]) ;
      $memberList[] = $userGroupMember->getArray();
    }
    return $memberList;
  }

  /**
   * Generate array of users-with-group
   * @param array $userIds User ids to be generated
   * @return array[]
   */
  private function getUsersWithGroup($userIds)
  {
    $groupPermissions = array("NONE" => -1, UserDao::USER => 0,
      UserDao::ADMIN => 1, UserDao::ADVISOR => 2);

    $usersWithGroup = array();
    foreach ($userIds as $userId) {
      $perm = array_rand($groupPermissions);
      $user = [
        "user_pk"   => $userId,
        "group_perm"=> $perm,
        "user_name" => $userId."username",
        "user_desc" => $userId."desc",
        "user_status"=> 'active'
      ];
      $usersWithGroup[] = $user;
    }
    return $usersWithGroup;
  }



  /**
   * @test
   * -# Test GroupController::deleteGroup() for valid delete request
   * -# Check if response status is 202
   */
  public function testDeleteGroup()
  {
    $groupName = 'fossy';
    $groupId = 4;
    $userId = 1;
    $request = M::mock(Request::class);
    $request->shouldReceive('getAttribute')->andReturn(ApiVersion::V1);
    $this->restHelper->getUserDao()->shouldReceive('getGroupIdByName')->withArgs([$groupName])->andReturn($groupId);
    $this->restHelper->shouldReceive('getUserId')->andReturn($userId);
    $this->dbHelper->shouldReceive('doesIdExist')
      ->withArgs(["groups", "group_pk", $groupId])->andReturn(true);
    $_SESSION[Auth::USER_LEVEL] = Auth::PERM_WRITE;
    $this->userDao->shouldReceive('getDeletableAdminGroupMap')->withArgs([$userId,$_SESSION[Auth::USER_LEVEL]]);
    $this->userDao->shouldReceive('deleteGroup')->withArgs([$groupId]);

    $info = new Info(202, "User Group will be deleted", InfoType::INFO);
    $expectedResponse = (new ResponseHelper())->withJson($info->getArray(),
      $info->getCode());
    $actualResponse = $this->groupController->deleteGroup($request, new ResponseHelper(),
      ['pathParam' => $groupId]);

    $this->assertEquals($expectedResponse->getStatusCode(),
      $actualResponse->getStatusCode());
    $this->assertEquals($this->getResponseJson($expectedResponse),
      $this->getResponseJson($actualResponse));
  }

  /**
   * @test
   * -# Test GroupController::getDeletableGroups()
   * -# Check if the response is a list of groups.
   */
  public function testGetDeletableGroups()
  {
    $userId = 2;
    $groupList = array();
    $this->restHelper->shouldReceive('getUserId')->andReturn($userId);
    $_SESSION[Auth::USER_LEVEL] = Auth::PERM_WRITE;
    $this->userDao->shouldReceive('getDeletableAdminGroupMap')->withArgs([$userId,
      $_SESSION[Auth::USER_LEVEL]])->andReturn([]);
    $expectedResponse = (new ResponseHelper())->withJson($groupList, 200);
    $actualResponse = $this->groupController->getDeletableGroups(null, new ResponseHelper(), []);
    $this->assertEquals($expectedResponse->getStatusCode(), $actualResponse->getStatusCode());
    $this->assertEquals($this->getResponseJson($expectedResponse), $this->getResponseJson($actualResponse));
  }
    /**
   * @test
   * -# Test GroupController::getGroupMembers() for all groups
   * -# Check if the response is list of group members
   */
  public function testGetGroupMembers()
  {
    $userIds = [2];
    $groupName = 'fossy';
    $groupId = 1;
    $memberList = $this->getGroupMembers($userIds);
    $request = M::mock(Request::class);
    $request->shouldReceive('getAttribute')->andReturn(ApiVersion::V1);
    $this->restHelper->getUserDao()->shouldReceive('getGroupIdByName')->withArgs([$groupName])->andReturn($groupId);
    $this->restHelper->shouldReceive('getUserId')->andReturn($userIds[0]);
    $_SESSION[Auth::USER_LEVEL] = Auth::PERM_WRITE;
    $this->userDao->shouldReceive('getAdminGroupMap')->withArgs([$userIds[0],$_SESSION[Auth::USER_LEVEL]])->andReturn([1]);

    $this->dbManager->shouldReceive('prepare')->withArgs([M::any(),M::any()]);
    $this->dbManager->shouldReceive('execute')->withArgs([M::any(),array($groupId)])->andReturn(1);
    $this->dbManager->shouldReceive('fetchAll')->withArgs([1])->andReturn($this->getUsersWithGroup($userIds));
    $this->dbManager->shouldReceive('freeResult')->withArgs([1]);

    $user = $this->getUsersWithGroup($userIds)[0];
    $users = [];
    $users[] = new User($user["user_pk"], $user["user_name"], $user["user_desc"],
      null, null, null, null, null);
    $this->dbHelper->shouldReceive("getUsers")->withArgs([$user['user_pk']])->andReturn($users);

    $expectedResponse = (new ResponseHelper())->withJson($memberList, 200);

    $actualResponse = $this->groupController->getGroupMembers($request, new ResponseHelper(), ['pathParam' => $groupId]);
    $this->assertEquals($expectedResponse->getStatusCode(),$actualResponse->getStatusCode());
  }


 /**
   * @test
   * -# Test GroupController::addMember()
   * -# The user already a member
   * -# Test if the response body matches
   * -# Test if the response status is 200
   *
   */
  public function testAddMemberUserNotMember()
  {
    $groupName = "fossy";
    $userName = "user";
    $groupId = 1;
    $newuser = 1;
    $userArray = ['user_pk' => $newuser];
    $newPerm = 2;
    $emptyArr=[];
    $userId = 1;
    $_SESSION[Auth::USER_LEVEL] = Auth::PERM_ADMIN;
    $this->restHelper->getUserDao()->shouldReceive('getGroupIdByName')->withArgs([$groupName])->andReturn($groupId);
    $this->restHelper->getUserDao()->shouldReceive('getUserByName')->withArgs([$userName])->andReturn($userArray);
    $this->dbHelper->shouldReceive('doesIdExist')->withArgs(["groups", "group_pk", $groupId])->andReturn(true);
    $this->dbHelper->shouldReceive('doesIdExist')->withArgs(["users","user_pk",$newuser])->andReturn(true);
    $this->dbManager->shouldReceive('getSingleRow')->withArgs([M::any(),M::any(),M::any()])->andReturn($emptyArr);
    $this->restHelper->shouldReceive('getUserId')->andReturn($userId);
    $this->userDao->shouldReceive('isAdvisorOrAdmin')->withArgs([$userId, $groupId])->andReturn(true);

    $this->dbManager->shouldReceive('prepare')->withArgs([M::any(),M::any()]);
    $this->dbManager->shouldReceive('execute')->withArgs([M::any(),array($groupId, $newuser,$newPerm)])->andReturn(1);
    $this->dbManager->shouldReceive('freeResult')->withArgs([1]);


    $body = $this->streamFactory->createStream(json_encode([
      "perm" => $newPerm
    ]));
    $requestHeaders = new Headers();
    $requestHeaders->setHeader('Content-Type', 'application/json');
    $request = new Request("POST", new Uri("HTTP", "localhost"),
      $requestHeaders, [], [], $body);

    $expectedResponse =  new Info(200, "User will be added to group.", InfoType::INFO);

    $actualResponse = $this->groupController->addMember($request, new ResponseHelper(), ['pathParam' => $groupId,'userPathParam' => $newuser]);
    $this->assertEquals($expectedResponse->getCode(),$actualResponse->getStatusCode());
    $this->assertEquals($expectedResponse->getArray(),$this->getResponseJson($actualResponse));
  }

  /**
   * @test
   * -# Test GroupController::addMember()
   * -# The user is not an admin
   * -# Test if the response status is 403
   */
  public function testAddMemberUserNotAdmin()
  {
    $groupName = "fossy";
    $userName = "user";
    $groupId = 1;
    $newuser = 1;
    $userArray = ['user_pk' => $newuser];
    $newPerm = 2;
    $userId = 1;

    $_SESSION[Auth::USER_LEVEL] = Auth::PERM_WRITE;
    $this->restHelper->getUserDao()->shouldReceive('getGroupIdByName')->withArgs([$groupName])->andReturn($groupId);
    $this->restHelper->getUserDao()->shouldReceive('getUserByName')->withArgs([$userName])->andReturn($userArray);
    $this->dbHelper->shouldReceive('doesIdExist')->withArgs(["groups", "group_pk", $groupId])->andReturn(true);
    $this->dbHelper->shouldReceive('doesIdExist')->withArgs(["users","user_pk",$newuser])->andReturn(true);
    $this->restHelper->shouldReceive('getUserId')->andReturn($userId);
    $this->userDao->shouldReceive('isAdvisorOrAdmin')->withArgs([$userId, $groupId])->andReturn(false);

    $body = $this->streamFactory->createStream(json_encode([
      "perm" => $newPerm
    ]));
    $requestHeaders = new Headers();
    $requestHeaders->setHeader('Content-Type', 'application/json');
    $request = new Request("POST", new Uri("HTTP", "localhost"),
      $requestHeaders, [], [], $body);

    $this->expectException(HttpForbiddenException::class);

    $this->groupController->addMember($request, new ResponseHelper(),
    ['pathParam' => $groupId,'userPathParam' => $newuser]);
  }

  /**
   * @test
   * -# Test GroupController::addMember()
   * -# The user is not an admin but group admin
   * -# Test if the response status is 200
   */
  public function testAddMemberUserGroupAdmin()
  {
    $groupName = "fossy";
    $userName = "user";
    $groupId = 1;
    $newuser = 1;
    $userArray = ['user_pk' => $newuser];
    $newPerm = 2;
    $emptyArr=[];
    $userId = 1;

    $_SESSION[Auth::USER_LEVEL] = Auth::PERM_WRITE;
    $this->restHelper->getUserDao()->shouldReceive('getGroupIdByName')->withArgs([$groupName])->andReturn($groupId);
    $this->restHelper->getUserDao()->shouldReceive('getUserByName')->withArgs([$userName])->andReturn($userArray);
    $this->dbHelper->shouldReceive('doesIdExist')->withArgs(["groups", "group_pk", $groupId])->andReturn(true);
    $this->dbHelper->shouldReceive('doesIdExist')->withArgs(["users","user_pk",$newuser])->andReturn(true);
    $this->dbManager->shouldReceive('getSingleRow')->withArgs([M::any(),M::any(),M::any()])->andReturn($emptyArr);
    $this->restHelper->shouldReceive('getUserId')->andReturn($userId);
    $this->userDao->shouldReceive('isAdvisorOrAdmin')->withArgs([$userId, $groupId])->andReturn(true);

    $this->dbManager->shouldReceive('prepare')->withArgs([M::any(),M::any()]);
    $this->dbManager->shouldReceive('execute')->withArgs([M::any(),array($groupId, $newuser,$newPerm)])->andReturn(1);
    $this->dbManager->shouldReceive('freeResult')->withArgs([1]);

    $body = $this->streamFactory->createStream(json_encode([
      "perm" => $newPerm
    ]));
    $requestHeaders = new Headers();
    $requestHeaders->setHeader('Content-Type', 'application/json');
    $request = new Request("POST", new Uri("HTTP", "localhost"),
      $requestHeaders, [], [], $body);

    $expectedResponse =  new Info(200, "User will be added to group.", InfoType::INFO);

    $actualResponse = $this->groupController->addMember($request, new ResponseHelper(), ['pathParam' => $groupId,'userPathParam' => $newuser]);
    $this->assertEquals($expectedResponse->getCode(),$actualResponse->getStatusCode());
    $this->assertEquals($expectedResponse->getArray(),$this->getResponseJson($actualResponse));
  }


  /**
   * @test
   * -# Test GroupController::addMember()
   * -# The user already a member
   * -# Test if the response body matches
   * -# Test if the response status is 400
   *
   */
  public function testAddMemberUserAlreadyMember()
  {
    $groupName = "fossy";
    $userName = "user";
    $groupId = 1;
    $newuser = 1;
    $userArray = ['user_pk' => $newuser];
    $newPerm = 2;
    $userId = 1;

    $_SESSION[Auth::USER_LEVEL] = Auth::PERM_ADMIN;
    $this->restHelper->getUserDao()->shouldReceive('getGroupIdByName')->withArgs([$groupName])->andReturn($groupId);
    $this->restHelper->getUserDao()->shouldReceive('getUserByName')->withArgs([$userName])->andReturn($userArray);
    $this->dbHelper->shouldReceive('doesIdExist')->withArgs(["groups", "group_pk", $groupId])->andReturn(true);
    $this->dbHelper->shouldReceive('doesIdExist')->withArgs(["users","user_pk",$newuser])->andReturn(true);
    $this->dbManager->shouldReceive('getSingleRow')->withArgs([M::any(),M::any(),M::any()])->andReturn(true);
    $this->restHelper->shouldReceive('getUserId')->andReturn($userId);
    $this->userDao->shouldReceive('isAdvisorOrAdmin')->withArgs([$userId, $groupId])->andReturn(true);

    $body = $this->streamFactory->createStream(json_encode([
      "perm" => $newPerm
    ]));
    $requestHeaders = new Headers();
    $requestHeaders->setHeader('Content-Type', 'application/json');
    $request = new Request("POST", new Uri("HTTP", "localhost"),
      $requestHeaders, [], [], $body);

    $this->expectException(HttpBadRequestException::class);

    $this->groupController->addMember($request, new ResponseHelper(),
    ['pathParam' => $groupId,'userPathParam' => $newuser]);
  }
      /**
   * @test
   * -# Test GroupController::getGroupMembers() for all groups
   * -# Check if the response is list of group members
   */
  public function testChangeUserPermission()
  {
    $groupIds = [1,2,3,4,5,6];
    $groupName = "fossy";
    $userName = "user";
    $userId = 1;
    $group_user_member_pk = 1;
    $newPerm = 2;
    $userPk = 1;
    $userArray = ['user_pk' => $userId];

    $_SESSION[Auth::USER_LEVEL] = Auth::PERM_ADMIN;
    $this->restHelper->getUserDao()->shouldReceive('getGroupIdByName')->withArgs([$groupName])->andReturn($groupIds[0]);
    $this->restHelper->getUserDao()->shouldReceive('getUserByName')->withArgs([$userName])->andReturn($userArray);
    $this->dbHelper->shouldReceive('doesIdExist')->withArgs(["groups", "group_pk", $groupIds[0]])->andReturn(true);
    $this->dbHelper->shouldReceive('doesIdExist')->withArgs(["users","user_pk",$userId])->andReturn(true);
    $this->dbManager->shouldReceive('getSingleRow')->withArgs([M::any(),M::any(),M::any()])->andReturn(['group_pk'=>$groupIds[0],'group_user_member_pk'=>$group_user_member_pk,'permission'=>$newPerm]);
    $this->restHelper->shouldReceive('getUserId')->andReturn($userPk);
    $this->userDao->shouldReceive('isAdvisorOrAdmin')->withArgs([$userPk, $groupIds[0]])->andReturn(true);

    $this->adminPlugin->shouldReceive('updateGUMPermission')->withArgs([$group_user_member_pk,$newPerm, $this->dbManager ]);

    $body = $this->streamFactory->createStream(json_encode([
      "perm" => $newPerm
    ]));
    $requestHeaders = new Headers();
    $requestHeaders->setHeader('Content-Type', 'application/json');
    $request = new Request("POST", new Uri("HTTP", "localhost"),
      $requestHeaders, [], [], $body);

    $_SESSION[Auth::USER_LEVEL] = Auth::PERM_WRITE;
    $expectedResponse = new Info(202, "Permission updated successfully.", InfoType::INFO);

    $actualResponse = $this->groupController->changeUserPermission($request, new ResponseHelper(), ['pathParam' => $groupIds[0],'userPathParam' => $userId]);
    $this->assertEquals($expectedResponse->getCode(),$actualResponse->getStatusCode());
    $this->assertEquals($expectedResponse->getArray(),$this->getResponseJson($actualResponse));
  }
}
