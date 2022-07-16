<?php
/*
 SPDX-FileCopyrightText: © 2022 Siemens AG
 Author: Gaurav Mishra <mishra.gaurav@siemens.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @file
 * @brief Migrate DB from release 4.0.0 to 4.1.0
 */

use Fossology\Lib\Db\DbManager;

/**
 * Tables which needs to be processed
 * @var array PROCESS_TABLES
 */
const PROCESS_TABLES = array(
  "copyright",
  "author",
  "ecc",
  "keyword",
  "copyright_event",
  "author_event",
  "ecc_event",
  "keyword_event"
);

/**
 * Total number of records to be migirated by script.
 * @var int $MIG_4041_TOTAL_RECORDS
 */
$GLOBALS['MIG_4041_TOTAL_RECORDS'] = 0;

/**
 * Check if migration is required.
 * @param DbManager $dbManager
 * @return boolean True if migration is required, false otherwise
 */
function checkMigrate4041Required($dbManager)
{
  global $MIG_4041_TOTAL_RECORDS;
  if ($dbManager == NULL) {
    echo "No connection object passed!\n";
    return false;
  }

  $migRequired = true;
  foreach (PROCESS_TABLES as $table) {
    if (DB_TableExists($table) != 1) {
      $migRequired = false;
      break;
    }
  }
  if ($migRequired) {
    $count = 0;
    foreach (PROCESS_TABLES as $table) {
      $sql = "SELECT count(*) AS cnt FROM $table " .
        "WHERE hash = 'd41d8cd98f00b204e9800998ecf8427e';"; // hash = md5('')
      $result = $dbManager->getSingleRow($sql, [],
        __METHOD__ . ".checkMig." . $table);
      $count += intval($result['cnt']);
    }
    $MIG_4041_TOTAL_RECORDS = $count;
    if ($count == 0) {
      $migRequired = false;
    }
  }

  return $migRequired;
}

/**
 * Start the recoding process
 * @param DbManager $dbManager
 * @return number Return code
 */
function fixEmptyContentHash($dbManager)
{
  global $MIG_4041_TOTAL_RECORDS;
  $updated = 0;
  foreach (PROCESS_TABLES as $table) {
    $sql = "WITH temp_h AS (" .
      "UPDATE $table SET " .
        "content = NULL, hash = NULL " .
      "WHERE (" .
        "hash = 'd41d8cd98f00b204e9800998ecf8427e' OR content = ''" .
      ") RETURNING 1 AS c) " .
      "SELECT sum(c) AS cnt FROM temp_h;";
    $statement = __METHOD__ . ".fixHash." . $table;
    $dbManager->begin();
    $result = $dbManager->getSingleRow($sql, [], $statement);
    $dbManager->commit();
    $updated += intval($result['cnt']);
  }
  echo "*** Corrected hash of $updated/$MIG_4041_TOTAL_RECORDS entries from " .
    count(PROCESS_TABLES) . " tables ***\n";
}

/**
 * Migration from FOSSology 4.0.0 to 4.1.0
 * @param DbManager $dbManager
 */
function Migrate_40_41($dbManager)
{
  if (!checkMigrate4041Required($dbManager)) {
    // Migration not required
    return;
  }
  try {
    fixEmptyContentHash($dbManager);
  } catch (Exception $e) {
    echo "Something went wrong. Try running postinstall again!\n";
    $dbManager->rollback();
  }
}
