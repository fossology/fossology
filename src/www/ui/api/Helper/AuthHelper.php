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
   */
  public function __construct()
  {
    $this->userDao = $GLOBALS["container"]->get('dao.user');
    $this->session = $GLOBALS["container"]->get('session');
    if (!$this->session->isStarted())
    {
      $this->session->setName('Login');
      $this->session->start();
    }
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
    $authPlugin = plugin_find('auth');
    return $authPlugin->checkUsernameAndPassword($userName, $password);
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
