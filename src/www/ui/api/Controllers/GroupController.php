<?php
/*
 SPDX-FileCopyrightText: © 2021 Orange
 SPDX-FileCopyrightText: © 2022 Samuel Dushimimana <dushsam100@gmail.com>
 Author: Piotr Pszczola <piotr.pszczola@orange.com>

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @file
 * @brief Controller for user queries
 */

namespace Fossology\UI\Api\Controllers;

use Fossology\UI\Api\Models\User;
use Fossology\UI\Api\Models\UserGroupMember;
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
    $user_pk = intval($args['userId']);

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

  /**
   * Get a list of groups that can be deleted
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   */
  public function getDeletableGroups($request, $response, $args)
  {
    $userId = $this->restHelper->getUserId();
    /* @var $userDao UserDao */
    $userDao = $this->restHelper->getUserDao();
    $groupMap = $userDao->getDeletableAdminGroupMap($userId,
      $_SESSION[Auth::USER_LEVEL]);

    $groupList = array();
    foreach ($groupMap as $key => $value) {
      $groupObject = new Group($key, $value);
      $groupList[] = $groupObject->getArray();
    }
    return $response->withJson($groupList, 200);
  }

  /**
   * Get users with their roles from a given group
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   */
  public function getGroupMembers($request, $response, $args)
  {
    $userId = $this->restHelper->getUserId();
    /** @var UserDao */
    $userDao = $this->restHelper->getUserDao();
    $groupMap = $userDao->getAdminGroupMap($userId, $_SESSION[Auth::USER_LEVEL]);
    $res = null;

    if (empty($groupMap)) {
      $res = new Info(400, "You have no permission to manage any group.", InfoType::ERROR);
    } else {

      /**
       * Get the group id from the params *
       **/
      $groupId = intval($args['id']);

      /**
       * The query to get the list of users with corresponding roles from the group. *
       **/
      $dbManager = $this->dbHelper->getDbManager();

      $stmt = __METHOD__ . "getUsersWithGroup";
      $dbManager->prepare($stmt, "SELECT user_pk, group_perm
         FROM users INNER JOIN group_user_member gum ON gum.user_fk=users.user_pk AND gum.group_fk=$1;");

      $result = $dbManager->execute($stmt, array($groupId));
      $usersWithGroup = $dbManager->fetchAll($result);

      /**
       * Convert back fields [user_pk , group_user_member_pk ,group_perm ] from String to Integer*
       **/
      $memberList = array();
      foreach ($usersWithGroup as $record) {
        $user = $this->dbHelper->getUsers($record['user_pk']);
        $userGroupMember = new UserGroupMember($user[0],$record["group_perm"]);
        $memberList[] = $userGroupMember->getArray();
      }
      $dbManager->freeResult($result);

      return $response->withJson($memberList, 200);
    }
    return $response->withJson($res->getArray(), $res->getCode());
  }


  /**
   * Add a user to a group
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   */
  public function addMember($request, $response, $args)
  {
    $returnVal = null;
    $dbManager = $this->dbHelper->getDbManager();

    $body = $this->getParsedBody($request);

    $group_pk = intval($args['id']);
    $newuser = intval($args['userId']);
    $newperm = intval($body['perm']);

    if (!isset($newperm)) {
      $returnVal = new Info(400, "ERROR - no default permission provided", InfoType::ERROR);
    } else if (!$this->dbHelper->doesIdExist("groups", "group_pk", $group_pk)) {
      $returnVal = new Info(404, "Group id not found!", InfoType::ERROR);
    } else if (!$this->dbHelper->doesIdExist("users", "user_pk", $newuser)) {
      $returnVal = new Info(404, "User id not found ! ".$newuser, InfoType::ERROR);
    } else if ($newperm < 0 || $newperm > 2) {
      $returnVal = new Info(400, "ERROR - Permission should be in range [0-2]", InfoType::ERROR);
    } else {
      try {
        $stmt = __METHOD__ . ".getByGroupAndUser";
        $sql = "SELECT group_user_member_pk FROM group_user_member WHERE group_fk=$1 AND user_fk=$2;";
        $fetchResult = $dbManager->getSingleRow($sql, [$group_pk, $newuser], $stmt);

        // Do not produce duplicate
        if (empty($fetchResult)) {
          $dbManager->prepare($stmt = __METHOD__ . ".insertGUP",
            "INSERT INTO group_user_member (group_fk, user_fk, group_perm) VALUES ($1,$2,$3)");
          $dbManager->freeResult(
            $dbManager->execute($stmt, array($group_pk, $newuser, $newperm)));

          $returnVal = new Info(200, "User will be added to group.", InfoType::INFO);
        } else {
          $returnVal = new Info(400, "Already a member!", InfoType::ERROR);
        }
      } catch (\Exception $e) {
        $returnVal = new Info(500, $e->getMessage(), InfoType::ERROR);
      }
    }
    return $response->withJson($returnVal->getArray(), $returnVal->getCode());
  }
}
