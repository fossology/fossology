<?php
/***************************************************************
 Copyright (C) 2021 Orange
 Copyright (C) 2022 Samuel Dushimimana <dushsam100@gmail.com>
 Authors: Piotr Pszczola <piotr.pszczola@orange.com>

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
use Fossology\UI\Api\Helper\RestHelper;
use Fossology\UI\Api\Models\Info;
use Fossology\UI\Api\Models\InfoType;
use Fossology\UI\Api\Models\Group;
use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\UserDao;
use Fossology\UI\Api\Helper\ResponseHelper;

/**
 * @class GroupController
 * @brief Controller for Group model
 */
class GroupController extends RestController
{

   /**
   * Get list of Groups
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   */
  public function getGroups($request, $response, $args)
  {
    $userDao = $this->restHelper->getUserDao();
    $groups = array();
    if (Auth::isAdmin()) {
      $groups = $userDao->getAdminGroupMap($this->restHelper->getUserId(),Auth::PERM_ADMIN);
    } else {
      $groups = $userDao->getUserGroupMap($this->restHelper->getUserId());
    }
    $groupList = array();
    foreach ($groups as $key => $value) {
      $groupObject = new Group($key,$value);
      $groupList[] = $groupObject->getArray();
    }
    return $response->withJson($groupList, 200);
  }

  /**
   * Create a given group
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   */
  public function createGroup($request, $response, $args)
  {
    $groupName = $request->getHeaderLine("name");
    $returnVal = null;
    if (!empty($request->getHeaderLine("name"))) {
      try
      {
        /* @var $userDao UserDao */
        $userDao = $this->restHelper->getUserDao();
        $groupId = $userDao->addGroup($groupName);
        $userDao->addGroupMembership($groupId, $this->restHelper->getUserId());
        $returnVal = new Info(200, "Group $groupName added.", InfoType::INFO);
      } catch (\Exception $e) {
        $returnVal = new Info(500, "ERROR - something went wrong. Details: ". $e->getMessage(), InfoType::ERROR);
      }
    } else {
      $returnVal = new Info(400, "ERROR - no group name provided", InfoType::ERROR);
    }
    return $response->withJson($returnVal->getArray(), $returnVal->getCode());
  }

  /**
   * Delete a given group
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   */
  public function deleteGroup($request, $response, $args)
  {
    $returnVal = null;

    if (!empty($args['id'])) {

      $userId = $this->restHelper->getUserId();

      /** @var UserDao $userDao */
      $userDao = $this->restHelper->getUserDao();
      $groupMap = $userDao->getDeletableAdminGroupMap($userId,
        $_SESSION[Auth::USER_LEVEL]);
      $groupId = intval($args['id']);

      if ($this->dbHelper->doesIdExist("groups", "group_pk", $groupId)) {
        try {
          $userDao->deleteGroup($groupId);
          $returnVal = new Info(202, "User Group will be deleted", InfoType::INFO);
          unset($groupMap[$groupId]);
        } catch (\Exception $e) {
          $returnVal = new Info(400, $e->getMessage(), InfoType::ERROR);
        }
      } else {
        $returnVal = new Info(404, "Group id not found!", InfoType::ERROR);
      }
    } else {
      $returnVal = new Info(400, "ERROR - no group id provided", InfoType::ERROR);
    }

    return $response->withJson($returnVal->getArray(), $returnVal->getCode());
  }

   /**
   * Delete a given group member
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   */
  public function deleteGroupMember($request, $response, $args)
  {
    $returnVal = null;
    $dbManager = $this->dbHelper->getDbManager();

    $group_pk = intval($args['id']);
    $user_pk = intval($args['uid']);

    if (!$this->dbHelper->doesIdExist("groups", "group_pk", $group_pk)) {
      $returnVal = new Info(404, "Group id not found!", InfoType::ERROR);
    } else if (!$this->dbHelper->doesIdExist("users", "user_pk", $user_pk)) {
      $returnVal = new Info(404, "User id not found!", InfoType::ERROR);
    } else {
      try {
        $dbManager->prepare($stmt = __METHOD__ . ".getByGroupAndUser",
          "SELECT group_user_member_pk FROM group_user_member WHERE group_fk=$1 AND user_fk=$2");
        $fetchResult = $dbManager->execute($stmt, array($group_pk, $user_pk));
        $fetchResult = $dbManager->fetchAll($fetchResult);
        $dbManager->freeResult($fetchResult);
        if (!empty($fetchResult)) {
          $group_user_member_pk = $fetchResult[0]['group_user_member_pk'];
          $adminGroupUsers = $this->restHelper->getPlugin('group_manage_users');
          $adminGroupUsers->updateGUMPermission($group_user_member_pk, -1);
          $returnVal = new Info(200, "User will be removed from group.", InfoType::INFO);
        } else {
          $returnVal = new Info(404, "Not a member !", InfoType::ERROR);
        }
      } catch (\Exception $e) {
        $returnVal = new Info(500, $e->getMessage(), InfoType::ERROR);
      }
    }

    return $response->withJson($returnVal->getArray(), $returnVal->getCode());
  }
}
