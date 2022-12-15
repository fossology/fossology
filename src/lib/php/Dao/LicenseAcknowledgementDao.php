<?php
/*
 SPDX-FileCopyrightText: © 2022 Siemens AG
 Author: Gaurav Mishra <mishra.gaurav@siemens.com>

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @file
 * DAO layer for license_std_acknowledgement table.
 */
namespace Fossology\Lib\Dao;

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Util\StringOperation;

/**
 * @class LicenseAcknowledgementDao
 * DAO layer for license_std_acknowledgement table.
 */
class LicenseAcknowledgementDao
{
  /** @var DbManager $dbManager
   * DB manager in use */
  private $dbManager;

  function __construct(DbManager $dbManager)
  {
    $this->dbManager = $dbManager;
  }

  /**
   * Get all the acknowledgements and their name stored in the DB, sorted by their ID
   * in ascending order.
   * @param boolean $skipNotSet Skip the entries where the name is not changed
   *        or are disabled.
   * @return array All acknowledgements from the DB
   */
  public function getAllAcknowledgements($skipNotSet = false)
  {
    $where = "";
    if ($skipNotSet) {
      $where = "WHERE name <> 'not-set' AND is_enabled = TRUE";
    }
    $sql = "SELECT la_pk, name, acknowledgement, is_enabled " .
      "FROM license_std_acknowledgement $where " .
      "ORDER BY la_pk ASC;";
    return $this->dbManager->getRows($sql);
  }

  /**
   * Update single acknowledgement
   * @param int $acknowledgementPk     The acknowledgement id
   * @param string $newName    New name of the acknowledgement
   * @param string $newAcknowledgement Updated acknowledgement
   * @return boolean True if the acknowledgements are updated, false otherwise.
   */
  function updateAcknowledgement($acknowledgementPk, $newName, $newAcknowledgement)
  {
    if (!Auth::isAdmin()) {
      // Only admins can update the acknowledgements.
      return false;
    }
    $this->isAcknowledgementIdValid($acknowledgementPk);

    $userFk = Auth::getUserId();

    $sql = "UPDATE license_std_acknowledgement " .
      "SET name = $2, acknowledgement = $3, updated = NOW(), user_fk = $4 " .
      "WHERE la_pk = $1 " .
      "RETURNING 1 AS updated;";
    $row = $this->dbManager->getSingleRow($sql,
      [$acknowledgementPk, $newName,
        StringOperation::replaceUnicodeControlChar($newAcknowledgement), $userFk]);
    return $row['updated'] == 1;
  }

  /**
   * Insert a new acknowledgement
   *
   * @param string $name Name of the acknowledgement
   * @param string $acknowledgement Acknowledgement
   * @return int New acknowledgement ID if inserted successfully, -1 otherwise or -2 in
   *         case of exception.
   */
  function insertAcknowledgement($name, $acknowledgement)
  {
    if (! Auth::isAdmin()) {
      // Only admins can add acknowledgements.
      return -1;
    }

    $name = trim($name);
    $acknowledgement = trim($acknowledgement);

    if (empty($name) || empty($acknowledgement)) {
      // Cannot insert empty fields.
      return -1;
    }

    $userFk = Auth::getUserId();

    $params = [
      'name' => $name,
      'acknowledgement' => StringOperation::replaceUnicodeControlChar($acknowledgement),
      'user_fk' => $userFk
    ];
    $statement = __METHOD__ . ".insertNewLicAcknowledgement";
    $returning = "la_pk";
    $returnVal = -1;
    try {
      $returnVal = $this->dbManager->insertTableRow("license_std_acknowledgement",
        $params, $statement, $returning);
    } catch (\Exception $e) {
      $returnVal = -2;
    }
    return $returnVal;
  }

