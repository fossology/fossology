<?php
/*
 SPDX-FileCopyrightText: © 2020 Siemens AG
 Author: Gaurav Mishra <mishra.gaurav@siemens.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @file
 * @brief Migrate DB from release 3.7.0 to 3.8.0
 */

use Fossology\Lib\Db\DbManager;

/**
 * Maximum rows to process at once
 * @var integer MAX_ROW_SIZE
 */
const MAX_ROW_SIZE = 100;

/**
 * Tables which needs to be recoded
 * @var array ENCODE_TABLES
 */
const ENCODE_TABLES = array(
  "copyright",
  "author",
  "ecc",
  "keyword"
);

/**
 * Calculate the total number of records in concerned tables
 * @param DbManager $dbManager
 */
function calculateNumberOfRecordsToRecode($dbManager)
{
  $selectSql = "SELECT count(*) AS cnt FROM ";
  $where = " WHERE (content IS NOT NULL AND content != '')";
  $statement = __METHOD__ . ".getCountsFor";
  $count = 0;
  foreach (ENCODE_TABLES as $table) {
    $sql = $selectSql . $table . $where;
    $row = $dbManager->getSingleRow($sql, array(), $statement . $table);
    $count += intval($row['cnt']);
  }
  return $count;
}

/**
 * Update the recoded values back into DB
 * @param DbManager $dbManager
 * @param string $table Table to be updated
 * @param array $rows   Rows with ID and recoded values
 * @return int Count of updated rows
 */
function updateRecodedValues($dbManager, $table, $rows)
{
  if (empty($rows)) {
    return 0;
  }

  $values = array_map(function($id,$content)
    {
      return "($id,'" . pg_escape_string($content) . "')";
    }, array_keys($rows), $rows);

  $sql = "UPDATE $table AS cp " .
    "SET content = up.content, hash = md5(up.content) " .
    "FROM (VALUES" . join(",", $values) .
    ") AS up(id,content) " .
    "WHERE up.id = cp." . $table . "_pk";
  unset($values);
  $statement = __METHOD__ . ".updateContentFor.$table";

  $dbManager->begin();
  $dbManager->queryOnce($sql, $statement);
  $dbManager->commit();
  return count($rows);
}

/**
 * Use the recoder tool to remove non UTF-8 characters from text
 * @param[in,out] array $rows Rows with ID and content. Function will replace
 * the content with recoded values and remove the rows which are unchanged.
 * @param string $MODDIR Location of fossology mods
 * @throws \UnexpectedValueException If recoder tool fails
 */
function recodeContents(&$rows, $MODDIR)
{
  $cmd = "$MODDIR/copyright/agent/fo_unicode_clean";
  $descriptorspec = array(
    0 => array("pipe", "r"),
    1 => array("pipe", "w")
  );

  $input = "";
  foreach ($rows as $key => $content) {
    if (empty($content)) {
      continue;
    }
    $input .= "$key,|>$content<|\n";
  }

  $pipes = array();
  $escaper =  proc_open($cmd, $descriptorspec, $pipes);

  if (is_resource($escaper)) {
    fwrite($pipes[0], $input);
    fclose($pipes[0]);
    unset($input);

    $output = trim(stream_get_contents($pipes[1]));
    fclose($pipes[1]);

    $returnVal = proc_close($escaper);
    assert($returnVal == 0, "Encoder tool failed. Returned $returnVal.");
  } else {
    throw new \UnexpectedValueException("Unable to fork encoder tool ($cmd)");
  }

  $output = explode("<|\n", $output);

  // Remove last delimiter as well
  $lastIndex = count($output) - 1;
  $output[$lastIndex] = str_replace("<|", "", $output[$lastIndex]);

  foreach ($output as $row) {
    if (empty($row)) {
      continue;
    }
    $line = explode(",|>", $row);
    $id = $line[0];
    $recodedContent = $line[1];
    if ($rows[$id] == $recodedContent) {
      unset($rows[$id]);
    } else {
      $rows[$id] = $recodedContent;
    }
  }
}

/**
 * Fetch the data from tables and update the recoded values
 * @param DbManager $dbManager
 */
function startRecodingTables($dbManager, $MODDIR)
{
  if ($dbManager == NULL) {
    echo "No connection object passed!\n";
    return false;
  }

  $sql = "SHOW client_encoding;";
  $statement = __METHOD__ . ".getOldClientEncoding";
  $oldEnc = $dbManager->getSingleRow($sql, array(), $statement);
  $oldEnc = $oldEnc['client_encoding'];

  $sql = "SET client_encoding = 'SQL_ASCII';";
  $dbManager->queryOnce($sql);
  $where = " WHERE (content IS NOT NULL AND content != '')";

  foreach (ENCODE_TABLES as $table) {
    $countSql = "SELECT count(*) AS cnt FROM $table $where;";
    $countStatement = __METHOD__ . ".getCountFor.$table";
    $contentSql = "SELECT " . $table . "_pk AS id, content " .
      "FROM $table $where " .
      "ORDER BY " . $table . "_pk " .
      "LIMIT $1 OFFSET $2;";
    $contentStatement = __METHOD__ . ".getContentFor.$table";

    $length = $dbManager->getSingleRow($countSql, array(), $countStatement);
    $length = $length['cnt'];
    echo "*** Recoding $length records from $table table ***\n";
    $i = 0;
    $updatedCount = 0;
    while ($i < $length) {
      $rows = $dbManager->getRows($contentSql, array(MAX_ROW_SIZE, $i),
        $contentStatement);
      $i += count($rows);
      $data = array();
      foreach ($rows as $row) {
        if (empty($row['content'])) {
          continue;
        }
        $data[$row['id']] = $row['content'];
      }
      recodeContents($data, $MODDIR);
      $updatedCount += updateRecodedValues($dbManager, $table, $data);
      if ($i % 10000 == 0) {
        echo "  Processed $i rows for $table table\n";
      }
    }
    echo "*** Recoded $updatedCount for $table table ***\n";
  }

  $sql = "SET client_encoding = '$oldEnc';";
  $dbManager->queryOnce($sql);
}

