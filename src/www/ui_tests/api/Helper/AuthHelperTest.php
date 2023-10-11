<?php
/*
 SPDX-FileCopyrightText: © 2020 Siemens AG
 Author: Gaurav Mishra <mishra.gaurav@siemens.com>

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @dir
 * @brief Unit test cases for API helpers
 * @file
 * @brief Tests for AuthHelper
 */

/**
 * @namespace Fossology::UI::Api::Test::Helper
 *            Unit tests for API helpers
 */
namespace Fossology\UI\Api\Test\Helper;

use Fossology\Lib\Dao\UserDao;
use Fossology\UI\Api\Exceptions\HttpForbiddenException;
use Fossology\UI\Api\Helper\AuthHelper;
use Fossology\UI\Api\Helper\DbHelper;
use Mockery as M;
use Symfony\Component\HttpFoundation\Session\Session;

/**
 * @class AuthHelperTest
 * @brief Test cases for AuthHelper
 */
class AuthHelperTest extends \PHPUnit\Framework\TestCase
{

  /**
   * @var integer $assertCountBefore
   * Assertions before running tests
   */
  private $assertCountBefore;

  /**
   * @var AuthHelper $authHelper
   * AuthHelper object to test
   */
  private $authHelper;

  /**
   * @var UserDao $userDao
   * UserDao mock
   */
  private $userDao;

  /**
   * @var Session $session
   * Session mock
   */
  private $session;

  /**
   * @var DbHelper $dbHelper
   * DbHelper mock
   */
  private $dbHelper;

