<?php
/*
Copyright (C) 2014, Siemens AG

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
version 2 as published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License along
with this program; if not, write to the Free Software Foundation, Inc.,
51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/

namespace Fossology\Lib\Application;

/**
 * @class UserInfoTest
 * @brief Unit tests for UserInfo class
 */
class UserInfoTest extends \PHPUnit\Framework\TestCase
{

  /** @var UserInfo $userInfo
   * UserInfo class object for testing */
  private $userInfo;

  /**
   * @brief One time setup for test
   * @see PHPUnit::Framework::TestCase::setUp()
   */
  protected function setUp()
  {
    $this->userInfo = new UserInfo();
  }

  /**
   * @brief Test for UserInfo::getUserId()
   * @test
   * -# Set user id in session.
   * -# Call UserInfo::getUserId() and check if the id matches.
   */
  public function testGetUserId()
  {
    $userId = 424;

    $_SESSION['UserId'] = $userId;

    assertThat($this->userInfo->getUserId(), is($userId));
  }

  /**
   * @brief Test for UserInfo::getGroupId()
   * @test
   * -# Set group id in session.
   * -# Call UserInfo::getGroupId() and check if the id matches.
   */
  public function testGetGroupId()
  {
    $groupId = 321;

    $_SESSION['GroupId'] = $groupId;

    assertThat($this->userInfo->getGroupId(), is($groupId));
  }
}
