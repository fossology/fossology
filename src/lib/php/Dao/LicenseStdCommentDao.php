<?php
/*
 SPDX-FileCopyrightText: © 2019 Siemens AG
 Author: Gaurav Mishra <mishra.gaurav@siemens.com>

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @file
 * DAO layer for license_std_comment table.
 */
namespace Fossology\Lib\Dao;

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Util\StringOperation;

/**
 * @class LicenseStdCommentDao
 * DAO layer for license_std_comment table.
 */
class LicenseStdCommentDao
{
  /** @var DbManager $dbManager
   * DB manager in use */
  private $dbManager;

  function __construct(DbManager $dbManager)
  {
    $this->dbManager = $dbManager;
  }

  /**
   * Get all the comments and their name stored in the DB, sorted by their ID
   * in ascending order.
   * @param boolean $skipNotSet Skip the entries where the name is not changed
   *        or are disabled.
   * @return array All comments from the DB
   */
  public function getAllComments($skipNotSet = false)
  {
    $where = "";
    if ($skipNotSet) {
      $where = "WHERE name <> 'not-set' AND is_enabled = TRUE";
    }
    $sql = "SELECT lsc_pk, name, comment, is_enabled " .
      "FROM license_std_comment $where " .
      "ORDER BY lsc_pk ASC;";
    return $this->dbManager->getRows($sql);
  }

  /**
   * Update single comment
   * @param int $commentPk     The comment id
   * @param string $newName    New name of the comment
   * @param string $newComment Updated comment
   * @return boolean True if the comments are updated, false otherwise.
   */
  function updateComment($commentPk, $newName, $newComment)
  {
    if (!Auth::isAdmin()) {
      // Only admins can update the comments.
      return false;
    }
    $this->isCommentIdValid($commentPk);

    $userFk = Auth::getUserId();

    $sql = "UPDATE license_std_comment " .
      "SET name = $2, comment = $3, updated = NOW(), user_fk = $4 " .
      "WHERE lsc_pk = $1 " .
      "RETURNING 1 AS updated;";
    $row = $this->dbManager->getSingleRow($sql,
      [$commentPk, $newName,
        StringOperation::replaceUnicodeControlChar($newComment), $userFk]);
    return $row['updated'] == 1;
  }

  /**
   * Insert a new comment
   *
   * @param string $name Name of the comment
   * @param string $comment Comment
   * @return int New comment ID if inserted successfully, -1 otherwise or -2 in
   *         case of exception.
   */
  function insertComment($name, $comment)
  {
    if (! Auth::isAdmin()) {
      // Only admins can add comments.
      return -1;
    }

    $name = trim($name);
    $comment = trim($comment);

    if (empty($name) || empty($comment)) {
      // Cannot insert empty fields.
      return -1;
    }

    $userFk = Auth::getUserId();

    $params = [
      'name' => $name,
      'comment' => StringOperation::replaceUnicodeControlChar($comment),
      'user_fk' => $userFk
    ];
    $statement = __METHOD__ . ".insertNewLicStdComment";
    $returning = "lsc_pk";
    $returnVal = -1;
    try {
      $returnVal = $this->dbManager->insertTableRow("license_std_comment",
        $params, $statement, $returning);
    } catch (\Exception $e) {
      $returnVal = -2;
    }
    return $returnVal;
  }

  /**
   * @brief Update the comments based only on the values provided.
   *
   * Takes an array as input and update only the fields passed.
   * @param array $commentArray Associative array with comment id as the index,
   *        name and comment as child index with corresponding values.
   * @return int Count of values updated
   * @throws \UnexpectedValueException If an entry does not contain any field,
   *         throws unexpected value exception.
   */
  function updateCommentFromArray($commentArray)
  {
    if (!Auth::isAdmin()) {
      // Only admins can update the comments.
      return false;
    }

    $userFk = Auth::getUserId();
    $updated = 0;

    foreach ($commentArray as $commentPk => $comment) {
      if (count($comment) < 1 ||
        (! array_key_exists("name", $comment) &&
        ! array_key_exists("comment", $comment))) {
        throw new \UnexpectedValueException(
          "At least name or comment is " . "required for entry " . $commentPk);
      }
      $this->isCommentIdValid($commentPk);
      $statement = __METHOD__;
      $params = [$commentPk, $userFk];
      $updateStatement = [];
      if (array_key_exists("name", $comment)) {
        $params[] = $comment["name"];
        $updateStatement[] = "name = $" . count($params);
        $statement .= ".name";
      }
      if (array_key_exists("comment", $comment)) {
        $params[] = StringOperation::replaceUnicodeControlChar($comment["comment"]);
        $updateStatement[] = "comment = $" . count($params);
        $statement .= ".comment";
      }
      $sql = "UPDATE license_std_comment " .
        "SET updated = NOW(), user_fk = $2, " . join(",", $updateStatement) .
        " WHERE lsc_pk = $1 " .
        "RETURNING 1 AS updated;";
      $retVal = $this->dbManager->getSingleRow($sql, $params, $statement);
      $updated += intval($retVal);
    }
    return $updated;
  }

  /**
   * Get the comment for the given comment id
   * @param int $commentPk The comment id
   * @return string|null Comment from the DB (if set, null otherwise)
   */
  function getComment($commentPk)
  {
    $this->isCommentIdValid($commentPk);
    $sql = "SELECT comment FROM license_std_comment " . "WHERE lsc_pk = $1;";
    $statement = __METHOD__ . ".getComment";

    $comment = $this->dbManager->getSingleRow($sql, [$commentPk], $statement);
    $comment = $comment['comment'];
    if (strcasecmp($comment, "null") === 0) {
      return null;
    }
    return $comment;
  }

  /**
   * Toggle comment status.
   *
   * @param int $commentPk The comment id
   * @return boolean True if the comment is toggled, false otherwise.
   */
  function toggleComment($commentPk)
  {
    if (! Auth::isAdmin()) {
      // Only admins can update the comments.
      return false;
    }
    $this->isCommentIdValid($commentPk);

    $userFk = Auth::getUserId();

    $sql = "UPDATE license_std_comment " .
      "SET is_enabled = NOT is_enabled, user_fk = $2 " .
      "WHERE lsc_pk = $1;";

    $this->dbManager->getSingleRow($sql, [$commentPk, $userFk]);
    return true;
  }

  /**
   * Check if the given comment id is an integer and exists in DB.
   * @param int $commentPk Comment id to check for
   * @throws \UnexpectedValueException Throw exception in comment id is not int
   *         or does not exists in DB.
   */
  private function isCommentIdValid($commentPk)
  {
    if (! is_int($commentPk)) {
      throw new \UnexpectedValueException("Inavlid comment id");
    }
    $sql = "SELECT count(*) AS cnt FROM license_std_comment " .
      "WHERE lsc_pk = $1;";

    $commentCount = $this->dbManager->getSingleRow($sql, [$commentPk]);
    if ($commentCount['cnt'] < 1) {
      // Invalid comment id
      throw new \UnexpectedValueException("Inavlid comment id");
    }
  }
}