  /**
   * @brief Update the acknowledgements based only on the values provided.
   *
   * Takes an array as input and update only the fields passed.
   * @param array $acknowledgementArray Associative array with acknowledgement id as the index,
   *        name and acknowledgement as child index with corresponding values.
   * @return int Count of values updated
   * @throws \UnexpectedValueException If an entry does not contain any field,
   *         throws unexpected value exception.
   */
  function updateAcknowledgementFromArray($acknowledgementArray)
  {
    if (!Auth::isAdmin()) {
      // Only admins can update the acknowledgements.
      return false;
    }

    $userFk = Auth::getUserId();
    $updated = 0;

    foreach ($acknowledgementArray as $acknowledgementPk => $acknowledgement) {
      if (count($acknowledgement) < 1 ||
        (! array_key_exists("name", $acknowledgement) &&
        ! array_key_exists("acknowledgement", $acknowledgement))) {
        throw new \UnexpectedValueException(
          "At least name or acknowledgement is " . "required for entry " . $acknowledgementPk);
      }
      $this->isAcknowledgementIdValid($acknowledgementPk);
      $statement = __METHOD__;
      $params = [$acknowledgementPk, $userFk];
      $updateStatement = [];
      if (array_key_exists("name", $acknowledgement)) {
        $params[] = $acknowledgement["name"];
        $updateStatement[] = "name = $" . count($params);
        $statement .= ".name";
      }
      if (array_key_exists("acknowledgement", $acknowledgement)) {
        $params[] = StringOperation::replaceUnicodeControlChar($acknowledgement["acknowledgement"]);
        $updateStatement[] = "acknowledgement = $" . count($params);
        $statement .= ".acknowledgement";
      }
      $sql = "UPDATE license_std_acknowledgement " .
        "SET updated = NOW(), user_fk = $2, " . join(",", $updateStatement) .
        "WHERE la_pk = $1 " .
        "RETURNING 1 AS updated;";
      $retVal = $this->dbManager->getSingleRow($sql, $params, $statement);
      $updated += intval($retVal);
    }
    return $updated;
  }

  /**
   * Get the acknowledgement for the given acknowledgement id
   * @param int $acknowledgementPk The acknowledgement id
   * @return string|null Acknowledgement from the DB (if set, null otherwise)
   */
  function getAcknowledgement($acknowledgementPk)
  {
    $this->isAcknowledgementIdValid($acknowledgementPk);
    $sql = "SELECT acknowledgement FROM license_std_acknowledgement " . "WHERE la_pk = $1;";
    $statement = __METHOD__ . ".getAcknowledgement";

    $acknowledgement = $this->dbManager->getSingleRow($sql, [$acknowledgementPk], $statement);
    $acknowledgement = $acknowledgement['acknowledgement'];
    if (strcasecmp($acknowledgement, "null") === 0) {
      return null;
    }
    return $acknowledgement;
  }

  /**
   * Toggle acknowledgement status.
   *
   * @param int $acknowledgementPk The acknowledgement id
   * @return boolean True if the acknowledgement is toggled, false otherwise.
   */
  function toggleAcknowledgement($acknowledgementPk)
  {
    if (! Auth::isAdmin()) {
      // Only admins can update the acknowledgements.
      return false;
    }
    $this->isAcknowledgementIdValid($acknowledgementPk);

    $userFk = Auth::getUserId();

    $sql = "UPDATE license_std_acknowledgement " .
      "SET is_enabled = NOT is_enabled, user_fk = $2 " .
      "WHERE la_pk = $1;";

    $this->dbManager->getSingleRow($sql, [$acknowledgementPk, $userFk]);
    return true;
  }

  /**
   * Check if the given acknowledgement id is an integer and exists in DB.
   * @param int $acknowledgementPk Acknowledgement id to check for
   * @throws \UnexpectedValueException Throw exception in acknowledgement id is not int
   *         or does not exists in DB.
   */
  private function isAcknowledgementIdValid($acknowledgementPk)
  {
    if (! is_int($acknowledgementPk)) {
      throw new \UnexpectedValueException("Inavlid acknowledgement id");
    }
    $sql = "SELECT count(*) AS cnt FROM license_std_acknowledgement " .
      "WHERE la_pk = $1;";

    $acknowledgementCount = $this->dbManager->getSingleRow($sql, [$acknowledgementPk]);
    if ($acknowledgementCount['cnt'] < 1) {
      // Invalid acknowledgement id
      throw new \UnexpectedValueException("Inavlid acknowledgement id");
    }
  }
}
