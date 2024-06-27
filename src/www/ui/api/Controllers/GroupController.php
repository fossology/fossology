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

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\UserDao;
use Fossology\UI\Api\Exceptions\HttpBadRequestException;
use Fossology\UI\Api\Exceptions\HttpErrorException;
use Fossology\UI\Api\Exceptions\HttpForbiddenException;
use Fossology\UI\Api\Exceptions\HttpNotFoundException;
use Fossology\UI\Api\Helper\ResponseHelper;
use Fossology\UI\Api\Models\Group;
use Fossology\UI\Api\Models\Info;
use Fossology\UI\Api\Models\InfoType;
use Fossology\UI\Api\Models\ApiVersion;
use Fossology\UI\Api\Models\UserGroupMember;
use Psr\Http\Message\ServerRequestInterface;

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
   * @throws HttpErrorException
   */
  public function createGroup($request, $response, $args)
  {
    $groupName = '';
    if (ApiVersion::getVersion($request) == ApiVersion::V2) {
      $queryParams = $request->getQueryParams();
      $groupName = $queryParams['name'] ?? '';
    } else {
      $groupName = $request->getHeaderLine('name') ?: '';
    }
    if (empty($groupName)) {
      throw new HttpBadRequestException("ERROR - no group name provided");
    }
    $userDao = $this->restHelper->getUserDao();
    $groupId = $userDao->addGroup($groupName);
    $userDao->addGroupMembership($groupId, $this->restHelper->getUserId());
    $returnVal = new Info(200, "Group $groupName added.", InfoType::INFO);
    return $response->withJson($returnVal->getArray(), $returnVal->getCode());
  }

  /**
   * Delete a given group
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   * @throws HttpErrorException
   */
  public function deleteGroup($request, $response, $args)
  {
    $apiVerison = ApiVersion::getVersion($request);
    if (empty($args['pathParam'])) {
      throw new HttpBadRequestException("ERROR - No group name or id provided");
    }
    $userId = $this->restHelper->getUserId();

    /** @var UserDao $userDao */
    $userDao = $this->restHelper->getUserDao();
    $groupMap = $userDao->getDeletableAdminGroupMap($userId,
      $_SESSION[Auth::USER_LEVEL]);
    $groupId = null;
    if ($apiVerison == ApiVersion::V2) {
      $groupName = $args['pathParam'];
      $groupId = intval($userDao->getGroupIdByName($groupName));
    } else {
      $groupId = intval($args['pathParam']);
    }

    if (!$this->dbHelper->doesIdExist("groups", "group_pk", $groupId)) {
      throw new HttpNotFoundException("Group id not found!");
    }
    try {
      $userDao->deleteGroup($groupId);
      $returnVal = new Info(202, "User Group will be deleted", InfoType::INFO);
      unset($groupMap[$groupId]);
    } catch (\Exception $e) {
      throw new HttpBadRequestException($e->getMessage(), $e);
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
   * @throws HttpErrorException
   */
  public function deleteGroupMember($request, $response, $args)
  {
    $apiVersion = ApiVersion::getVersion($request);
    $dbManager = $this->dbHelper->getDbManager();

    $user_pk = null;
    $group_pk = null;
    if ($apiVersion == ApiVersion::V2) {
      $user_pk = intval($this->restHelper->getUserDao()->getUserByName($args['userPathParam'])['user_pk']);
      $group_pk = intval($this->restHelper->getUserDao()->getGroupIdByName($args['pathParam']));
    } else {
      $user_pk = intval($args['userPathParam']);
      $group_pk = intval($args['pathParam']);
    }

    $userIsAdmin = Auth::isAdmin();
    $userHasGroupAccess = $this->restHelper->getUserDao()->isAdvisorOrAdmin(
      $this->restHelper->getUserId(), $group_pk);

    if (!$this->dbHelper->doesIdExist("groups", "group_pk", $group_pk)) {
      throw new HttpNotFoundException("Group id not found!");
    }
    if (!$this->dbHelper->doesIdExist("users", "user_pk", $user_pk)) {
      throw new HttpNotFoundException("User id not found!");
    }
    if (! $userIsAdmin && ! $userHasGroupAccess) {
      throw new HttpForbiddenException("Not advisor or admin of the group. " .
        "Can not process request.");
    }
    $fetchResult = $dbManager->getSingleRow(
      "SELECT group_user_member_pk FROM group_user_member " .
      "WHERE group_fk=$1 AND user_fk=$2", [$group_pk, $user_pk],
      __METHOD__ . ".getByGroupAndUser");
    if (empty($fetchResult)) {
      throw new HttpNotFoundException("Not a member!");
    }
    $group_user_member_pk = $fetchResult['group_user_member_pk'];
    /** @var \Fossology\UI\Page\AdminGroupUsers $adminGroupUsers */
    $adminGroupUsers = $this->restHelper->getPlugin('group_manage_users');
    $adminGroupUsers->updateGUMPermission($group_user_member_pk, -1,$dbManager);
    $returnVal = new Info(200, "User will be removed from group.", InfoType::INFO);
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
   * @throws HttpErrorException
   */
  public function getGroupMembers($request, $response, $args)
  {
    $apiVersion = ApiVersion::getVersion($request);
    $userId = $this->restHelper->getUserId();
    $userDao = $this->restHelper->getUserDao();
    $groupMap = $userDao->getAdminGroupMap($userId, $_SESSION[Auth::USER_LEVEL]);

    if (empty($groupMap)) {
      throw new HttpForbiddenException("You have no permission to manage any group.");
    }

    // Get the group name/id form the params and then the group Id
    $groupId = $apiVersion == ApiVersion::V2 ? intval($this->restHelper->getUserDao()->getGroupIdByName($args['pathParam'])) : intval($args['pathParam']);

    // The query to get the list of users with corresponding roles from the group.
    $dbManager = $this->dbHelper->getDbManager();

    $stmt = __METHOD__ . "getUsersWithGroup";
    $dbManager->prepare($stmt, "SELECT user_pk, group_perm
       FROM users INNER JOIN group_user_member gum ON gum.user_fk=users.user_pk AND gum.group_fk=$1;");

    $result = $dbManager->execute($stmt, array($groupId));
    $usersWithGroup = $dbManager->fetchAll($result);

    // Convert back fields [user_pk , group_user_member_pk ,group_perm ] from String to Integer
    $memberList = array();
    foreach ($usersWithGroup as $record) {
      $user = $this->dbHelper->getUsers($record['user_pk']);
      $userGroupMember = new UserGroupMember($user[0],$record["group_perm"]);
      $memberList[] = $userGroupMember->getArray(ApiVersion::getVersion($request));
    }
    $dbManager->freeResult($result);

    return $response->withJson($memberList, 200);
  }


  /**
   * Add a user to a group
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   * @throws HttpErrorException
   */
  public function addMember($request, $response, $args)
  {
    $apiVersion = ApiVersion::getVersion($request);
    $dbManager = $this->dbHelper->getDbManager();

    $body = $this->getParsedBody($request);
    $newuser = null;
    $group_pk = null;
    if ($apiVersion == ApiVersion::V2) {
      $newuser = intval($this->restHelper->getUserDao()->getUserByName($args['userPathParam'])['user_pk']);
      $group_pk = intval($this->restHelper->getUserDao()->getGroupIdByName($args['pathParam']));
    } else {
      $group_pk = intval($args['pathParam']);
      $newuser = intval($args['userPathParam']);
    }
    $newperm = intval($body['perm']);

    $userIsAdmin = Auth::isAdmin();
    $userHasGroupAccess = $this->restHelper->getUserDao()->isAdvisorOrAdmin(
      $this->restHelper->getUserId(), $group_pk);

    if (!isset($newperm)) {
      throw new HttpBadRequestException("ERROR - no default permission provided");
    }
    if (!$this->dbHelper->doesIdExist("groups", "group_pk", $group_pk)) {
      throw new HttpNotFoundException("Group id not found!");
    }
    if (!$this->dbHelper->doesIdExist("users", "user_pk", $newuser)) {
      throw new HttpNotFoundException("User id not found!");
    }
    if ($newperm < 0 || $newperm > 2) {
      throw new HttpBadRequestException("ERROR - Permission should be in range [0-2]");
    }
    if (! $userIsAdmin && ! $userHasGroupAccess) {
      throw new HttpForbiddenException("Not advisor or admin of the group. " .
        "Can not process request.");
    }
    $stmt = __METHOD__ . ".getByGroupAndUser";
    $sql = "SELECT group_user_member_pk FROM group_user_member WHERE group_fk=$1 AND user_fk=$2;";
    $fetchResult = $dbManager->getSingleRow($sql, [$group_pk, $newuser], $stmt);

    // Do not produce duplicate
    if (!empty($fetchResult)) {
      throw new HttpBadRequestException("Already a member!");
    }
    $dbManager->prepare($stmt = __METHOD__ . ".insertGUP",
      "INSERT INTO group_user_member (group_fk, user_fk, group_perm) VALUES ($1,$2,$3)");
    $dbManager->freeResult(
      $dbManager->execute($stmt, array($group_pk, $newuser, $newperm)));

    $returnVal = new Info(200, "User will be added to group.", InfoType::INFO);
    return $response->withJson($returnVal->getArray(), $returnVal->getCode());
  }

  /**
   * Change user permission
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   * @throws HttpErrorException
   */
  public function changeUserPermission($request, $response, $args)
  {
    // Extract all  prerequisites (dbManager , user_pk , new_permission , group_pk ) for this functionality
    $apiVersion = ApiVersion::getVersion($request);
    $dbManager = $this->dbHelper->getDbManager();

    $user_pk = null;
    $group_pk = null;
    if ($apiVersion == ApiVersion::V2) {
      $user_pk = intval($this->restHelper->getUserDao()->getUserByName($args['userPathParam'])['user_pk']);
      $group_pk = intval($this->restHelper->getUserDao()->getGroupIdByName($args['pathParam']));
    } else {
      $user_pk = intval($args['userPathParam']);
      $group_pk = intval($args['pathParam']);
    }

    $newperm = intval($this->getParsedBody($request)['perm']);
    $userIsAdmin = Auth::isAdmin();
    $userHasGroupAccess = $this->restHelper->getUserDao()->isAdvisorOrAdmin(
      $this->restHelper->getUserId(), $group_pk);

    // Validate arguments

    if (!isset($newperm)) {
      throw new HttpBadRequestException("Permission should be provided");
    }
    if (!$this->dbHelper->doesIdExist("groups", "group_pk", $group_pk)) {
      throw new HttpNotFoundException("Group id not found!");
    }
    if (!$this->dbHelper->doesIdExist("users", "user_pk", $user_pk)) {
      throw new HttpNotFoundException("User id not found!");
    }
    if ($newperm < 0) {
      throw new HttpBadRequestException("Permission can not be negative");
    }
    if ($newperm > 2) {
      throw new HttpBadRequestException("Permission can not be greater than 2");
    }
    if (! $userIsAdmin && ! $userHasGroupAccess) {
      throw new HttpForbiddenException("Not advisor or admin of the group. " .
        "Can not process request.");
    }

    // Check if the relation already exists, retrieve the PK.
    // IF not, return 404 error
    $group_user_member_pk = $dbManager->getSingleRow("SELECT group_user_member_pk FROM group_user_member WHERE group_fk=$1 AND user_fk=$2",
      [$group_pk, $user_pk],
      __METHOD__ . ".getByGroupAndUser")['group_user_member_pk'];

    if (empty($group_user_member_pk)) {
      throw new HttpNotFoundException("User not part of the group");
    }
    /** @var \Fossology\UI\Page\AdminGroupUsers $adminGroupUsers */
    $adminGroupUsers = $this->restHelper->getPlugin('group_manage_users');
    $adminGroupUsers->updateGUMPermission($group_user_member_pk, $newperm,$dbManager);
    $info = new Info(202, "Permission updated successfully.", InfoType::INFO);
    return $response->withJson($info->getArray(), $info->getCode());
  }
}
