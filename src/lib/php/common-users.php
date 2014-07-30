<?php
/***********************************************************
 Copyright (C) 2013 Hewlett-Packard Development Company, L.P.

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
 * \file common-users.php
 * \brief This file contains common functions involving the users table
 */


/**
 * \brief Add a user
 *        This also creates a group for the user and makes the user 
 *        the group admin.
 *
 * Parameters are the user table fields:
 * \param $User  user_name
 * \param $Desc  user_desc
 * \param $Seed  user_seed
 * \param $Hash  user_pass
 * \param $Perm  user_perm
 * \param $Email user_email
 * \param $Email_notify  email_notify
 * \param $agentList user_agent_list
 * \param $Folder root_folder_fk
 * \param $default_bucketpool_fk, default is empty
 * 
 * \return error: exit (1)
 */
function add_user($User, $Desc, $Seed, $Hash, $Perm, $Email, $Email_notify, 
                  $agentList, $Folder, $default_bucketpool_fk='')
{
  global $PG_CONN;

  if (empty($default_bucketpool_fk)) 
  {
    $VALUES = " VALUES ('$User','$Desc','$Seed','$Hash',$Perm,'$Email',
                        '$Email_notify','$agentList',$Folder, NULL)";
  }
  else 
  {
    $VALUES = " VALUES ('$User','$Desc','$Seed','$Hash',$Perm,'$Email',
                        '$Email_notify','$agentList',$Folder, $default_bucketpool_fk)";
  }

  $SQL = "INSERT INTO users
         (user_name,user_desc,user_seed,user_pass,user_perm,user_email,
          email_notify,user_agent_list,root_folder_fk, default_bucketpool_fk)
          $VALUES";

  $result = pg_query($PG_CONN, $SQL);
  DBCheckResult($result, $SQL, __FILE__, __LINE__);
  pg_free_result($result);

  /* Make sure it was added */
  $SQL = "SELECT * FROM users WHERE user_name = '$User' LIMIT 1;";
  $result = pg_query($PG_CONN, $SQL);
  DBCheckResult($result, $SQL, __FILE__, __LINE__);
  $row = pg_fetch_assoc($result);
  pg_free_result($result);
  if (empty($row['user_name'])) 
  {
    $text = _("Failed to insert user.");
    return ($text);
  }

  /* The user was added, so create their group and make them the admin */
  $user_name = $row['user_name'];
  $user_pk = $row['user_pk'];
  // Add user group
  $sql = "insert into groups(group_name) values ('$user_name')";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  /* Get new group_pk */
  $sql = "select group_pk from groups where group_name='$user_name'";
  $GroupResult = pg_query($PG_CONN, $sql);
  DBCheckResult($GroupResult, $sql, __FILE__, __LINE__);
  $GroupRow = pg_fetch_assoc($GroupResult);
  $group_pk = $GroupRow['group_pk'];
  pg_free_result($GroupResult);
  // make user a member of their own group
  $sql = "insert into group_user_member(group_fk, user_fk, group_perm) values($group_pk, $user_pk, 1)";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  pg_free_result($result);
  // set active group = own group
  // TODO prepare statement
  $sql = "update users SET group_id=$group_pk WHERE user_pk=$user_pk";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  pg_free_result($result);

  return ('');
}