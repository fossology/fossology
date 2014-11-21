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


class UserInfoTest extends \PHPUnit_Framework_TestCase {

  /** @var UserInfo */
  private $userInfo;

  public function setUp() {
    $this->userInfo = new UserInfo();
  }

  public function testGetUserId() {
    $userId = 424;

    $_SESSION['UserId'] = $userId;

    assertThat($this->userInfo->getUserId(), is($userId));
  }

  public function testGetGroupId() {
    $groupId = 321;

    $_SESSION['GroupId'] = $groupId;

    assertThat($this->userInfo->getGroupId(), is($groupId));
  }
}
 