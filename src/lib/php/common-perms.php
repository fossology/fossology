<?php
/*
 SPDX-FileCopyrightText: © 2011-2013 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: LGPL-2.1-only
*/

use Fossology\Lib\Auth\Auth;

/**
 * \file
 * \brief Library of common functions for permissions and groups.
 */

  /** ********************************************************
   Get all the uploads inside a given folder

   @param int $folder_pk
   @return Array of upload_pks for all the uploads in this
           folder (and subfolders).
   ***********************************************************/
function GetUploadsFromFolder($folder_pk)
{
  $uploads = array();
  if (empty($folder_pk)) {
    return $uploads;
  }
  GetUploadsFromFolder_recurse($folder_pk, $uploads);
  return $uploads;
}  /* GetUploadsFromFolder */

  /** ********************************************************
   Get the upload ids of all uploads inside given folder.

   \note This is recursive!

   @param int $folder_pk
   @param[in,out] array $uploads  Array of upload_pk's.
   @return Array of upload_pks for all the uploads in this
           folder (and subfolders).
   @sa GetUploadsFromFolder()
   ***********************************************************/
function GetUploadsFromFolder_recurse($folder_pk, &$uploads)
{
  global $PG_CONN;

  $sql = "select * from foldercontents where parent_fk=$folder_pk";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  while ($row = pg_fetch_assoc($result)) {
    switch ($row["foldercontents_mode"]) {
      case 1:  // Child is folder
        GetUploadsFromFolder_recurse($row["child_id"], $uploads);
        break;
      case 2:  // Child is upload
        $uploads[] = $row["child_id"];
        break;
      default:
        // Other modes not used at this time
    }
  }
  pg_free_result($result);
}  /* GetUploadsFromFolder_recurse */


  /**
   *  @brief Check if User is already in the $GroupArray.
   *
   *  If not, add them. If they are, update their record with the
   *  highest permission granted to them.
   *
   *  @param array $GroupRow Group row from DB
   *  @param[in,out] array &$GroupArray Group array where user is to be added
   *  @return $GroupArray is updated.
   **/
function AddUserToGroupArray($GroupRow, &$GroupArray)
{
  /* loop throught $GroupArray to see if the user is already present */
  $found = false;
  foreach ($GroupArray as &$Grec) {
    if ($Grec['user_pk'] == $GroupRow['user_fk']) {
      /* user already exists in $GroupArray, so make sure they have the highest
       * permission granted to them.
       */
      if ($Grec['group_perm'] < $GroupRow['group_perm']) {
        $Grec['group_perm'] = $GroupRow['group_perm'];
      }
      $found = true;
      break;
    }
  }

  if (! $found) {
    $NewGroup = array();
    $NewGroup['user_pk'] = $GroupRow['user_fk'];
    $NewGroup['group_pk'] = $GroupRow['group_pk'];
    $NewGroup['group_name'] = $GroupRow['group_name'];
    $NewGroup['group_perm'] = $GroupRow['group_perm'];
    $GroupArray[] = $NewGroup;
  }
}

  /**
   *  @brief Get all the users users of this group.
   *  @param int $user_pk  Optional, if specified limit to single user
   *  @param int $group_pk Group id to look into
   *  @param[in,out] array &$GroupArray
   *  @return Array of groups the and the user's permission (group_perm) in each group
   *  -  [user_pk]
   *  -  [group_pk]
   *  -  [group_name]
   *  -  [group_perm]
   **/
function GetGroupUsers($user_pk, $group_pk, &$GroupArray)
{
  global $PG_CONN;
  $GroupArray = array();

  $user_pk = GetArrayVal("UserId", $_SESSION);
  if (empty($user_pk)) {
    return $GroupArray;
  }

  /****** For this group, get its users ******/
  if (empty($user_pk)) {
    $UserCondition = "";
  } else {
    $UserCondition = " and user_fk=$user_pk ";
  }

  $sql = "select group_pk, group_name, group_perm, user_fk from group_user_member, groups where group_pk=$group_pk and group_pk=group_fk $UserCondition";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  while ($row = pg_fetch_assoc($result)) {
    /* Add the user(s) to $GroupArray */
    AddUserToGroupArray($row, $GroupArray);
  }
}

  /**
   *  @brief Find all the groups a user belongs to.
   *  @param int $user_pk Optional, defaults to current user
   *  @return Array of groups
   *  each group is itself an array with the following elements
   *  -  [user_pk]
   *  -  [group_pk]
   *  -  [group_name]
   *  -  [group_perm]
   **/
