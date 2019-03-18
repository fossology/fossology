<?php
/***********************************************************
Copyright (C) 2008-2013 Hewlett-Packard Development Company, L.P.
Copyright (C) 2017-2018 Siemens AG

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
 ***********************************************************/

require_once __DIR__ . "/../../lib/php/common-db.php";
require_once __DIR__ . "/../../lib/php/common-perms.php";

/**
 * \brief Delete a user.
 * \param $UserId User to be deleted.
 * \param $dbManager DB Manager used.
 * \return NULL on success, string on failure.
 */
function DeleteUser($UserId, $dbManager)
{
  global $PG_CONN;

  // Prepare all statements
  $userSelectStatement = __METHOD__ . ".getUser";
  $dbManager->prepare($userSelectStatement,
    "SELECT * FROM users WHERE user_pk = $1 LIMIT 1;");

  $selectGroupStatement = __METHOD__ . ".getGroup";
  $dbManager->prepare($selectGroupStatement,
    "SELECT group_pk FROM groups WHERE group_name = $1 LIMIT 1;");

  $deleteGroupUserStatement = __METHOD__ . ".deleteGroupUser";
  $dbManager->prepare($deleteGroupUserStatement,
    "DELETE FROM group_user_member WHERE user_fk = $1;");

  $deleteUserStatement = __METHOD__ . ".deleteUser";
  $dbManager->prepare($deleteUserStatement,
    "DELETE FROM users WHERE user_pk = $1;");

  $userCheckStatement = __METHOD__ . ".getUserbyName";
  $dbManager->prepare($userCheckStatement,
    "SELECT * FROM users WHERE user_name = $1 LIMIT 1;");

  /* See if the user already exists */
  $result = $dbManager->execute($userSelectStatement, [$UserId]);
  $row = $dbManager->fetchArray($result);
  $dbManager->freeResult($result);
  if (empty($row['user_name']))
  {
    $text = _("User does not exist.");
    return($text);
  }

  /* Delete the users group
   * First look up the users group_pk
   */
  $result = $dbManager->execute($selectGroupStatement, [$row['user_name']]);
  $GroupRow = $dbManager->fetchArray($result);
  $dbManager->freeResult($result);

  /* Delete all the group user members for this user_pk */
  $dbManager->freeResult($dbManager->execute($deleteGroupUserStatement, [$UserId]));

  /* Delete the user */
  $dbManager->freeResult($dbManager->execute($deleteUserStatement, [$UserId]));

  /* Now delete their group */
  DeleteGroup($GroupRow['group_pk'], $PG_CONN);

  /* Make sure it was deleted */
  $result = $dbManager->execute($userCheckStatement, [$UserId]);
  $rowCount = count($dbManager->fetchArray($result));
  $dbManager->freeResult($result);
  if ($rowCount != 0)
  {
    $text = _("Failed to delete user.");
    return($text);
  }

  return(NULL);
} // Delete()
