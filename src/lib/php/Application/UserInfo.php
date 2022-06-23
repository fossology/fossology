<?php
/*
 SPDX-FileCopyrightText: © 2014 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Application;

/**
 * @file
 * @brief Get user info from session
 */

/**
 * @class UserInfo
 * @brief Get user info from session
 */
class UserInfo
{

  /**
   * @brief Get the user id from the session.
   * @return int Id of the current user
   */
  public function getUserId()
  {
    return $_SESSION['UserId'];
  }

  /**
   * @brief Get the group id from the session.
   * @return int Id of the current group
   */
  public function getGroupId()
  {
    return $_SESSION['GroupId'];
  }
}
