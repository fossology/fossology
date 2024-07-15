<?php
/*
 SPDX-FileCopyrightText: © 2021 Siemens AG
 Author: Shaheem Azmal M MD <shaheem.azmal@siemens.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @file
 * @brief Migrate DB for copyrights
 */

use Fossology\Lib\Db\DbManager;

/**
 * Maximum rows to process at once
 * @var integer MAX_ROW_SIZE
 */
const MAX_SIZE_OF_ROW = 100000;

/**
 * Tables with is_enabled data
 * @var array TABLE_NAMES
 */
const TABLE_NAMES = array(
  "copyright" => "copyright_event",
  "author" => "author_event",
  "ecc" => "ecc_event",
  "keyword" => "keyword_event"
);

/**
 * Update the copyright_event table
 * @param DbManager $dbManager
 * @return int Count of updated rows
 */
function insertDataInToEventTables($dbManager)
{
  if ($dbManager == NULL) {
    echo "No connection object passed!\n";
    return false;
  }
  foreach (TABLE_NAMES as $table => $tableEvent) {
    $sql = "SELECT count(*) AS cnt FROM ";
    $statement = __METHOD__ . ".getCountsFor";
    $length = 0;
    $row = $dbManager->getSingleRow($sql . $table ." AS cp
            INNER JOIN uploadtree AS ut ON cp.pfile_fk = ut.pfile_fk
              WHERE cp.is_enabled=false;", array(), $statement . $table);
    $length = intval($row['cnt']);

    if (!empty($length)) {
      echo "*** Inserting $length records from $table to $tableEvent table ***\n";
    } else {
      echo "*** Table $table already migrated to $tableEvent table ***\n";
      continue;
    }

    $tablePk = $table."_pk";
    $tableFk = $table."_fk";
    $i = 0;
    $j = MAX_SIZE_OF_ROW;
    $num = 0;
    $statement = __METHOD__ . ".updateContentFor.$tableEvent";
    $dbManager->queryOnce("DROP FUNCTION IF EXISTS migrate_".$table."_events_event(upperuploadtreeid int, loweruploadtreeid int)", $statement."drop");
    $sql = "
        CREATE OR REPLACE FUNCTION migrate_".$table."_events_event(upperuploadtreeid int, loweruploadtreeid int) RETURNS VOID AS
        $$
        BEGIN
         INSERT INTO $tableEvent (upload_fk, $tableFk, uploadtree_fk)
           SELECT upload_fk, $tablePk, uploadtree_pk FROM $table as cp
             INNER JOIN uploadtree AS ut ON cp.pfile_fk = ut.pfile_fk
           WHERE ut.uploadtree_pk >= loweruploadtreeid
             AND ut.uploadtree_pk < upperuploadtreeid
             AND cp.is_enabled=false
           ORDER BY ut.uploadtree_pk;
        END
        $$
        LANGUAGE 'plpgsql';";
    $dbManager->queryOnce($sql, $statement.'plPGsqlfunction');
    while ($num <= $length) {
      $startTime = microtime(true);
      $dbManager->begin();
      $statementName = __METHOD__."insert from function".$table;
      $dbManager->getSingleRow("SELECT 1 FROM migrate_".$table."_events_event($1, $2)", array($j, $i), $statementName);
      $rows = $dbManager->getSingleRow("SELECT count(*) AS cnt, max(uploadtree_fk) AS uploadtree FROM $tableEvent", array(), $statement . $tableEvent. $table);
      $num = intval($rows['cnt']);
      if (!empty($rows['uploadtree'])) {
        $i =  $rows['uploadtree'];
      } else {
        $i = $j;
      }
      $j += MAX_SIZE_OF_ROW;
      $dbManager->commit();
      $endTime = microtime(true);
      $totalTime = ($endTime - $startTime);
      echo "Inserted $num / $length rows to $tableEvent table in ".gmdate("i", $totalTime)." minutes and ".gmdate("s", $totalTime)." seconds. \n";
    }
    $dbManager->begin();
    $sqlTable = "UPDATE $table SET is_enabled=true WHERE is_enabled=false";
    $dbManager->queryOnce($sqlTable, $statement."Update");
    $dbManager->commit();
  }
}

/**
 * Check if migration is Possible.
 * @param DbManager $dbManager
 * @return boolean True if migration is possible, false otherwise
 */
function checkIfMigratePossible($dbManager)
{
  if ($dbManager == NULL){
    echo "No connection object passed!\n";
    return false;
  }

  $migPossible = true;
  foreach (TABLE_NAMES as $table => $tableEvent) {
    if (DB_TableExists($table) != 1) {
      $migPossible = false;
      break;
    }
    if (DB_TableExists($tableEvent) != 1) {
      $migPossible = false;
      break;
    }
    if (DB_ColExists($table, 'is_enabled') != 1) {
      $migPossible = false;
      break;
    }
  }
  return $migPossible;
}

/**
 * @param DbManager $dbManager
 */
function createCopyrightMigrationForCopyrightEvents($dbManager)
{
  if (! checkIfMigratePossible($dbManager)) {
    // Migration not possible
    return;
  }
  try {
    insertDataInToEventTables($dbManager);
  } catch (Exception $e) {
    echo "Something went wrong. Try running postinstall again!\n";
    $dbManager->rollback();
  }
}
