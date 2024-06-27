<?php
/*
 SPDX-FileCopyrightText: © 2020 Siemens AG
 Author: Gaurav Mishra <mishra.gaurav@siemens.com>

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @file
 * @brief Tests for DbHelper
 */

namespace Fossology\UI\Api\Test\Helper;

if (session_status() == PHP_SESSION_NONE) {
  session_start();
}

require_once dirname(dirname(dirname(dirname(__DIR__)))) .
  '/lib/php/Plugin/FO_Plugin.php';

use Fossology\Lib\Dao\UploadDao;
use Mockery as M;
use Fossology\Lib\Db\ModernDbManager;
use Fossology\UI\Api\Helper\DbHelper;
use Fossology\UI\Api\Helper\RestHelper;
use Fossology\Lib\Auth\Auth;
use Fossology\UI\Api\Models\User;
use Fossology\Lib\Dao\FolderDao;

/**
 * @class DbHelperTest
 * @brief Tests for DbHelper
 */
class DbHelperTest extends \PHPUnit\Framework\TestCase
{
  /**
   * @var integer $assertCountBefore
   * Assertions before running tests
   */
  private $assertCountBefore;

  /**
   * @var ModernDbManager $dbManager
   * ModernDbManager mock
   */
  private $dbManager;

  /**
   * @var DbHelper $dbHelper
   * DbHelper object to test
   */
  private $dbHelper;

  /**
   * @var FolderDao $folderDao
   * FolderDao object to test
   */
  private $folderDao;

  /**
   * @var UploadDao $uploadDao
   * UploadDao object to test
   */
  private $uploadDao;

  /**
   * @brief Setup test objects
   * @see PHPUnit_Framework_TestCase::setUp()
   */
  protected function setUp() : void
  {
    global $container;
    $restHelper = M::mock(RestHelper::class);
    $container = M::mock('ContainerBuilder');
    $this->dbManager = M::mock(ModernDbManager::class);
    $this->folderDao = M::mock(FolderDao::class);
    $this->uploadDao = M::mock(UploadDao::class);

    $this->dbHelper = new DbHelper($this->dbManager, $this->folderDao,
      $this->uploadDao);
    $this->assertCountBefore = \Hamcrest\MatcherAssert::getCount();
    $container->shouldReceive('get')->withArgs(array(
      'helper.restHelper'))->andReturn($restHelper);
  }

  /**
   * @brief Remove test objects
   * @see PHPUnit_Framework_TestCase::tearDown()
   */
  protected function tearDown() : void
  {
    $GLOBALS['SysConf']['auth'][Auth::USER_ID] = -1;
    $_SESSION[Auth::USER_LEVEL] = -1;
    $this->addToAssertionCount(
      \Hamcrest\MatcherAssert::getCount() - $this->assertCountBefore);
    M::close();
  }

  /**
   * Generate user row data to match database
   * @return array
   */
  private function generateUserRow()
  {
    $agent_list = [];
    $agent_list[2] = 'nomos,monk';
    $agent_list[3] = 'nomos,ojo';
    $agent_list[4] = 'copyright,ecc';

    $perm_list = [];
    $perm_list[2] = PLUGIN_DB_ADMIN;
    $perm_list[3] = PLUGIN_DB_WRITE;
    $perm_list[4] = PLUGIN_DB_READ;

    $userRows = [];
    for ($i = 2; $i <= 4; $i++) {
      $row = [
        'user_pk' => $i, 'user_name' => "user$i", 'user_desc' => "user $i",
        'user_email' => "user$i@local", 'email_notify' => 'y',
        'root_folder_fk' => 2, 'group_fk' => 2, 'user_perm' => $perm_list[$i],
        'user_agent_list' => $agent_list[$i], 'default_bucketpool_fk' => 2
      ];
      $userRows[$i] = $row;
    }
    return $userRows;
  }

  /**
   * Generate User object array from DB array
   * @param array $rows
   * @param integer $currentUser If set, returns complete object of user with
   *                             provided id, others will be masked
   * @return array
   */
  private function generateUserFromRow($rows, $currentUser = null)
  {
    $users = [];
    foreach ($rows as $row) {
      if ($currentUser === null || $row['user_pk'] == $currentUser) {
        $user = new User($row["user_pk"], $row["user_name"], $row["user_desc"],
            $row["user_email"], $row["user_perm"], $row["root_folder_fk"],
          $row["email_notify"], $row["user_agent_list"], $row['group_fk'], $row["default_bucketpool_fk"]);
      } else {
        $user = new User($row["user_pk"], $row["user_name"], $row["user_desc"],
          null, null, null, null, null, null);
      }
      $users[$row["user_pk"]] = $user->getArray();
    }
    return $users;
  }

