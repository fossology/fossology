<?php
/*
 SPDX-FileCopyrightText: © 2026 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Auth;

/**
 * @class AuthTest
 * @brief Test for class Auth
 */
class AuthTest extends \PHPUnit\Framework\TestCase
{
  protected function setUp(): void
  {
    $_SESSION = array();
  }

  protected function tearDown(): void
  {
    $_SESSION = array();
  }

  /**
   * @test
   * -# isAdmin() must return false, not raise a warning, when nothing has
   *    populated the session yet (e.g. an anonymous request to a page with
   *    REQUIRES_LOGIN => false).
   */
  public function testIsAdminFalseWhenSessionEmpty(): void
  {
    $this->assertFalse(Auth::isAdmin());
  }

  /**
   * @test
   * -# isAdmin() must return false for a logged-in, non-admin user level.
   */
  public function testIsAdminFalseWhenUserLevelBelowAdmin(): void
  {
    $_SESSION[Auth::USER_LEVEL] = Auth::PERM_ADMIN - 1;
    $this->assertFalse(Auth::isAdmin());
  }

  /**
   * @test
   * -# isAdmin() must return true when the session user level is exactly
   *    PERM_ADMIN.
   */
  public function testIsAdminTrueWhenUserLevelIsAdmin(): void
  {
    $_SESSION[Auth::USER_LEVEL] = Auth::PERM_ADMIN;
    $this->assertTrue(Auth::isAdmin());
  }

  /**
   * @test
   * -# getUserId() must return 0 when $GLOBALS['SysConf'] is not populated.
   */
  public function testGetUserIdZeroWhenSysConfEmpty(): void
  {
    $prevSysConf = array_key_exists('SysConf', $GLOBALS) ? $GLOBALS['SysConf'] : null;
    unset($GLOBALS['SysConf']);
    try {
      $this->assertSame(0, Auth::getUserId());
    } finally {
      $GLOBALS['SysConf'] = $prevSysConf;
    }
  }

  /**
   * @test
   * -# getUserId() must return user id when $GLOBALS['SysConf']['auth'] is populated.
   */
  public function testGetUserIdWhenSysConfPopulated(): void
  {
    $prevSysConf = array_key_exists('SysConf', $GLOBALS) ? $GLOBALS['SysConf'] : null;
    $GLOBALS['SysConf']['auth'][Auth::USER_ID] = 42;
    try {
      $this->assertSame(42, Auth::getUserId());
    } finally {
      $GLOBALS['SysConf'] = $prevSysConf;
    }
  }

  /**
   * @test
   * -# getGroupId() must return 0 when $GLOBALS['SysConf'] is not populated.
   */
  public function testGetGroupIdZeroWhenSysConfEmpty(): void
  {
    $prevSysConf = array_key_exists('SysConf', $GLOBALS) ? $GLOBALS['SysConf'] : null;
    unset($GLOBALS['SysConf']);
    try {
      $this->assertSame(0, Auth::getGroupId());
    } finally {
      $GLOBALS['SysConf'] = $prevSysConf;
    }
  }

  /**
   * @test
   * -# getGroupId() must return group id when $GLOBALS['SysConf']['auth'] is populated.
   */
  public function testGetGroupIdWhenSysConfPopulated(): void
  {
    $prevSysConf = array_key_exists('SysConf', $GLOBALS) ? $GLOBALS['SysConf'] : null;
    $GLOBALS['SysConf']['auth'][Auth::GROUP_ID] = 10;
    try {
      $this->assertSame(10, Auth::getGroupId());
    } finally {
      $GLOBALS['SysConf'] = $prevSysConf;
    }
  }
}
