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

use Mockery as M;
use Fossology\UI\Api\Helper\AuthHelper;
use Fossology\Lib\Dao\UserDao;
use Symfony\Component\HttpFoundation\Session\Session;
use Fossology\UI\Api\Helper\DbHelper;
use Firebase\JWT\JWT;
use Fossology\UI\Api\Models\Info;
use Fossology\UI\Api\Models\InfoType;

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
  protected function setUp()
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
  protected function tearDown()
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
   * -# Check if the function says token is active
   * -# Check if the function updates user id and token scope.
   */
  public function testVerifyAuthToken()
  {
    $userId = null;
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
      "user_fk" => 2,
      "active" => 't',
      "token_scope" => "w"
    ];

    $this->dbHelper->shouldReceive('getTokenKey')
      ->withArgs(["4"])
      ->andReturn($tokenRow);

    $expectedReturn = true;

    $actualReturn = $this->authHelper->verifyAuthToken($authHeader, $userId,
      $tokenScope);

    $this->assertEquals($expectedReturn, $actualReturn);
    $this->assertEquals(2, $userId);
    $this->assertEquals("write", $tokenScope);
  }

  /**
   * @test
   * -# Test for AuthHelper::isTokenActive()
   * -# Generate two DB rows with active and inactive tokens
   * -# Call AuthHelper::isTokenActive() on both rows and check for results
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

    $this->assertEquals(true, $this->authHelper->isTokenActive($activeTokenRow,
      $tokenId));

    $expectedResponse = new Info(403, "Token expired.", InfoType::ERROR);
    $actualResponse = $this->authHelper->isTokenActive($expireTokenRow,
      $tokenId);
    $this->assertEquals($expectedResponse, $actualResponse);
  }

  /**
   * @test
   * -# Test for AuthHelper::isTokenActive()
   * -# Generate DB row with expired token
   * -# Check if AuthHelper::isTokenActive() calls DbHelper::invalidateToken()
   * -# Check if the result contains 403 status
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

    $expectedResponse = new Info(403, "Token expired.", InfoType::ERROR);
    $actualResponse = $this->authHelper->isTokenActive($tokenRow, $tokenId);
    $this->assertEquals($expectedResponse->getArray(),
      $actualResponse->getArray());
  }

  /**
   * @test
   * -# Test for AuthHelper::userHasGroupAccess()
   * -# Check if the function accepts correct group
   * -# Check if the function returns 403 for inaccessible group
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

    $this->assertEquals(true, $this->authHelper->userHasGroupAccess($userId,
      $groupName));

    $groupName = 'random';
    $this->userDao->shouldReceive('getGroupIdByName')
      ->withArgs([$groupName])->andReturn(['group_pk' => 6])->once();

    $expectedResponse = new Info(403, "User has no access to $groupName group",
      InfoType::ERROR);
    $actualResponse = $this->authHelper->userHasGroupAccess($userId,
      $groupName);
    $this->assertEquals($expectedResponse, $actualResponse);
  }
}
