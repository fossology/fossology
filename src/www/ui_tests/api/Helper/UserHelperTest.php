<?php
/*
 SPDX-FileCopyrightText: © 2026 Fossology contributors

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @file
 * @brief Tests for UserHelper
 */

namespace Fossology\UI\Api\Test\Helper;

use Fossology\Lib\Dao\UserDao;
use Fossology\UI\Api\Helper\RestHelper;
use Fossology\UI\Api\Helper\UserHelper;
use Fossology\UI\Api\Models\ApiVersion;
use Mockery as M;

use \PHPUnit\Framework\TestCase;

require_once dirname(dirname(dirname(dirname(__DIR__)))) .
  '/lib/php/Plugin/FO_Plugin.php';
require_once dirname(dirname(dirname(dirname(__DIR__)))) .
  '/lib/php/common-agents.php';
require_once dirname(dirname(dirname(dirname(__DIR__)))) .
  '/lib/php/common-menu.php';

/**
 * @class UserHelperTest
 * @brief Tests for UserHelper
 */
class UserHelperTest extends TestCase
{
  /**
   * @var integer $assertCountBefore
   * Assertions before running tests
   */
  private $assertCountBefore;
  /**
   * @var RestHelper $restHelper
   * RestHelper mock
   */
  private $restHelper;
  /**
   * @var UserDao $userDao
   * UserDao mock
   */
  private $userDao;
  /**
   * @var array $dbUser
   * Fake current DB row for the user being edited
   */
  private $dbUser;

  /**
   * @brief Setup test objects
   * @see PHPUnit_Framework_TestCase::setUp()
   */
  protected function setUp() : void
  {
    global $container;
    $container = M::mock('ContainerBuilder');
    $this->restHelper = M::mock(RestHelper::class);
    $this->userDao = M::mock(UserDao::class);

    $this->restHelper->shouldReceive('getUserDao')->andReturn($this->userDao);
    $container->shouldReceive('get')->withArgs(array(
      'helper.restHelper'))->andReturn($this->restHelper);

    $this->dbUser = [
      'user_name' => 'fossy',
      'root_folder_fk' => 1,
      'group_fk' => 1,
      'upload_visibility' => 'public',
      'default_folder_fk' => 1,
      'user_desc' => 'Default Administrator',
      'user_status' => 'active',
      'user_email' => 'fossy@localhost',
      'email_notify' => 'y',
      'default_bucketpool_fk' => null,
      'user_perm' => 10,
      'user_agent_list' => '',
    ];
    $this->userDao->shouldReceive('getUserByPk')->andReturn($this->dbUser);
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
   * -# Test that createSymRequest() reads the V2 camelCase 'userStatus' key
   * -# Confirm the resulting symfony request carries it through as
   *    'user_status'
   */
  public function testCreateSymRequestReadsCamelCaseUserStatusForV2()
  {
    $userHelper = new UserHelper(2);
    $request = $userHelper->createSymRequest(['userStatus' => 'inactive'],
      ApiVersion::V2);
    $this->assertEquals('inactive', $request->request->get('user_status'));
  }

  /**
   * @test
   * -# Test that createSymRequest() reads the V1 snake_case 'user_status' key
   * -# Confirm the resulting symfony request carries it through as
   *    'user_status'
   */
  public function testCreateSymRequestReadsSnakeCaseUserStatusForV1()
  {
    $userHelper = new UserHelper(2);
    $request = $userHelper->createSymRequest(['user_status' => 'inactive'],
      ApiVersion::V1);
    $this->assertEquals('inactive', $request->request->get('user_status'));
  }

  /**
   * @test
   * -# Test that createSymRequest() falls back to the existing DB value when
   *    no status is given in the request body, for both API versions
   */
  public function testCreateSymRequestFallsBackToDbUserStatus()
  {
    $userHelper = new UserHelper(2);
    $requestV2 = $userHelper->createSymRequest([], ApiVersion::V2);
    $this->assertEquals('active', $requestV2->request->get('user_status'));

    $requestV1 = $userHelper->createSymRequest([], ApiVersion::V1);
    $this->assertEquals('active', $requestV1->request->get('user_status'));
  }
}
