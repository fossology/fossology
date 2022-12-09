<?php
/*
 SPDX-FileCopyrightText: © 2008-2013 Hewlett-Packard Development Company, L.P.
 SPDX-FileCopyrightText: © 2017-2018 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

require_once __DIR__ . "/../../lib/php/common-db.php";
require_once __DIR__ . "/../../lib/php/common-perms.php";

/**
 * \brief Delete a user.
 * \param $UserId User to be deleted.
 * \param $dbManager DB Manager used.
 * \return NULL on success, string on failure.
 */
function deleteUser($UserId, $dbManager)
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
    "SELECT count(*) AS cnt FROM users WHERE user_name = $1 LIMIT 1;");

  /* See if the user already exists */
  $result = $dbManager->execute($userSelectStatement, [$UserId]);
  $row = $dbManager->fetchArray($result);
  $dbManager->freeResult($result);
  if (empty($row['user_name'])) {
    $text = _("User does not exist.");
    return ($text);
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
  $rowEmpty = empty($dbManager->fetchArray($result)['cnt']);
  $dbManager->freeResult($result);
  if (! $rowEmpty) {
    $text = _("Failed to delete user.");
    return ($text);
  }

  return(null);
} // Delete()
