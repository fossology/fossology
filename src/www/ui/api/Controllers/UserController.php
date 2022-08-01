<?php
/*
 SPDX-FileCopyrightText: © 2018, 2021 Siemens AG
 Author: Gaurav Mishra <mishra.gaurav@siemens.com>

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @file
 * @brief Controller for user queries
 */

namespace Fossology\UI\Api\Controllers;

use Fossology\UI\Api\Helper\ResponseHelper;
use Psr\Http\Message\ServerRequestInterface;
use Fossology\UI\Api\Models\Info;
use Fossology\UI\Api\Models\InfoType;
use Fossology\Lib\Dao\UserDao;

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
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
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
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
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
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   */
  public function getCurrentUser($request, $response, $args)
  {
    $user = $this->dbHelper->getUsers($this->restHelper->getUserId())[0];
    $userDao = $this->restHelper->getUserDao();
    $defaultGroup = $userDao->getUserAndDefaultGroupByUserName($user["name"])["group_name"];
    $user["default_group"] = $defaultGroup;
    return $response->withJson($user, 200);
  }
}
