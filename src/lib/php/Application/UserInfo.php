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
 * @file
 * @brief Get user info from session
 */

/**
 * @class UserInfo
 * @brief Get user info from session
 */
class UserInfo {

  /**
   * @brief Get the user id from the session.
   * @return int Id of the current user
   */
  public function getUserId() {
    return $_SESSION['UserId'];
  }

  /**
   * @brief Get the group id from the session.
   * @return int Id of the current group
   */
  public function getGroupId() {
    return $_SESSION['GroupId'];
  }

}