  /**
   * @brief Setup test objects
   * @see PHPUnit_Framework_TestCase::setUp()
   */
  protected function setUp() : void
  {
    $this->userDao = M::mock(UserDao::class);
    $this->session = M::mock(Session::class);
    $this->dbHelper = M::mock(DbHelper::class);

    $this->session->shouldReceive('isStarted')->andReturn(true);

    $this->authHelper = new AuthHelper($this->userDao, $this->session,
      $this->dbHelper);
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
   * @test
   * -# Test for AuthHelper::verifyAuthToken(), AuthHelper::generateJwtToken()
   *    and AuthHelper::isTokenActive()
   * -# Generate a JWT token using AuthHelper::generateJwtToken()
   * -# Call AuthHelper::verifyAuthToken()
   * -# Check if no exception is thrown
   * -# Check if the function updates user id and token scope.
   */
  public function testVerifyAuthToken()
  {
    $userId = null;
    $expectedUser = 2;
    $tokenScope = null;
    $jti = "4.2";
    $key = "mysecretkey";
    $createdOn = strftime('%Y-%m-%d');
    $expire = strftime('%Y-%m-%d', strtotime('+3 day'));
    $authToken = $this->authHelper->generateJwtToken($expire, $createdOn, $jti,
      "write", $key);
    $authHeader = "Bearer " . $authToken;
    $tokenRow = [
      "token_key" => $key,
      "created_on" => $createdOn,
      "expire_on" => $expire,
      "user_fk" => $expectedUser,
      "active" => 't',
      "token_scope" => "w"
    ];

    $this->dbHelper->shouldReceive('getTokenKey')
      ->withArgs(["4"])
      ->andReturn($tokenRow);
    $this->userDao->shouldReceive('isUserIdActive')
      ->withArgs([$expectedUser])
      ->andReturn(true);

    $GLOBALS['SysConf'] = ['AUTHENTICATION' => ['resttoken' => 'token']];
    $this->authHelper->verifyAuthToken($authHeader, $userId,
      $tokenScope);

    $this->assertEquals($expectedUser, $userId);
    $this->assertEquals("write", $tokenScope);
  }

  /**
   * @test
   * -# Test for AuthHelper::verifyAuthToken() with inactive user
   * -# Generate a JWT token using AuthHelper::generateJwtToken()
   * -# Call AuthHelper::verifyAuthToken()
   * -# Check if the function throws exception
   */
  public function testVerifyAuthTokenInactiveUser()
  {
    $userId = null;
    $expectedUser = 2;
    $tokenScope = null;
    $jti = "4.2";
    $key = "mysecretkey";
    $createdOn = strftime('%Y-%m-%d');
    $expire = strftime('%Y-%m-%d', strtotime('+3 day'));
    $authToken = $this->authHelper->generateJwtToken($expire, $createdOn, $jti,
      "write", $key);
    $authHeader = "Bearer " . $authToken;
    $tokenRow = [
      "token_key" => $key,
      "created_on" => $createdOn,
      "expire_on" => $expire,
      "user_fk" => $expectedUser,
      "active" => 't',
      "token_scope" => "w"
    ];

    $this->dbHelper->shouldReceive('getTokenKey')
      ->withArgs(["4"])
      ->andReturn($tokenRow);
    $this->userDao->shouldReceive('isUserIdActive')
      ->withArgs([$expectedUser])
      ->andReturn(false);

    $GLOBALS['SysConf'] = ['AUTHENTICATION' => ['resttoken' => 'token']];

    $this->expectException(HttpForbiddenException::class);

    $this->authHelper->verifyAuthToken($authHeader, $userId, $tokenScope);
  }

  /**
   * @test
   * -# Test for AuthHelper::isTokenActive()
   * -# Generate two DB rows with active and inactive tokens
   * -# Call AuthHelper::isTokenActive() on both rows and check for exception
   */
  public function testIsTokenActive()
  {
    $key = "mysecretkey";
    $createdOn = strftime('%Y-%m-%d');
    $expire = strftime('%Y-%m-%d', strtotime('+3 day'));
    $tokenId = 4;
    $activeTokenRow = [
      "token_key" => $key,
      "created_on" => $createdOn,
      "expire_on" => $expire,
      "user_fk" => 2,
      "active" => 't',
      "token_scope" => "w"
    ];
    $expireTokenRow = [
      "token_key" => $key,
      "created_on" => $createdOn,
      "expire_on" => $expire,
      "user_fk" => 2,
      "active" => 'f',
      "token_scope" => "w"
    ];

    $this->authHelper->isTokenActive($activeTokenRow, $tokenId);

    $this->expectException(HttpForbiddenException::class);

    $this->authHelper->isTokenActive($expireTokenRow, $tokenId);
  }

  /**
   * @test
   * -# Test for AuthHelper::isTokenActive()
   * -# Generate DB row with expired token
   * -# Check if AuthHelper::isTokenActive() calls DbHelper::invalidateToken()
   * -# Check if the function throws exception
   */
  public function testIsTokenActiveExpireOldToken()
  {
    $key = "mysecretkey";
    $createdOn = strftime('%Y-%m-%d', strtotime('-3 day'));
    $expire = strftime('%Y-%m-%d', strtotime('-1 day'));
    $tokenId = 4;
    $tokenRow = [
      "token_key" => $key,
      "created_on" => $createdOn,
      "expire_on" => $expire,
      "user_fk" => 2,
      "active" => 't',
      "token_scope" => "w"
    ];

    $this->dbHelper->shouldReceive('invalidateToken')
      ->withArgs([$tokenId])->once();
    $this->expectException(HttpForbiddenException::class);

    $this->authHelper->isTokenActive($tokenRow, $tokenId);
  }

  /**
   * @test
   * -# Test for AuthHelper::userHasGroupAccess()
   * -# Check if the function accepts correct group
   * -# Check if the function throws exception for inaccessible group
   */
  public function testUserHasGroupAccess()
  {
    $userId = 3;
    $groupName = 'fossy';
    $groupMap = [
      2 => 'fossy',
      3 => 'read',
      4 => 'write'
    ];

    $this->userDao->shouldReceive('getGroupIdByName')
      ->withArgs([$groupName])->andReturn(['group_pk' => 2])->once();
    $this->userDao->shouldReceive('getUserGroupMap')
      ->withArgs([$userId])->andReturn($groupMap)->twice();

    $this->authHelper->userHasGroupAccess($userId, $groupName);

    $groupName = 'random';
    $this->userDao->shouldReceive('getGroupIdByName')
      ->withArgs([$groupName])->andReturn(['group_pk' => 6])->once();

    $this->expectException(HttpForbiddenException::class);

    $this->authHelper->userHasGroupAccess($userId, $groupName);
  }
}
