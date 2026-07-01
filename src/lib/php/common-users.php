<?php
/*
 SPDX-FileCopyrightText: © 2013 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: LGPL-2.1-only
*/

/**
 * \file
 * \brief This file contains common functions involving the users table
 */


/**
 * \brief Add a user
 *
 * This also creates a group for the user and makes the user the group admin.
 *
 * Parameters are the user table fields:
 * \param string $User  user_name
 * \param string $Desc  user_desc
 * \param string $Hash  user_pass
 * \param int    $Perm  user_perm
 * \param string $Email user_email
 * \param char   $Email_notify  email_notify
 * \param string $agentList user_agent_list
 * \param int    $Folder root_folder_fk
 * \param int    $default_bucketpool_fk default is empty
 * \param string $spdxSettings spdx_settings
 *
 * \return error: exit (1)
 */
function add_user($User, $Desc, $Hash, $Perm, $Email, $Email_notify, $Upload_visibility,
                  $agentList, $Folder, $default_bucketpool_fk='', $spdxSettings=null)
{
  global $container;
  $dbManager = $container->get('db.manager');

  if (empty($default_bucketpool_fk)) {
    $default_bucketpool_fk = null;
  }

  if ($dbManager->existsColumn('users', 'spdx_settings') && $spdxSettings !== null) {
    $dbManager->prepare($stmt='users.insert',$sql="INSERT INTO users
           (user_name,user_desc,user_seed,user_pass,user_perm,user_email,
            email_notify,upload_visibility,user_agent_list,root_folder_fk,default_folder_fk,spdx_settings) VALUES ($1,$2,$3,$4,$5,$6,  $7,$8,$9,$10,$11,$12)");
    $dbManager->execute($stmt,array ($User,$Desc,'Seed',$Hash,$Perm,$Email,  $Email_notify,$Upload_visibility,$agentList,$Folder,$Folder,$spdxSettings));
  } else {
    $dbManager->prepare($stmt='users.insert',$sql="INSERT INTO users
           (user_name,user_desc,user_seed,user_pass,user_perm,user_email,
            email_notify,upload_visibility,user_agent_list,root_folder_fk,default_folder_fk) VALUES ($1,$2,$3,$4,$5,$6,  $7,$8,$9,$10,$11)");
    $dbManager->execute($stmt,array ($User,$Desc,'Seed',$Hash,$Perm,$Email,  $Email_notify,$Upload_visibility,$agentList,$Folder,$Folder));
  }

  /* Make sure it was added */
  $row = $dbManager->getSingleRow("SELECT * FROM users WHERE user_name = $1",array($User),$stmt='users.get');
  if (empty($row['user_name'])) {
    $text = _("Failed to insert user.");
    return ($text);
  }

  /* The user was added, so create their group and make them the admin */
  $user_name = $row['user_name'];
  $user_pk = $row['user_pk'];
  // Add user group
  $dbManager->prepare($stmt='group.get', $sql = "select group_pk from groups where LOWER(group_name)=LOWER($1)");
  $verg = $dbManager->execute('group.get',array($user_name));
  $GroupRow = $dbManager->fetchArray($verg);
  if (false === $GroupRow) {
    $dbManager->getSingleRow('insert into groups(group_name) values ($1)',
      array($user_name));
    $GroupRow = $dbManager->fetchArray(
      $dbManager->execute('group.get', array($user_name)));
  }

  $group_pk = $GroupRow['group_pk'];
  // make user a member of their own group
  $dbManager->getSingleRow($sql="insert into group_user_member (group_fk, user_fk, group_perm) values ($1,$2,$3)",
          $param=array($group_pk, $user_pk, 1),$stmt='groupmember.insert');
  // set active group = own group
  $dbManager->prepare($stmt='users.update', $sql = "update users SET group_fk=$1, default_bucketpool_fk=$3 WHERE user_pk=$2");
  $dbManager->execute($stmt,array($group_pk,$user_pk,$default_bucketpool_fk));
  return ('');
}

/**
 * \brief Update user password hash
 *
 * \param string $User  user_name
 * \param string $Hash  user_pass
 *
 * \return error: exit (1)
 */
function update_password_hash($User, $Hash)
{
  global $container;
  $dbManager = $container->get('db.manager');

  // Check if user exist
  $row = $dbManager->getSingleRow("SELECT * FROM users WHERE user_name = $1",array($User),$stmt='users.get');
  if (empty($row['user_name'])) {
    $text = _("User does not exist.");
    return ($text);
  }

  $dbManager->prepare($stmt = 'users.update_hash', $sql = "UPDATE users SET user_seed = $1, user_pass = $2  WHERE user_name = $3");
  $dbManager->execute($stmt,array ('Seed', $Hash, $User));
  return ('');
}
