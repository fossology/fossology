<?php
/***********************************************************
 Copyright (C) 2021 Siemens AG
 Author: Shaheem Azmal M MD <shaheem.azmal@siemens.com>

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

/**
 * @file
 * @brief Migrate DB for copyrights
 */

use Fossology\Lib\Db\DbManager;

/**
 * Maximum rows to process at once
 * @var integer MAX_ROW_SIZE
 */
const MAX_SIZE_OF_ROW = 10000;

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
function insertCopyrightEventTables($dbManager)
{
  if ($dbManager == NULL) {
    echo "No connection object passed!\n";
    return false;
  }
  foreach (TABLE_NAMES as $table => $tableEvent) {
    $sql = "SELECT count(*) AS cnt FROM ";
    $statement = __METHOD__ . ".getCountsFor";
    $length = 0;
    $row = $dbManager->getSingleRow($sql . $table ." WHERE is_enabled=false;", array(), $statement . $table);
    $length = intval($row['cnt']);
    if (!empty($length)) {
      echo "*** Inserting $length records from $table to $tableEvent table ***\n";
    }
    $tablePk = $table."_pk";
    $tableFk = $table."_fk";
    $sql = "SELECT DISTINCT ON ($tablePk) $tablePk, uploadtree_pk, upload_fk 
              FROM $table as cp
            INNER JOIN uploadtree AS ut ON cp.pfile_fk = ut.pfile_fk
              WHERE cp.is_enabled=false ORDER BY $tablePk
                LIMIT $1;";
    $i = 0;
    $statement = __METHOD__ . ".updateContentFor.$tableEvent";
    while ($i < $length) {
      $rows = $dbManager->getRows($sql, array(MAX_SIZE_OF_ROW),
        $statement);
      $i += count($rows);
      $dbManager->begin();
      foreach ($rows as $key => $content) {
        $sqlEvent = "INSERT INTO $tableEvent (upload_fk, $tableFk, uploadtree_fk) VALUES($content[upload_fk], $content[$tablePk], $content[uploadtree_pk])";
        $dbManager->queryOnce($sqlEvent, $statement.$key."Insert");
        
        $sqlTable = "UPDATE $table SET is_enabled=true WHERE $tablePk = $content[$tablePk]";
        $dbManager->queryOnce($sqlTable, $statement.$key."Update");
      }
      $dbManager->commit();
      echo "Inserted $i out of $length rows in $tableEvent table\n";
    }
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
    insertCopyrightEventTables($dbManager);
  } catch (Exception $e) {
    echo "Something went wrong. Try running postinstall again!\n";
    $dbManager->rollback();
  }
}
