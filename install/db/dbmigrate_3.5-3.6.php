<?php
/***********************************************************
 Copyright (C) 2019 Siemens AG
 Author: Gaurav Mishra <mishra.gaurav@siemens.com>

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

use Fossology\Lib\Db\DbManager;

/**
 * @file
 * @brief Migrate DB from release 3.5.0 to 3.6.0 with new column for decision
 * tables.
 */

/**
 * @brief Removes duplicate decisions based on same textfinding for same pfile
 *
 * The function first tries to remove all duplicate decisions from deactivated
 * list then from active list.
 * @param DbManager $dbManager
 * @param string $tableName
 */
function cleanDecisionTable($dbManager, $tableName)
{
  if($dbManager == null){
    echo "No connection object passed!\n";
    return false;
  }

  echo "*** Removing any duplicate manual findings from $tableName ***\n";
  // First remove only duplicate deactivated statements
  $sql = "
DELETE FROM $tableName
WHERE " . $tableName . "_pk IN (SELECT " . $tableName . "_pk
  FROM (SELECT " . $tableName . "_pk, is_enabled,
    ROW_NUMBER() OVER (PARTITION BY textfinding, pfile_fk
                       ORDER BY " . $tableName . "_pk) AS rnum
    FROM $tableName) AS a
  WHERE a.is_enabled = FALSE AND a.rnum > 1);";
  $dbManager->queryOnce($sql);

  // Then remove any active duplicate statements
  $sql = "
DELETE FROM $tableName
WHERE " . $tableName . "_pk IN (SELECT " . $tableName . "_pk
  FROM (SELECT " . $tableName . "_pk,
    ROW_NUMBER() OVER (PARTITION BY textfinding, pfile_fk
                       ORDER BY " . $tableName . "_pk) AS rnum
    FROM $tableName) AS a
  WHERE a.rnum > 1);";
  $dbManager->queryOnce($sql);
}

/**
 * @brief Update the hash column of the table with value from textfinding.
 * @param DbManager $dbManager
 * @param string $tableName
 * @return integer Number of entries updated
 */
function updateHash($dbManager, $tableName)
{
  if($dbManager == null){
    echo "No connection object passed!\n";
    return false;
  }
  if(DB_TableExists($tableName) != 1) {
    // Table does not exists (migrating from old version)
    echo "Table $tableName does not exists, not updating!\n";
    return 0;
  }

  $sql = "SELECT " . $tableName . "_pk AS id, textfinding " .
    "FROM $tableName WHERE hash IS NULL;";
  $statement = __METHOD__ . ".getNullHash.$tableName";
  $rows = $dbManager->getRows($sql, [], $statement);

  $sql = "
UPDATE $tableName
  SET hash = $2
  WHERE " . $tableName . "_pk = $1;
";
  $statement = __METHOD__ . ".updateHashOf.$tableName";
  $dbManager->prepare($statement, $sql);
  foreach ($rows as $row) {
    $dbManager->execute($statement, [
      $row["id"],
      hash('sha256', $row['textfinding'])
    ]);
  }
  return count($rows);
}

/**
 * Migration from FOSSology 3.5.0 to 3.6.0
 * @param DbManager $dbManager
 * @param boolean $force Set true to force run the script.
 */
function migrate_35_36($dbManager, $force = false)
{
  $total = 0;
  $tables = [
    "copyright_decision",
    "ecc_decision",
    "keyword_decision"
  ];
  if (!$force) {
    $sql = "WITH decision_tables AS(".
    "  SELECT count(*) AS cnt FROM $tables[0] WHERE hash IS NULL" .
    "  UNION" .
    "  SELECT count(*) AS cnt FROM $tables[1] WHERE hash IS NULL" .
    "  UNION" .
    "  SELECT count(*) AS cnt FROM $tables[2] WHERE hash IS NULL" .
    ") SELECT SUM(cnt) AS total FROM decision_tables;";
    $total = intval($dbManager->getSingleRow($sql, [],
      __METHOD__ . ".checkIfMigrationDone")['total']);
    if ($total == 0) {
      // Migration not required
      return;
    }
  }
  try {
    echo "*** Updating the hash values of manual copyright/ecc/keyword findings ***\n";
    $count = 0;

    $dbManager->begin();

    // Foreign key constraints
    foreach ($tables as $table) {
      cleanDecisionTable($dbManager, $table);
      $count += updateHash($dbManager, $table);
    }

    $dbManager->commit();
    echo "*** Updated hash of $count/$total manual copyright/ecc/keyword findings ***\n";
  } catch (Exception $e) {
    echo "*** Something went wrong. Try running postinstall again! ***\n";
    $dbManager->rollback();
  }
}
