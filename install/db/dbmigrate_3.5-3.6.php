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
 * @brief calculate number of records and return offset
 *
 * The function gets count of the values from database and check if there are
 * more than 10,000 records then return 10,000, otherwise return number of
 * records.
 * @param DbManager $dbManager
 * @param string $tableName
 */
function calculateNumberOfRecordsToBeProcessed($dbManager, $tableName, $columnName)
{
  $sql = "SELECT count(*) AS cnt FROM $tableName WHERE $tableName.$columnName is NULL;";
  $totalPfile = $dbManager->getSingleRow($sql, [], __METHOD__ .
    ".calculateNumberOfRecordsToBeProcesses" . $tableName);
  $count = 0;
  if ($totalPfile['cnt'] > 10000) {
    $count = 10000;
  } else {
    $count = $totalPfile['cnt'];
  }
  return array($count, $totalPfile['cnt']);
}

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

  $dbManager->begin();
  $dbManager->queryOnce($sql);
  $dbManager->commit();

  // Then remove any active duplicate statements
  $sql = "
 DELETE FROM $tableName
  WHERE " . $tableName . "_pk IN (SELECT " . $tableName . "_pk
   FROM (SELECT " . $tableName . "_pk,
    ROW_NUMBER() OVER (PARTITION BY textfinding, pfile_fk
                       ORDER BY " . $tableName . "_pk) AS rnum
   FROM $tableName) AS a
  WHERE a.rnum > 1);";

  $dbManager->begin();
  $dbManager->queryOnce($sql);
  $dbManager->commit();
}

/**
 * @brief Update the hash column of the table with value from textfinding.
 * @param DbManager $dbManager
 * @param string $tableName
 * @return integer Number of entries updated
 */
function updateHash($dbManager, $tableName)
{
  $totalCount = 0;
  if($dbManager == null){
    echo "No connection object passed!\n";
    return false;
  }
  if(DB_TableExists($tableName) != 1) {
    // Table does not exists (migrating from old version)
    echo "Table $tableName does not exists, not updating!\n";
    return 0;
  }

  $numberOfRecords = calculateNumberOfRecordsToBeProcessed($dbManager, $tableName, "hash")[0];
  while (!empty($numberOfRecords)) {
    $sql = "SELECT " . $tableName . "_pk AS id, textfinding " .
      "FROM $tableName WHERE hash IS NULL LIMIT $numberOfRecords;";
    $statement = __METHOD__ . ".getNullHash.$tableName.$numberOfRecords";
    $rows = $dbManager->getRows($sql, [], $statement);

    $sql = "UPDATE $tableName AS m " .
      "SET hash = c.sha256 FROM (VALUES ";
    $fileShaList = [];
    foreach ($rows as $row) {
      $fileShaList[] = "(" . $row["id"] . ",'" .
      hash('sha256', $row['textfinding']) . "')";
    }
    $sql .= join(",", $fileShaList);
    $sql .= ") AS c(id, sha256) WHERE c.id = m.$tableName" . "_pk;";
    $dbManager->begin();
    $dbManager->queryOnce($sql, __METHOD__ . ".update.$tableName.hash");
    $dbManager->commit();

    $totalCount = $totalCount + $numberOfRecords;
    $numberOfRecords = calculateNumberOfRecordsToBeProcessed($dbManager, $tableName, "hash")[0];
  }
  return $totalCount;
}


/**
 * @brief Update the sha256 column of the table with value from textfinding.
 * @param DbManager $dbManager
 * @param string $tableName
 * @return integer Number of entries updated
 */
