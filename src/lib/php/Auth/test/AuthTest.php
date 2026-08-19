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
}