function GetUsersGroups($user_pk='')
{
  global $PG_CONN;

  $GroupArray = array();

  if (empty($user_pk)) {
    $user_pk = GetArrayVal("UserId", $_SESSION);
  }
  if (empty($user_pk)) {
    return $GroupArray; /* user has no groups */
  }
  /* find all groups with this user */
  $sql = "select group_fk as group_pk from group_user_member where user_fk=$user_pk";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  while ($row = pg_fetch_assoc($result)) {
    /* Now find all the groups that contain this group */
    GetGroupUsers($user_pk, $row['group_pk'], $GroupArray);
  }
  pg_free_result($result);
  return $GroupArray;
}

  /**
   * @brief Get array of groups that this user has admin access to
   * @depricated use UserDao::getAdminGroupMap()
   * @param int $user_pk
   *
   * @return Array in the format {group_pk=>group_name, group_pk=>group_name, ...}
   *         Array may be empty.
   **/
function GetGroupArray($user_pk)
{
  global $PG_CONN;

  $GroupArray = array();

  if ($_SESSION[Auth::USER_LEVEL] == PLUGIN_DB_ADMIN) {
    $sql = "select group_pk, group_name from groups";
  } else {
    $sql = "select group_pk, group_name from groups, group_user_member
                  where group_pk=group_fk and user_fk='$user_pk' and group_perm=1";
  }
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  if (pg_num_rows($result) > 0) {
    while ($row = pg_fetch_assoc($result)) {
      $GroupArray[$row['group_pk']] = $row['group_name'];
    }
  }
  pg_free_result($result);

  natcasesort($GroupArray);
  return $GroupArray;
}


  /**
   * \brief Delete a group.
   * \param int $group_pk Group to be deleted
   * \returns NULL on success, string on failure.
   */
function DeleteGroup($group_pk)
{
  global $PG_CONN;

  $user_pk = Auth::getUserId();

  /* Make sure groupname looks valid */
  if (empty($group_pk)) {
    $text = _("Error: Group name must be specified.");
    return ($text);
  }

  /* See if the group already exists */
  $sql = "SELECT group_pk FROM groups WHERE group_pk = '$group_pk'";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  if (pg_num_rows($result) < 1) {
    pg_free_result($result);
    $text = _("Group does not exist.  Not deleted.");
    return ($text);
  }
  pg_free_result($result);

  /* Make sure the user has permission to delete this group
   * Look through all the group users (table group_user_member)
   * and make sure the user has admin access.
   */
  if ($_SESSION[Auth::USER_LEVEL] != PLUGIN_DB_ADMIN) {
    $sql = "SELECT *  FROM group_user_member WHERE group_fk = '$group_pk' and user_fk='$user_pk' and group_perm=1";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    if (pg_num_rows($result) < 1) {
      pg_free_result($result);
      $text = _("Permission Denied.");
      return ($text);
    }
    pg_free_result($result);
  }

  /* Start transaction */
  $sql = "begin";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  pg_free_result($result);

  /* Delete group records from perm_upload */
  $sql = "delete from perm_upload where group_fk='$group_pk'";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  pg_free_result($result);

  /* Delete group records from group_user_member */
  $sql = "delete from group_user_member where group_fk='$group_pk'";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  pg_free_result($result);

  /* Update new_upload_group_fk and new_upload_perm in users table */
  $sql = "update users set new_upload_group_fk=NULL, new_upload_perm=NULL where new_upload_group_fk='$group_pk'";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  pg_free_result($result);

  /* Delete group records from groups table */
  $sql = "delete from groups where group_pk='$group_pk'";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  pg_free_result($result);

  /* End transaction */
  $sql = "commit";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  pg_free_result($result);

  return (null);
}
