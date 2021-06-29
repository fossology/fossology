<?php
/***************************************************************
 Copyright (C) 2018,2021 Siemens AG
 Author: Gaurav Mishra <mishra.gaurav@siemens.com>

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
 ***************************************************************/
/**
 * @file
 * @brief Controller for user queries
 */

namespace Fossology\UI\Api\Controllers;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Fossology\UI\Api\Models\Info;
use Fossology\UI\Api\Models\InfoType;

/**
 * @class UserController
 * @brief Controller for User model
 */
class UserController extends RestController
{
  /**
   * Get list of Users
   *
   * @param ServerRequestInterface $request
   * @param ResponseInterface $response
   * @param array $args
   * @return ResponseInterface
   */
  public function getUsers($request, $response, $args)
  {
    $id = null;
    if (isset($args['id'])) {
      $id = intval($args['id']);
      if (! $this->dbHelper->doesIdExist("users", "user_pk", $id)) {
        $returnVal = new Info(404, "UserId doesn't exist", InfoType::ERROR);
        return $response->withJson($returnVal->getArray(), $returnVal->getCode());
      }
    }
    $users = $this->dbHelper->getUsers($id);
    if ($id !== null) {
      $users = $users[0];
    }
    return $response->withJson($users, 200);
  }

  /**
   * Delete a given user
   *
   * @param ServerRequestInterface $request
   * @param ResponseInterface $response
   * @param array $args
   * @return ResponseInterface
   */
  public function deleteUser($request, $response, $args)
  {
    $id = intval($args['id']);
    $returnVal = null;
    if ($this->dbHelper->doesIdExist("users","user_pk", $id)) {
      $this->dbHelper->deleteUser($id);
      $returnVal = new Info(202, "User will be deleted", InfoType::INFO);
    } else {
      $returnVal = new Info(404, "UserId doesn't exist", InfoType::ERROR);
    }
    return $response->withJson($returnVal->getArray(), $returnVal->getCode());
  }

  /**
   * Get information of current user
   *
   * @param ServerRequestInterface $request
   * @param ResponseInterface $response
   * @param array $args
   * @return ResponseInterface
   */
  public function getCurrentUser($request, $response, $args)
  {
    $user = $this->dbHelper->getUsers($this->restHelper->getUserId());
    return $response->withJson($user[0], 200);
  }
}
