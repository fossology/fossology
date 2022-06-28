<?php
/***************************************************************
 * Copyright (C) 2022 Samuel Dushimimana <dushsam100@gmail.com>

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
 * @brief Tests for GroupController
 */

namespace Fossology\UI\Api\Test\Controllers;

require_once dirname(dirname(dirname(dirname(__DIR__)))) .
  '/lib/php/Plugin/FO_Plugin.php';


use Fossology\Lib\Auth\Auth;
use Fossology\UI\Api\Helper\ResponseHelper;
use Fossology\UI\Api\Models\Info;
use Fossology\UI\Api\Models\InfoType;
use Mockery as M;
use Fossology\Lib\Dao\UserDao;
use Fossology\Lib\Db\DbManager;
use Fossology\UI\Api\Helper\DbHelper;
use Fossology\UI\Api\Helper\RestHelper;
use Fossology\UI\Api\Controllers\GroupController;

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
    $this->groupController = new GroupController($container);
    $this->assertCountBefore = \Hamcrest\MatcherAssert::getCount();
    $this->dbManager = M::mock(DbManager::class);
    $this->dbHelper->shouldReceive('getDbManager')->andReturn($this->dbManager);

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
   * @test
   * -# Test GroupController::deleteGroup() for valid delete request
   * -# Check if response status is 202
   */
  public function testDeleteGroup()
  {
    $groupId = 4;
    $userId = 1;
    $this->restHelper->shouldReceive('getUserId')->andReturn($userId);
    $this->dbHelper->shouldReceive('doesIdExist')
      ->withArgs(["groups", "group_pk", $groupId])->andReturn(true);
    $_SESSION[Auth::USER_LEVEL] = Auth::PERM_WRITE;
    $this->userDao->shouldReceive('getDeletableAdminGroupMap')->withArgs([$userId,$_SESSION[Auth::USER_LEVEL]]);
    $this->userDao->shouldReceive('deleteGroup')->withArgs([$groupId]);

    $info = new Info(202, "User Group will be deleted", InfoType::INFO);
    $expectedResponse = (new ResponseHelper())->withJson($info->getArray(),
      $info->getCode());
    $actualResponse = $this->groupController->deleteGroup(null, new ResponseHelper(),
      ['id' => $groupId]);

    $this->assertEquals($expectedResponse->getStatusCode(),
      $actualResponse->getStatusCode());
    $this->assertEquals($this->getResponseJson($expectedResponse),
      $this->getResponseJson($actualResponse));
  }


}
