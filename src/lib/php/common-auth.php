<?php
/***********************************************************
 Copyright (C) 2011-2015 Hewlett-Packard Development Company, L.P.
 Copyright (C) 2015 Siemens AG

 This library is free software; you can redistribute it and/or
 modify it under the terms of the GNU Lesser General Public
 License version 2.1 as published by the Free Software Foundation.

 This library is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 Lesser General Public License for more details.

 You should have received a copy of the GNU Lesser General Public License
 along with this library; if not, write to the Free Software Foundation, Inc.0
 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 ***********************************************************/

/**
 * \file common-auth.php
 * \brief This file contains common authentication funtion
 */


/**
 * \brief Check if SiteMinder is enabled.
 * \return -1 if not enabled, or the users SEA if enabled
 */
function siteminder_check() {
  if (isset($_SERVER['HTTP_SMUNIVERSALID'])){
    $SEA = $_SERVER['HTTP_SMUNIVERSALID'];
    return $SEA;
  }
  return(-1);
} // siteminder_check()

/**
 * \brief check if this account is correct 
 *
 * \param &$user - user name, reference variable
 * \param &$passwd - password, reference variable
 * 
 * \return error: exit (1)
 */
function account_check(&$user, &$passwd, &$group = "")
{
  global $SysConf;
  $dbManager = $GLOBALS['container']->get('db.manager');
  /** get username/passwd from ~/.fossology.rc */
  $user_passwd_file = getenv("HOME") . "/.fossology.rc";
  if (empty($user) && empty($passwd) && file_exists($user_passwd_file)) {
    $user_passwd_array = parse_ini_file($user_passwd_file, true);

    /* get username and password from conf file */
    if(!empty($user_passwd_array) && !empty($user_passwd_array['user']))
      $user = $user_passwd_array['user'];
    if(!empty($user_passwd_array) && !empty($user_passwd_array['username']))
      $user = $user_passwd_array['username'];
    if(!empty($user_passwd_array) && !empty($user_passwd_array['group']))
      $group = $user_passwd_array['group'];
    if(!empty($user_passwd_array) && !empty($user_passwd_array['password']))
      $passwd = $user_passwd_array['password'];
  }
  /* check if the user name/passwd is valid */
  if (empty($user)) {
    /*
       $uid_arr = posix_getpwuid(posix_getuid());
       $user = $uid_arr['name'];
     */
    echo "FATAL: You should add '--username USERNAME' when running OR add 'user=USERNAME' in ~/.fossology.rc before running.\n";
    exit(1);
  }
  if (empty($passwd)) {
    echo "The user is: $user, please enter the password:\n";
    system('stty -echo');
    $passwd = trim(fgets(STDIN));
    system('stty echo');
    if (empty($passwd)) {
      echo "You entered an empty password.\n";
    }
  }

  if (!empty($user)) {
    $row = $dbManager->getSingleRow(
      "SELECT users.*, groups.group_name
       FROM users LEFT JOIN groups ON groups.group_pk = users.group_fk
       WHERE user_name = $1",
      array($user),
      __METHOD__.".lookUpUser"
    );
    if(false === $row) {
      echo "User name is invalid.\n";
      exit(1);
    }
    $userId = $row['user_pk'];
    $SysConf['auth']['UserId'] = $userId;

    if (empty($group)) {
      $group = $row['group_name'];
      $groupId = $row['group_fk'];
    } else {
      $rowGroup = $dbManager->getSingleRow(
        "SELECT group_pk
        FROM group_user_member INNER JOIN groups ON groups.group_pk = group_user_member.group_fk
        WHERE user_fk = $1 AND group_name = $2",
        array($userId, $group),
        __METHOD__.".lookUpGroup"
      );
      if (false === $rowGroup) {
        echo "User is not in group.\n";
        exit(1);
      }
      $groupId = $rowGroup['group_pk'];
    }
    $SysConf['auth']['GroupId'] = $groupId;
    if (empty($groupId)) {
      echo "Group not found.\n";
      exit(1);
    }

    if (!empty($row['user_seed']) && !empty($row['user_pass'])) {
      $passwd_hash = sha1($row['user_seed'] . $passwd);
      if (strcmp($passwd_hash, $row['user_pass']) != 0) {
        echo "User name or password is invalid.\n";
        exit(1);
      }
    }
  }
  return $userId;
}

/**
 * \brief check if the user has the permission to read the 
 * copyright/license/etc information of this upload
 * 
 * \param $upload - upload id
 * \param $user - user name
 *
 * \return 1: has the permission; 0: no permission
 */
function read_permission($upload, $user)
{
  global $PG_CONN;
  $ADMIN_PERMISSION = 10;

  /** check if the user if the owner of this upload */
  $SQL = "SELECT * FROM upload where upload_pk = $upload and user_fk = (SELECT user_pk from users where user_name = '$user');";
  $result = pg_query($PG_CONN, $SQL);
  DBCheckResult($result, $SQL, __FILE__, __LINE__);
  $row = pg_fetch_assoc($result);
  pg_free_result($result);
  if(!empty($row)) {
    return 1;
  }

  /** check if the user is administrator */
  $SQL = "SELECT * FROM users where user_name = '$user' and user_perm = $ADMIN_PERMISSION;";
  $result = pg_query($PG_CONN, $SQL);
  DBCheckResult($result, $SQL, __FILE__, __LINE__);
  $row = pg_fetch_assoc($result);
  pg_free_result($result);
  if(!empty($row)) {
    return 1;
  }

  return 0;
}
  
