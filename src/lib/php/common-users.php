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
  global $container;
  $dbManager = $container->get('db.manager');

  if (empty($default_bucketpool_fk)) 
  {
    $default_bucketpool_fk = NULL;
  }
  
  $dbManager->prepare($stmt='users.insert',$sql="INSERT INTO users
         (user_name,user_desc,user_seed,user_pass,user_perm,user_email,
          email_notify,user_agent_list,root_folder_fk) VALUES ($1,$2,$3,$4,$5,$6,  $7,$8,$9)");
  $dbManager->execute($stmt,array ($User,$Desc,$Seed,$Hash,$Perm,$Email, $Email_notify,$agentList,$Folder));

  /* Make sure it was added */
  $row = $dbManager->getSingleRow("SELECT * FROM users WHERE user_name = $1",array($User),$stmt='users.get');
  if (empty($row['user_name'])) 
  {
    $text = _("Failed to insert user.");
    return ($text);
  }

  /* The user was added, so create their group and make them the admin */
  $user_name = $row['user_name'];
  $user_pk = $row['user_pk'];
  // Add user group
  $dbManager->prepare($stmt='group.get', $sql = "select group_pk from groups where group_name=$1");
  $verg = $dbManager->execute('group.get',array($user_name));
  $GroupRow = $dbManager->fetchArray($verg);
  if(false===$GroupRow){
    $dbManager->getSingleRow('insert into groups(group_name) values ($1)',array($user_name));
    $GroupRow = $dbManager->fetchArray($dbManager->execute('group.get',array($user_name)));
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