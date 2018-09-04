<?php
/***************************************************************
 * Copyright (C) 2018 Siemens AG
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 ***************************************************************/

/**
 * @dir
 * @brief Helper functions for REST api use.
 * @file
 * @brief Provides authentication helper methods for REST api.
 * @namespace Fossology::UI::Api::Helper
 * @brief REST api helper classes
 */
namespace Fossology\UI\Api\Helper;

use Fossology\Lib\Exception;
use Fossology\Lib\Auth\Auth;
use Symfony\Component\HttpFoundation\Session\Session;
use Fossology\Lib\Dao\UserDao;

/**
 * @class AuthHelper
 * @brief Provides helper methods for REST api
 */
class AuthHelper
{
  /**
   * @var Session $session
   * Current Symfony session
   */
  private $session;
  /**
   * @var UserDao $userDao
   * User DAO object
   */
  private $userDao;

  /**
   * AuthHelper constructor.
   * @param UserDao $userDao
   */
  public function __construct($userDao)
  {
    $this->userDao = $userDao;
    $this->session = new Session();
    $this->session->save();
  }

  /**
   * @brief Check the username and password against the database.
   *
   * If the user is not 'Default User' and is valid, this function also update
   * session using updateSession().
   * @param string $userName  Username
   * @param string $password  Password
   * @return boolean True if user is valid, false otherwise.
   * @sa updateSession()
   */
  function checkUsernameAndPassword($userName, $password)
  {
    if (empty($userName) || $userName == 'Default User') {
      return false;
    }
    try {
      $row = $this->userDao->getUserAndDefaultGroupByUserName($userName);
    } catch (Exception $e) {
      return false;
    }

    if (empty($row['user_name'])) {
      return false;
    }

    /* Check the password -- only if a password exists */
    if (! empty($row['user_seed']) && ! empty($row['user_pass'])) {
      $passwordHash = sha1($row['user_seed'] . $password);
      if (strcmp($passwordHash, $row['user_pass']) != 0) {
        return false;
      }
    } else if (! empty($row['user_seed'])) {
      /* Seed with no password hash = no login */
      return false;
    } else if (! empty($password)) {
      /* empty password required */
      return false;
    }

    /* If you make it here, then username and password were good! */
    $this->updateSession($row);

    $this->session->set('time_check', time() + (480 * 60));
    /* No specified permission means ALL permission */
    if ("X" . $row['user_perm'] == "X") {
      $this->session->set(Auth::USER_LEVEL, PLUGIN_DB_ADMIN);
    } else {
      $this->session->set(Auth::USER_LEVEL, $row['user_perm']);
    }
    $this->session->set('checkip', GetParm("checkip", PARM_STRING));
    /* Check for the no-popup flag */
    if (GetParm("nopopup", PARM_INTEGER) == 1) {
      $this->session->set('NoPopup', 1);
    } else {
      $this->session->set('NoPopup', 0);
    }
    return true;
  }

  /**
   * @brief Set $_SESSION and $SysConf user variables
   * @param array $UserRow Users table row, if empty, use Default User
   * @return void, updates globals $_SESSION and $SysConf[auth][UserId]
   * variables
   */
  function updateSession($userRow)
  {
    global $SysConf;

    if (empty($userRow)) {
      $userRow = $this->userDao->getUserAndDefaultGroupByUserName(
        'Default User');
    }

    $SysConf['auth'][Auth::USER_ID] = $userRow['user_pk'];
    $this->session->set(Auth::USER_ID, $userRow['user_pk']);
    $this->session->set(Auth::USER_NAME, $userRow['user_name']);
    $this->session->set('Folder', $userRow['root_folder_fk']);
    $this->session->set(Auth::USER_LEVEL, $userRow['user_perm']);
    $this->session->set('UserEmail', $userRow['user_email']);
    $this->session->set('UserEnote', $userRow['email_notify']);
    $SysConf['auth'][Auth::GROUP_ID] = $userRow['group_fk'];
    $this->session->set(Auth::GROUP_ID, $userRow['group_fk']);
    $this->session->set('GroupName', $userRow['group_name']);
  }

  /**
   * Get the current Symfony session
   * @return Session
   */
  public function getSession()
  {
    return $this->session;
  }
}
