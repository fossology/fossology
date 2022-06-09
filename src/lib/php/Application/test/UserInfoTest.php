<?php
/*
 SPDX-FileCopyrightText: © 2014 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
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
  protected function setUp() : void
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
