<?php
/*
Copyright (C) 2014-2015, Siemens AG

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

/**
 * @namespace Fossology::Lib::Auth
 * Contains the constants and helpers for authentication of user.
 */
namespace Fossology\Lib\Auth;

/**
 * @file
 * @brief Contains the constants and helpers for authentication of user.
 * @class Auth
 * @brief Contains the constants and helpers for authentication of user.
 *
 * Permissions
 * See https://github.com/fossology/fossology/wiki/Access-Control
 */
class Auth
{
  /** @var string USER_NAME
   * Session variable name containing user name */
  const USER_NAME = 'User';
  /** @var string USER_ID
   * Session variable name containing user id */
  const USER_ID = 'UserId';
  /** @var string GROUP_ID
   * Session variable name containing group id */
  const GROUP_ID = 'GroupId';
  /** @var string USER_LEVEL
   * Session variable name containing user permission level */
  const USER_LEVEL = 'UserLevel';

  /** @var int PERM_NONE
   * No permissions */
  const PERM_NONE = 0;
  /** @var int PERM_READ
   * Read only permission */
  const PERM_READ = 1;
  /** @var int PERM_WRITE
   * DB writes permitted */
  const PERM_WRITE= 3;
  /** @var int PERM_WRITE
   * DB writes permitted, with additional clearing permissions. */
  const PERM_CADMIN=5;
  /** @var int PERM_ADMIN
   * Add/delete users and groups. This is the 'superuser' permission. */
  const PERM_ADMIN=10;

  /**
   * @brief Get the current user's id
   * @return int User id
   */
  public static function getUserId()
  {
    return $GLOBALS['SysConf']['auth'][self::USER_ID];
  }

  /**
   * @brief Get the current user's group id
   * @return int Group id
   */
  public static function getGroupId()
  {
    return $GLOBALS['SysConf']['auth'][self::GROUP_ID];
  }

  /**
   * @brief Check if user is admin
   * @return boolean True if user is an admin, false otherwise.
   */
  public static function isAdmin()
  {
    return $_SESSION[self::USER_LEVEL]==self::PERM_ADMIN;
  }

  /**
   * @brief Check if user is clearing admin
   * @return boolean True if user is an clearing admin or more, false otherwise.
   */
  public static function isClearingAdmin()
  {
    return $_SESSION[self::USER_LEVEL]>=self::PERM_CADMIN;
  }
}
