<?php
/*
 SPDX-FileCopyrightText: © 2014-2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
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

  /** @var int TOKEN_OAUTH
   * Allow OAuth tokens for REST */
  const TOKEN_OAUTH = 0x1;
  /** @var int TOKEN_TOKEN
   * Allow FOSSology JWT tokens for REST */
  const TOKEN_TOKEN = 0x2;
  /** @var int TOKEN_BOTH
   * Allow both token formats for REST */
  const TOKEN_BOTH  = 0x3;

  /**
   * @brief Get the current user's id
   * @return int User id
   */
  public static function getUserId()
  {
    if (array_key_exists('auth', $GLOBALS['SysConf'])) {
      return $GLOBALS['SysConf']['auth'][self::USER_ID];
    }
    return 0;
  }

  /**
   * @brief Get the current user's group id
   * @return int Group id
   */
  public static function getGroupId()
  {
    if (array_key_exists('auth', $GLOBALS['SysConf'])) {
      return $GLOBALS['SysConf']['auth'][self::GROUP_ID];
    }
    return 0;
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

  /**
   * Get REST Token format from conf
   * @return int Auth::TOKEN_TOKEN | Auth::TOKEN_OAUTH | Auth::TOKEN_BOTH
   */
  public static function getRestTokenType()
  {
    global $SysConf;
    $restToken = "token";
    if (array_key_exists('AUTHENTICATION', $SysConf) &&
        array_key_exists('resttoken', $SysConf['AUTHENTICATION'])) {
      $restToken = $SysConf['AUTHENTICATION']['resttoken'];
    }
    switch ($restToken) {
      case 'oauth':
        return self::TOKEN_OAUTH;
        break;
      case 'both':
        return self::TOKEN_BOTH;
        break;
      default:
        return self::TOKEN_TOKEN;
    }
  }
}