/**
 * Get fossology DB's encoding
 * @param DbManager $dbManager
 * @return string Encoding of current DB
 */
function getDbEncoding($dbManager)
{
  $dbName = $GLOBALS["SysConf"]["DBCONF"]["dbname"];
  $sql = "SELECT pg_encoding_to_char(encoding) AS encoding " .
    "FROM pg_database WHERE datname = '$dbName';";
  $row = $dbManager->getSingleRow($sql);
  return $row["encoding"];
}

/**
 * Check if the DB encoding is SQL_ASCII, change it to UTF-8
 * @param DbManager $dbManager
 */
function updateDbEncoding($dbManager)
{
  if (strcasecmp(getDbEncoding($dbManager), 'sql_ascii') == 0) {
    $dbName = $GLOBALS["SysConf"]["DBCONF"]["dbname"];
    $sql = "UPDATE pg_database SET encoding = pg_char_to_encoding('UTF8') " .
      "WHERE datname = '$dbName';";
    $cmd = 'su postgres --command "psql --command=\"BEGIN;' . $sql .
      '\"COMMIT;"';
    shell_exec($cmd);
  }
}

/**
 * Check if migration is required.
 * @param DbManager $dbManager
 * @return boolean True if migration is required, false otherwise
 */
function checkMigrate3738Required($dbManager)
{
  if ($dbManager == NULL){
    echo "No connection object passed!\n";
    return false;
  }

  $migRequired = true;
  foreach (ENCODE_TABLES as $table) {
    if (DB_TableExists($table) != 1) {
      $migRequired = false;
      break;
    }
  }
  if ($migRequired) {
    $migRequired = false;
    if (strcasecmp(getDbEncoding($dbManager), 'sql_ascii') == 0) {
      $migRequired = true;
    }
  }

  return $migRequired;
}

/**
 * Start the recoding process
 * @param DbManager $dbManager
 * @param string $MODDIR Location of LIB
 * @param boolean $force True to force the process
 * @return number Return code
 */
function recodeTables($dbManager, $MODDIR, $force = false)
{
  if (! checkMigrate3738Required($dbManager)) {
    // Migration not required
    // Still change encoding of database from SQL_ASCII to UTF-8
    updateDbEncoding($dbManager);
    return 0;
  }
  $totalRecords = calculateNumberOfRecordsToRecode($dbManager);

  if ($totalRecords == 0) {
    // Migration not required
    return 0;
  }
  $envYes = getenv('FOSSENCODING');
  if (!$force) {
    $force = !empty($envYes);
  }

  echo "*** Recoding $totalRecords records to UTF-8. ***\n";

  if (!$force && $totalRecords > 500000) {
    $REDCOLOR = "\033[0;31m";
    $NOCOLOR = "\033[0m";
    echo "\n*********************************************************" .
      "***********************\n";
    echo "*** " . $REDCOLOR . "Error, script will take too much time. Not " .
      "recoding entries.            " . $NOCOLOR . " ***\n";
    echo "*** Either rerun the fo-postinstall with \"--force-encode\" flag " .
      "or set        ***\n" .
      "*** \"FOSSENCODING=1\" in environment or run script at             " .
      "            ***\n";
    echo "*** \"" . dirname(__FILE__) .
    "/dbmigrate_change_db_encoding.php\" to continue as a separate process ***\n";
    echo "*********************************************************" .
      "***********************\n";
    return -25;
  }

  try {
    echo "*** Recoding entries in copyright and sister tables ***\n";
    startRecodingTables($dbManager, $MODDIR);
    updateDbEncoding($dbManager);
  } catch (Exception $e) {
    echo "*** Something went wrong. Try again! ***\n";
    $dbManager->rollback();
    return -1;
  }
}

/**
 * Migration from FOSSology 3.7.0 to 3.8.0
 * @param DbManager $dbManager
 */
function Migrate_37_38($dbManager, $MODDIR)
{
  if (! checkMigrate3738Required($dbManager)) {
    // Migration not required
    // Still change encoding of database from SQL_ASCII to UTF-8
    updateDbEncoding($dbManager);
    return;
  }
  try {
    recodeTables($dbManager, $MODDIR);
  } catch (Exception $e) {
    echo "Something went wrong. Try running postinstall again!\n";
    $dbManager->rollback();
  }
}