  /**
   * @test
   * -# Test for DbHelper::getUsers()
   * -# Check if all users are returned for admin user
   */
  public function testGetUsersAll()
  {
    $userId = 2;

    $GLOBALS['SysConf']['auth'][Auth::USER_ID] = $userId;
    $_SESSION[Auth::USER_LEVEL] = Auth::PERM_ADMIN;

    $sql = 'SELECT user_pk, user_name, user_desc, user_email,
                  email_notify, root_folder_fk, group_fk, user_perm, user_agent_list, ' .
      'default_bucketpool_fk FROM users;';
    $statement = DbHelper::class . "::getUsers.getAllUsers";
    $userRows = $this->generateUserRow();

    $this->dbManager->shouldReceive('getRows')
      ->withArgs([$sql, [], $statement])
      ->once()
      ->andReturn($userRows);

    $expectedUsers = array_values($this->generateUserFromRow($userRows));
    $actualUsers = $this->dbHelper->getUsers();

    $allUsers = array();
    foreach ($actualUsers as $user) {
      $allUsers[] = $user->getArray();
    }

    $this->assertEquals($expectedUsers, $allUsers);
  }

  /**
   * @test
   * -# Test for DbHelper::getUsers()
   * -# Check if all users are returned, masking other users for non-admin users
   */
  public function testGetUsersAllNonAdmin()
  {
    $userId = 3;

    $GLOBALS['SysConf']['auth'][Auth::USER_ID] = $userId;
    $_SESSION[Auth::USER_LEVEL] = Auth::PERM_WRITE;

    $sql = 'SELECT user_pk, user_name, user_desc, user_email,
                  email_notify, root_folder_fk, group_fk, user_perm, user_agent_list, ' .
      'default_bucketpool_fk FROM users;';
    $statement = DbHelper::class . "::getUsers.getAllUsers";
    $userRows = $this->generateUserRow();

    $this->dbManager->shouldReceive('getRows')
      ->withArgs([$sql, [], $statement])
      ->once()
      ->andReturn($userRows);

    $expectedUsers = array_values($this->generateUserFromRow($userRows,
      $userId));
    $actualUsers = $this->dbHelper->getUsers();
    $allUsers = array();
    foreach ($actualUsers as $user) {
      $allUsers[] = $user->getArray();
    }
    $this->assertEquals($expectedUsers, $allUsers);
  }

  /**
   * @test
   * -# Test for DbHelper::getUsers() fetching specific user
   * -# Check if complete object is returned
   */
  public function testGetUsersSingleUserAdmin()
  {
    $userId = 2;

    $GLOBALS['SysConf']['auth'][Auth::USER_ID] = $userId;
    $_SESSION[Auth::USER_LEVEL] = Auth::PERM_ADMIN;

    $sql = "SELECT user_pk, user_name, user_desc, user_email,
                email_notify, root_folder_fk, group_fk, user_perm, user_agent_list, default_bucketpool_fk FROM users
                WHERE user_pk = $1;";
    $statement = DbHelper::class . "::getUsers.getSpecificUser";
    $userRows = $this->generateUserRow();

    $this->dbManager->shouldReceive('getRows')
      ->withArgs([$sql, [$userId], $statement])
      ->once()
      ->andReturn([$userRows[$userId]]);

    $expectedUsers = $this->generateUserFromRow($userRows, $userId);
    $actualUsers = $this->dbHelper->getUsers($userId);

    $allUsers = array();
    foreach ($actualUsers as $user) {
      $allUsers[] = $user->getArray();
    }
    $this->assertEquals([$expectedUsers[$userId]], $allUsers);
  }

  /**
   * @test
   * -# Test for DbHelper::getUsers() fetching single user by non-admin user
   * -# Check if masked user is returned
   */
  public function testGetUsersSingleUserNonAdmin()
  {
    $userId = 3;
    $fetchId = 4;

    $GLOBALS['SysConf']['auth'][Auth::USER_ID] = $userId;
    $_SESSION[Auth::USER_LEVEL] = Auth::PERM_WRITE;

    $sql = "SELECT user_pk, user_name, user_desc, user_email,
                email_notify, root_folder_fk, group_fk, user_perm, user_agent_list, default_bucketpool_fk FROM users
                WHERE user_pk = $1;";
    $statement = DbHelper::class . "::getUsers.getSpecificUser";
    $userRows = $this->generateUserRow();

    $this->dbManager->shouldReceive('getRows')
      ->withArgs([$sql, [$fetchId], $statement])
      ->once()
      ->andReturn([$userRows[$fetchId]]);

    $expectedUsers = $this->generateUserFromRow($userRows, $userId);
    $actualUsers = $this->dbHelper->getUsers($fetchId);

    $allUsers = array();
    foreach ($actualUsers as $user) {
      $allUsers[] = $user->getArray();
    }
    $this->assertEquals([$expectedUsers[$fetchId]], $allUsers);
  }
}
