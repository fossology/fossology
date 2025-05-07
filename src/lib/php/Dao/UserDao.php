<?php
/*
 SPDX-FileCopyrightText: © 2014-2017 Siemens AG
 Author: J. Najjar, S. Weber, A. Wührl
 SPDX-FileCopyrightText: © 2021-2022 Orange
 Contributors: Piotr Pszczola, Bartlomiej Drozdz

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Dao;

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Db\DbManager;
use Monolog\Logger;
use Symfony\Component\HttpFoundation\Session\Session;

class UserDao
{
  const USER = 0;
  const ADMIN = 1;
  const ADVISOR = 2;

  const USER_ACTIVE_STATUS = 'active';

  const SUPER_USER = 'fossy';

  /* @var DbManager */
  private $dbManager;
  /* @var Logger */
  private $logger;
  /** @var Session */
  private $session;

  function __construct(DbManager $dbManager, Logger $logger)
  {
    $this->dbManager = $dbManager;
    $this->logger = $logger;

    global $container;
    $this->session = $container->get('session');
  }

  /**
   * @return array
   */
  public function getUserChoices($groupId=null)
  {
    if (empty($groupId)) {
      $groupId = Auth::getGroupId();
    }
    $userChoices = array();
    $statementN = __METHOD__;
    $sql = "SELECT user_pk, user_name, user_desc FROM users LEFT JOIN group_user_member AS gum ON users.user_pk = gum.user_fk"
            . " WHERE gum.group_fk = $1 AND users.user_status='active'";
    $this->dbManager->prepare($statementN, $sql);
    $res = $this->dbManager->execute($statementN, array($groupId));
    while ($rw = $this->dbManager->fetchArray($res)) {
      $userChoices[$rw['user_pk']] = $rw['user_desc'] . ' (' . $rw['user_name'] . ')';
    }
    $this->dbManager->freeResult($res);
    return $userChoices;
  }

  /**
   * @brief get array of groups that this user has admin access to
   * @param int $userId
   * @return array in the format {group_pk=>group_name, group_pk=>group_name, ...}
   */
  function getAdminGroupMap($userId,$userLevel=0)
  {
    if ($userLevel == PLUGIN_DB_ADMIN) {
      return $this->dbManager->createMap('groups', 'group_pk', 'group_name');
    }
    $sql = "SELECT group_pk, group_name FROM groups, group_user_member"
            . " WHERE group_pk=group_fk AND user_fk=$1 AND group_perm=$2";
    $param = array($userId,self::ADMIN);
    $this->dbManager->prepare($stmt=__METHOD__, $sql);
    $res = $this->dbManager->execute($stmt,$param);
    $groupMap = array();
    while ($row = $this->dbManager->fetchArray($res)) {
      $groupMap[$row['group_pk']] = $row['group_name'];
    }
    $this->dbManager->freeResult($res);
    return $groupMap;
  }

  /**
   * @brief get array of groups that this user has admin access to
   * @param int $userId
   * @return array in the format {group_pk=>group_name, group_pk=>group_name, ...}
   */
  function getUserGroupMap($userId)
  {
    $sql = "SELECT group_pk, group_name FROM groups, group_user_member WHERE group_pk=group_fk AND user_fk=$1";
    $this->dbManager->prepare($stmt=__METHOD__, $sql);
    $res = $this->dbManager->execute($stmt,array($userId));
    $groupMap = array();
    while ($row = $this->dbManager->fetchArray($res)) {
      $groupMap[$row['group_pk']] = $row['group_name'];
    }
    $this->dbManager->freeResult($res);
    return $groupMap;
  }

  /**
   * @brief get array of groups that this user has admin access to
   * @param int $userId
   * @return array in the format {group_pk=>group_name, group_pk=>group_name, ...}
   */
  function getDeletableAdminGroupMap($userId,$userLevel=0)
  {
    if ($userLevel == PLUGIN_DB_ADMIN) {
      $sql = "SELECT group_pk, group_name FROM groups LEFT JOIN users ON group_name=user_name "
           . "WHERE user_name IS NULL";
      $param = array();
    } else {
      $sql = "SELECT group_pk, group_name FROM groups LEFT JOIN users ON group_name=user_name "
           . " INNER JOIN group_user_member ON group_pk=group_user_member.group_fk AND user_fk=$1 AND group_perm=$2 "
           . "WHERE user_name IS NULL";
      $param = array($userId,1);
    }
    $this->dbManager->prepare($stmt=__METHOD__.".$userLevel", $sql);
    $res = $this->dbManager->execute($stmt,$param);
    $groupMap = array();
    while ($row = $this->dbManager->fetchArray($res)) {
      $groupMap[$row['group_pk']] = $row['group_name'];
    }
    $this->dbManager->freeResult($res);
    return $groupMap;
  }


  /**
   * @brief Delete a group (for constraint, see http://www.fossology.org/projects/fossology/wiki/GroupsPerms )
   * @param $groupId
   * @throws \Exception
   * @return bool true on success
   */
  function deleteGroup($groupId)
  {
    if (!$this->session->isStarted()) {
      $this->session->setName('Login');
      $this->session->start();
    }
    $groupArray = $this->dbManager->getSingleRow('SELECT group_pk, group_name FROM groups WHERE group_pk=$1',
            array($groupId),__METHOD__.'.exists');
    if ($groupArray===false) {
      throw new \Exception( _("Group does not exist.  Not deleted.") );
    }
    $groupConstraint = $this->dbManager->getSingleRow('SELECT count(*) cnt FROM users WHERE user_name=$1',
            array($groupArray['group_name']),__METHOD__.'.contraint');
    if ($groupConstraint['cnt']) {
      throw new \Exception( _("Group must not be deleted due to name constraint.") );
    }
    if ($_SESSION[Auth::USER_LEVEL] != PLUGIN_DB_ADMIN) {
      $userId = Auth::getUserId();
      $adminLevel = $this->dbManager->getSingleRow("SELECT count(*) cnt FROM group_user_member WHERE group_fk=$1 and user_fk=$2 and group_perm=1",
              array($groupId,$userId),__METHOD__.'.admin_lvl');
      if ($adminLevel['cnt']< 1) {
        $text = _("Permission Denied.");
        throw new \Exception($text);
      }
    }

    $this->dbManager->begin();
    $this->dbManager->getSingleRow("DELETE FROM perm_upload WHERE group_fk=$1",array($groupId),__METHOD__.'.perm_upload');
    $this->dbManager->getSingleRow("DELETE FROM group_user_member WHERE group_fk=$1",array($groupId),__METHOD__.'.gum');
    $this->dbManager->getSingleRow("UPDATE users SET new_upload_group_fk=NULL, new_upload_perm=NULL WHERE new_upload_group_fk=$1",
            array($groupId),__METHOD__.'.upload_group');
    $newGroupIdStmt = '(SELECT group_fk FROM group_user_member WHERE user_fk=user_pk LIMIT 1)';
    $this->dbManager->getSingleRow("UPDATE users SET group_fk=$newGroupIdStmt WHERE group_fk=$1",
            array($groupId),__METHOD__.'.active_group');
    $this->dbManager->getSingleRow("DELETE FROM groups WHERE group_pk=$1",array($groupId),__METHOD__.'.delete');
    $this->dbManager->commit();

    $newGroupId= $this->dbManager->getSingleRow("SELECT group_fk FROM users WHERE user_pk=$1",
      array($this->session->get(AUTH::USER_ID)), __METHOD__.'.group_after_update');
    $_SESSION[Auth::GROUP_ID] = $newGroupId['group_fk'];
    $this->session->set(Auth::GROUP_ID, $newGroupId['group_fk']);

    return true;
  }

  function updateUserTable()
  {
    $statementBasename = __FUNCTION__;

    /* No users with no seed and no perm -- make them read-only */
    $this->dbManager->getSingleRow("UPDATE users SET user_perm = $1 WHERE user_perm IS NULL;",
            array(PLUGIN_DB_READ),
            $statementBasename . '.setDefaultPermission');
    /* There must always be at least one default user. */
    $defaultUser = $this->getUserByName('Default User');

    if (empty($defaultUser['user_name'])) {
      $level = PLUGIN_DB_NONE;
      $this->dbManager->getSingleRow("
        INSERT INTO users (user_name,user_desc,user_seed,user_pass,user_perm,user_email,root_folder_fk)
          VALUES ('Default User','Default User when nobody is logged in','Seed','Pass', $1,NULL,1);",
          array($level), $statementBasename . '.createDefaultUser');
    }
    /* There must always be at least one user with user-admin access.
     If he does not exist, make it SUPER_USER with the same password. */
    $perm = PLUGIN_DB_ADMIN;
    $row = $this->getUserByPermission($perm);
    if (empty($row['user_name'])) {
      /* No user with PLUGIN_DB_ADMIN access. */
      $options = array('cost' => 10);
      $hash = password_hash(self::SUPER_USER, PASSWORD_DEFAULT, $options);
      $row0 = $this->getUserByName(self::SUPER_USER);

      if (empty($row0['user_name'])) {
        $this->dbManager->getSingleRow("
          INSERT INTO users (user_name, user_desc, user_seed, user_pass, user_perm, user_email, email_notify, root_folder_fk)
            VALUES ($1,'Default Administrator',$2, $3, $4, $1,'y',1)",
            array(self::SUPER_USER, 'Seed', $hash, $perm), $statementBasename . '.createDefaultAdmin');
      } else {
        $this->dbManager->getSingleRow("UPDATE users SET user_perm = $1, email_notify = 'y'," .
            " user_email=$2 WHERE user_name =$2",
            array($perm, self::SUPER_USER), $statementBasename . '.updateDefaultUserToDefaultAdmin');
      }
      $row = $this->getUserByPermission($perm);
    }

    return empty($row['user_name']) ? 1 : 0;
  }

  /**
   * @param $userName
   * @return array
   */
  public function getUserByName($userName)
  {
    return $this->dbManager->getSingleRow("SELECT * FROM users WHERE user_name = $1", array($userName), __FUNCTION__);
  }

  /**
   * @param $userPk
   * @return array
   */
  public function getUserByPk($userPk)
  {
    return $this->dbManager->getSingleRow("SELECT * FROM users WHERE user_pk = $1", array($userPk), __FUNCTION__);
  }

  /**
   * @param $groupName
   * @return array
   */
  public function getGroupIdByName($groupName)
  {
    $row = $this->dbManager->getSingleRow("SELECT * FROM groups WHERE group_name = $1", array($groupName), __FUNCTION__);
    return $row['group_pk'];
  }

  /**
   * @param $permission
   * @return array
   */
  private function getUserByPermission($permission)
  {
    return $this->dbManager->getSingleRow("SELECT * FROM users WHERE user_perm = $1", array($permission), __FUNCTION__);
  }

  /**
   * @param int $userId
   * @param int $groupId
   */
  public function setDefaultGroupMembership($userId, $groupId)
  {
    $this->dbManager->getSingleRow("UPDATE users SET group_fk=$2 WHERE user_pk=$1",
        array($userId, $groupId), __FUNCTION__);
  }

  public function getUserAndDefaultGroupByUserName(&$userName, $oauth=false)
  {
    $searchEmail = " ";
    $statement = __METHOD__;
    if ($oauth) {
      $searchEmail = " OR user_email=$1";
      $statement .= "oauth";
    }
    $userRow = $this->dbManager->getSingleRow(
        "SELECT users.*,group_name FROM users LEFT JOIN groups ON group_fk=group_pk WHERE user_name=$1$searchEmail",
        array($userName), $statement);
    if (empty($userRow)) {
      throw new \Exception('invalid user name');
    }
    if ($oauth) {
      $userName = $userRow['user_name'];
    }
    $userRow['oauth'] = $oauth;
    if ($userRow['group_fk']) {
      return $userRow;
    }
    $groupRow = $this->fixDefaultGroup($userRow['user_pk'],$userName);
    $this->setDefaultGroupMembership($userRow['user_pk'], $groupRow['group_fk']);
    $userRow['group_fk'] = $groupRow['group_fk'];
    $userRow['group_name'] = $groupRow['group_name'];
    return $userRow;
  }

  /**
   * @param string $userName
   * @return boolean true if user status=active
   */
  public function isUserActive($userName)
  {
    $row = $this->dbManager->getSingleRow("SELECT user_status FROM users WHERE user_name=$1",
        array($userName), __METHOD__);
    return $row!==false && ($row['user_status']==self::USER_ACTIVE_STATUS);
  }

  /**
   * @param int $userId
   * @return boolean true if user status=active
   */
  public function isUserIdActive($userId)
  {
    $row = $this->dbManager->getSingleRow("SELECT user_status FROM users WHERE user_pk=$1",
        array($userId), __METHOD__);
    return $row!==false && ($row['user_status']==self::USER_ACTIVE_STATUS);
  }

  /**
   * @param int $userId
   */
  public function updateUserLastConnection($userId)
  {
    $this->dbManager->getSingleRow("UPDATE users SET last_connection=now() WHERE user_pk=$1",
        array($userId), __FUNCTION__);
  }

  /**
   * @param int $userId
   * @param string $groupName
   * @return array with keys 'group_fk', 'group_name'
   */
  private function fixDefaultGroup($userId, $groupName)
  {
    $groupRow = $this->dbManager->getSingleRow(
          "SELECT group_fk,group_name FROM group_user_member LEFT JOIN groups ON group_fk=group_pk WHERE user_fk=$1",
          array($userId), __FUNCTION__.".getGroup");
    if ($groupRow) {
      return $groupRow;
    }

    $groupId = $this->getGroupIdByName($groupName);
    if (empty($groupId)) {
      $groupId = $this->addGroup($groupName);
      $this->addGroupMembership($groupId, $userId);
    }

    return array('group_fk'=>$groupId,'group_name'=>$groupName);
  }

  public function isAdvisorOrAdmin($userId, $groupId)
  {
    $row = $this->dbManager->getSingleRow("SELECT group_perm FROM group_user_member WHERE user_fk=$1 AND group_fk=$2",
        array($userId, $groupId), __METHOD__);
    return $row!==false && ($row['group_perm']==self::ADVISOR || $row['group_perm']==self::ADMIN);
  }

  /**
   * @param string $groupName raw group name as entered by the user
   * @return int $groupId
   * @throws \Exception
   */
  public function addGroup($groupName)
  {
    if (empty($groupName)) {
      throw new \Exception(_("Error: Group name must be specified."));
    }

    $groupAlreadyExists = $this->dbManager->getSingleRow("SELECT group_pk, group_name FROM groups WHERE LOWER(group_name)=LOWER($1)",
            array($groupName),
            __METHOD__.'.gExists');
    if ($groupAlreadyExists) {
      throw new \Exception(_("Group exists. Try different Name, Group-Name checking is case-insensitive and Duplicate not allowed"));
    }

    $this->dbManager->insertTableRow('groups', array('group_name'=>$groupName));
    $groupNowExists = $this->dbManager->getSingleRow("SELECT * FROM groups WHERE group_name=$1",
            array($groupName),
            __METHOD__.'.gNowExists');
    if (!$groupNowExists) {
      throw new \Exception(_("Failed to create group"));
    }
    return $groupNowExists['group_pk'];
  }

  public function addGroupMembership($groupId, $userId, $groupPerm=1)
  {
    $this->dbManager->insertTableRow('group_user_member',
            array('group_fk'=>$groupId,'user_fk'=>$userId,'group_perm'=>$groupPerm));
  }

  /**
   * @param int $userId
   * @return string
   */
  public function getUserName($userId)
  {
    $userRow = $this->dbManager->getSingleRow("SELECT user_name FROM users WHERE user_pk=$1",array($userId),__METHOD__);
    if (!$userRow) {
      throw new \Exception('unknown user with id='.$userId);
    }
    return $userRow['user_name'];
  }

  /**
   * @param $groupId
   * @return array
   */
  public function getGroupNameById($groupId)
  {
    $groupRow =  $this->dbManager->getSingleRow("SELECT group_name FROM groups WHERE group_pk = $1",array($groupId),__METHOD__);
    if (empty($groupRow)) {
      throw new \Exception('Error: GroupId ='. $groupId .' not a member of a valid group.');
    }
    return $groupRow['group_name'];
  }

  /**
   * @param int $userId
   * @return string
   */
  public function getUserEmail($userId)
  {
    $userRow = $this->dbManager->getSingleRow("SELECT user_email FROM users WHERE user_pk=$1",array($userId),__METHOD__);
    if (!$userRow) {
      throw new \Exception('unknown user with id='.$userId);
    }
    return $userRow['user_email'];
  }

  /**
   * Get all users from users table
   * @return array
   */
  public function getAllUsers()
  {
    return $this->dbManager->getRows("SELECT * FROM users ORDER BY user_name;");
  }

  /**
   * @param int $groupId
   * @param string $newGroupName
   */
  function editGroup($groupId, $newGroupName)
  {
    $this->dbManager->getSingleRow('UPDATE groups SET group_name=$2 WHERE group_pk=$1;',
            array($groupId, $newGroupName),__METHOD__.'.UpdateEditGroup');
  }
}
