<?php
/***********************************************************
 * Copyright (C) 2014 Siemens AG
 * Author: J.Najjar
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 ***********************************************************/

namespace Fossology\Lib\Dao;


use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Util\Object;
use Monolog\Logger;

class UserDao extends Object
{
  const USER=0;
  const ADMIN=1;
  const ADVISOR=2;

  /* @var DbManager */
  private $dbManager;
  /* @var Logger */
  private $logger;

  function __construct(DbManager $dbManager, Logger $logger)
  {
    $this->dbManager = $dbManager;
    $this->logger = $logger;
  }

  /**
   * @return array
   */
  public function getUserChoices()
  {
    $userChoices = array();
    $statementN = __METHOD__;

    $this->dbManager->prepare($statementN, "select user_pk, user_name from users left join group_user_member as GUM on users.user_pk = GUM.user_fk where GUM.group_fk = $1");
    $res = $this->dbManager->execute($statementN, array($_SESSION['GroupId']));
    while ($rw = $this->dbManager->fetchArray($res))
    {
      $userChoices[$rw['user_pk']] = $rw['user_name'];
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
    if ($userLevel == PLUGIN_DB_ADMIN)
    {
      return $this->dbManager->createMap('groups', 'group_pk', 'group_name');
    }
    $sql = "SELECT group_pk, group_name FROM groups, group_user_member"
            . " WHERE group_pk=group_fk AND user_fk=$1 AND group_perm=$2";
    $param = array($userId,self::ADMIN);
    $this->dbManager->prepare($stmt=__METHOD__, $sql);
    $res = $this->dbManager->execute($stmt,$param);
    $groupMap = array();
    while($row = $this->dbManager->fetchArray($res))
    {
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
    while($row = $this->dbManager->fetchArray($res))
    {
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
    if ($userLevel == PLUGIN_DB_ADMIN)
    {
      $sql = "SELECT group_pk, group_name FROM groups LEFT JOIN users ON group_name=user_name "
           . "WHERE user_name IS NULL";
      $param = array();
    }
    else{
      $sql = "SELECT group_pk, group_name FROM groups LEFT JOIN users ON group_name=user_name "
           . " INNER JOIN group_user_member ON group_pk=group_user_member.group_fk AND user_fk=$1 AND group_perm=$2 "
           . "WHERE user_name IS NULL";
      $param = array($userId,1);
    }
    $this->dbManager->prepare($stmt=__METHOD__.".$userLevel", $sql);
    $res = $this->dbManager->execute($stmt,$param);
    $groupMap = array();
    while($row = $this->dbManager->fetchArray($res))
    {
      $groupMap[$row['group_pk']] = $row['group_name'];
    }
    $this->dbManager->freeResult($res);
    return $groupMap;
  }


  /**
   * @brief Delete a group (for constraint, see http://www.fossology.org/projects/fossology/wiki/GroupsPerms )
   * @param $groupId
   * Returns true on success
   * @throws \Exception
   * @return bool
   */
  function deleteGroup($groupId) 
  {
    $groupArray = $this->dbManager->getSingleRow('SELECT group_pk, group_name FROM groups WHERE group_pk=$1',
            array($groupId),__METHOD__.'.exists');
    if ($groupArray===false)
    {
      throw new \Exception( _("Group does not exist.  Not deleted.") );
    }
    $groupConstraint = $this->dbManager->getSingleRow('SELECT count(*) cnt FROM users WHERE user_name=$1',
            array($groupArray['group_name']),__METHOD__.'.contraint');
    if ($groupConstraint['cnt'])
    {
      throw new \Exception( _("Group must not be deleted due to name constraint.") );
    }
    if ($_SESSION['UserLevel'] != PLUGIN_DB_ADMIN)
    {
      global $SysConf;
      $userId = $SysConf['auth']['UserId'];
      $adminLevel = $this->dbManager->getSingleRow("SELECT count(*) cnt FROM group_user_member WHERE group_fk=$1 and user_fk=$2 and group_perm=1",
              array($groupId,$userId),__METHOD__.'.admin_lvl');
      if ($adminLevel['cnt']< 1)
      {
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

    return true;
  }

  function updateUserTable() {
    $statementBasename = __FUNCTION__;

    $this->dbManager->getSingleRow("UPDATE users SET user_seed = $1 WHERE user_seed IS NULL;", array(rand()), $statementBasename . '.randomizeEmptySeeds');

    /* No users with no seed and no perm -- make them read-only */
    $this->dbManager->getSingleRow("UPDATE users SET user_perm = $1 WHERE user_perm IS NULL;", array(PLUGIN_DB_READ), $statementBasename . '.setDefaultPermission');

    /* There must always be at least one default user. */
    $row = $this->getUserByName('Default User');

    if (empty($row['user_name']))
    {
      /* User "fossy" does not exist.  Create it. */
      /* No valid username/password */
      $Level = PLUGIN_DB_NONE;
      $this->dbManager->getSingleRow("
        INSERT INTO users (user_name,user_desc,user_seed,user_pass,user_perm,user_email,root_folder_fk)
          VALUES ('Default User','Default User when nobody is logged in','Seed','Pass', $1,NULL,1);",
          array($Level), $statementBasename . '.createDefaultUser');
    }
    /* There must always be at least one user with user-admin access.
     If he does not exist, make it user "fossy".
     If user "fossy" does not exist, add him with the default password 'fossy'. */
    $Perm = PLUGIN_DB_ADMIN;
    $row = $this->getUserByPermission($Perm);
    if (empty($row['user_name']))
    {
      /* No user with PLUGIN_DB_ADMIN access. */
      $Seed = rand() . rand();
      $Hash = sha1($Seed . "fossy");
      $row0 = $this->getUserByName('fossy');

      if (empty($row0['user_name']))
      {
        /* User "fossy" does not exist.  Create it. */
        $this->dbManager->getSingleRow("
          INSERT INTO users (user_name, user_desc, user_seed, user_pass, user_perm, user_email, email_notify, root_folder_fk)
            VALUES ('fossy','Default Administrator',$1, $2, $3, 'fossy','y',1)",
            array($Seed, $Hash, $Perm), $statementBasename . '.createDefaultAdmin');
      } else
      {
        /* User "fossy" exists!  Update it. */
        $this->dbManager->getSingleRow("UPDATE users SET user_perm = $1, email_notify = 'y'," .
            " user_email= 'fossy' WHERE user_name = 'fossy'",
            array($Perm), $statementBasename . '.updateDefaultUserToDefaultAdmin');
      }

      $row = $this->getUserByPermission($Perm);
    }

    return empty($row['user_name']) ? 1 : 0;
  }

  /**
   * @param $userName
   * @return array
   */
  private function getUserByName($userName)
  {
    return $this->dbManager->getSingleRow("SELECT * FROM users WHERE user_name = $1", array($userName), __FUNCTION__);
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
  public function setDefaultGroupMembership($userId, $groupId) {
    $this->dbManager->getSingleRow("UPDATE users SET group_fk=$2 WHERE user_pk=$1",
        array($userId, $groupId), __FUNCTION__);
  }

  public function getUserAndDefaultGroupByUserName($userName) {
    return $this->dbManager->getSingleRow(
        "SELECT users.*,group_name FROM users LEFT JOIN groups ON group_fk=group_pk WHERE user_name=$1",
        array($userName), __FUNCTION__);
  }
  
  public function isAdvisorOrAdmin($userId, $groupId)
  {
    $row = $this->dbManager->getSingleRow("SELECT group_perm FROM group_user_member WHERE user_fk=$1 AND group_fk=$2",
        array($userId, $groupId), __METHOD__);
    return $row!==false && ($row['group_perm']==self::ADVISOR || $row['group_perm']==self::ADMIN);
  }

} 