function updateSHA256($dbManager, $tableName)
{
  $totalCount = 0;
  if ($dbManager == null) {
    echo "No connection object passed!\n";
    return false;
  }

  if (DB_TableExists($tableName) != 1) {
    // Table does not exists (migrating from old version)
    echo "Table $tableName does not exists, not updating!\n";
    return 0;
  }

  $numberOfRecords = calculateNumberOfRecordsToBeProcessed($dbManager, $tableName, $tableName."_sha256")[0];
  while (!empty($numberOfRecords)) {
    $sql = "SELECT ".$tableName.".".$tableName . "_pk AS id " .
      "FROM $tableName WHERE $tableName." . $tableName . "_sha256 is NULL " .
      "LIMIT $numberOfRecords";
    $statement = __METHOD__ . ".getNullSHA256.$tableName.$numberOfRecords";
    $rows = $dbManager->getRows($sql, [], $statement);

    $sql = "UPDATE $tableName AS m " .
    "SET " . $tableName . "_sha256 = c.sha256 " .
    "FROM (VALUES ";
    $fileShaList = [];
    foreach ($rows as $row) {
      $oneRow = "(" . $row["id"];
      $filePath = RepPath($row['id'], "files");
      if (file_exists($filePath)) {
        $hash = hash_file('sha256', $filePath);
        $oneRow .= ",'$hash')";
      } else {
        $oneRow .= ",null)";
      }
      $fileShaList[] = $oneRow;
    }
    $sql .= join(",", $fileShaList);
    $sql .= ") AS c(id, sha256) WHERE c.id = m.$tableName" . "_pk;";
    $dbManager->begin();
    $dbManager->queryOnce($sql, __METHOD__ . ".updatePfile_SHA256");
    $dbManager->commit();

    $totalCount = $totalCount + $numberOfRecords;
    $numberOfRecords = calculateNumberOfRecordsToBeProcessed($dbManager, $tableName, $tableName."_sha256")[0];
  }
  return $totalCount;
}

function updatePfileSha256($dbManager, $force = false)
{
  $totalPfile = 0;
  $totalPfile = calculateNumberOfRecordsToBeProcessed($dbManager, "pfile", "pfile_sha256")[1];

  if ($totalPfile == 0) {
    // Migration not required
    return 0;
  }
  $envYes = getenv('FOSSPFILE');
  if (!$force) {
    $force = !empty($envYes);
  }
  if (!$force) {
    // Ask the user for confirmation
    $timePerJob = 0.00905919;
    $totalTime = floatval($totalPfile) * $timePerJob;
    $minutes = intval($totalTime / 60.0);
    $hours = floor($minutes / 60);
    $actualMinutes = $minutes - ($hours * 60);
    echo "*** Calculation of SHA256 for pfiles will require approx $hours hrs " .
      "$actualMinutes mins. ***\n";
    if ($hours > 0 || $minutes > 45) {
      $REDCOLOR = "\033[0;31m";
      $NOCOLOR = "\033[0m";
      echo "\n*********************************************************" .
        "***********************\n";
      echo "*** " . $REDCOLOR . "Error, script will take too much time. Not " .
        "calculating SHA256 for pfile." . $NOCOLOR . " ***\n";
      echo "*** Either rerun the fo-postinstall with \"--force-pfile\" flag " .
        "or set         ***\n" .
        "*** \"FOSSPFILE=1\" in environment or run script at                " .
        "            ***\n";
      echo "*** \"" . dirname(__FILE__) .
        "/dbmigrate_pfile_calculate_sha256.php\" to continue as a separate process ***\n";
      echo "*********************************************************" .
        "***********************\n";
      return 0;
    }
  }

  try {
    echo "*** Updating the sha256 values of pfiles ***\n";
    $countPfile = updateSHA256($dbManager, "pfile");
    echo "*** Updated sha256 of $countPfile/$totalPfile records of pfile ***\n";
  } catch (Exception $e) {
    echo "*** Something went wrong. Try again! ***\n";
    $dbManager->rollback();
    return -1;
  }
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
    $count = 0;
    // Updating the copyright/ecc/keyword findings
    echo "*** Updating the hash values of manual copyright/ecc/keyword findings ***\n";

    foreach ($tables as $table) {
      cleanDecisionTable($dbManager, $table);
      $count += updateHash($dbManager, $table);
    }

    echo "*** Updated hash of $count/$total manual copyright/ecc/keyword findings ***\n";
  } catch (Exception $e) {
    echo "*** Something went wrong. Try running postinstall again! ***\n";
    $dbManager->rollback();
  }
}
