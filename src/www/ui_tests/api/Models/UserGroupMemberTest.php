<?php

namespace Models;

use Fossology\Lib\Dao\UserDao;
use Fossology\UI\Api\Helper\DbHelper;
use Fossology\UI\Api\Helper\RestHelper;
use Fossology\UI\Api\Models\ApiVersion;
use Fossology\UI\Api\Models\User;
use Fossology\UI\Api\Models\UserGroupMember;
use Monolog\Test\TestCase;
use Mockery as M;
class UserGroupMemberTest extends TestCase
{

  private $dbHelper;
  private $restHelper;
  private $userDao;
  protected function setup(): void
  {
    global $container;
    $container = M::mock('ContainerBuilder');
    $this->dbHelper = M::mock(DbHelper::class);
    $this->restHelper = M::mock(RestHelper::class);
    $this->userDao = M::mock(UserDao::class);

    $this->restHelper->shouldReceive('getDbHelper')->andReturn($this->dbHelper);

    $container->shouldReceive('get')->withArgs(array(
      'helper.restHelper'))->andReturn($this->restHelper);
    $this->restHelper->shouldReceive('getUserDao')->andReturn($this->userDao);
  }
  /**
   * Provides test data and an instance of the UserGroupMember class.
   *
   * @return array An associative array containing:
   *  - `expectedArray`: The expected array structure.
   *  - `obj`: The instance of UserGroupMember being tested.
   */
  private function getUserGroupMemberInfo($version = ApiVersion::V2)
  {

    $user = new User(1, "fossy", "Admin user", "fossy@gmail.com", "admin", 4, "fossy@gmail.com", "monk", 3, null);
    if ($version == ApiVersion::V1) {
      $expectedArray = [
        'user' => $user->getArray($version),
        'group_perm' => 0
      ];
    } else {
      $expectedArray = [
        'user' => $user->getArray($version),
        'groupPerm' => 0
      ];
    }

    $obj = new UserGroupMember($user, $expectedArray['group_perm']);
    return [
      'expectedArray' => $expectedArray,
      'obj' => $obj
    ];
  }

  // Getter Tests
  public function testGetUser()
  {
    $user = new User(1, "fossy", "Admin user", "fossy@gmail.com", "admin", 4, "fossy@gmail.com", "monk", 3, null);
    $userGroupMember = new UserGroupMember($user, 3);
    $this->assertEquals($user, $userGroupMember->getUser());
  }

  public function testGetGroupPerm()
  {
    $user = new User(1, "fossy", "Admin user", "fossy@gmail.com", "admin", 4, "fossy@gmail.com", "monk", 3, null);
    $userGroupMember = new UserGroupMember($user, 3);
    $this->assertEquals(3, $userGroupMember->getGroupPerm());
  }

  // Setter Tests
  public function testSetUser()
  {
    $user1 = new User(1, "fossy", "Admin user", "fossy@gmail.com", "admin", 4, "fossy@gmail.com", 'monk', 3, null);
    $user2 = new User(2, "newuser", "New user", "newuser@gmail.com", "user", 5, "newuser@gmail.com", 'monk', 4, null);
    $userGroupMember = new UserGroupMember($user1, 3);

    $userGroupMember->setUser($user2);
    $this->assertEquals($user2, $userGroupMember->getUser());
  }

  public function testSetGroupPerm()
  {
    $user = new User(1, "fossy", "Admin user", "fossy@gmail.com", "admin", 4, "fossy@gmail.com", "monk", 3, null);
    $userGroupMember = new UserGroupMember($user, 3);

    $userGroupMember->setGroupPerm(5);
    $this->assertEquals(5, $userGroupMember->getGroupPerm());
  }

  // Test for JSON output
  public function testGetJSON()
  {
    $data = $this->getUserGroupMemberInfo(ApiVersion::V1);
    $this->assertJsonStringEqualsJsonString(
      json_encode($data['expectedArray']),
      $data['obj']->getJSON()
    );
  }

  // Test for Array output
  public function testGetArrayV1()
  {
    $data = $this->getUserGroupMemberInfo(ApiVersion::V1);
    $this->assertEquals($data['expectedArray'], $data['obj']->getArray(ApiVersion::V1));
  }
}

