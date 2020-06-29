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

/**
 * @file
 * @brief Migrate DB from release 3.6.0 to 3.7.0 with new obligation fixes
 */

/**
 * @brief Move licenses from obligation map to obligation candidate map.
 *
 * Remove licenses from obligation_map which are candidate license and add them
 * to obligation_candidate_map.
 * @param DbManager $dbManager DB Manager to use
 * @param integer $obPk        Obligation ID
 * @param boolean $verbose     Set TRUE to print verbose message
 * @return integer Number of entries deleted
 */
function moveCandidateLicenseMap($dbManager, $obPk, $verbose)
{
  if($dbManager == NULL){
    echo "No connection object passed!\n";
    return false;
  }

  $sql = "SELECT rf_fk FROM obligation_map WHERE rf_fk NOT IN (" .
    "SELECT rf_pk FROM ONLY license_ref) AND ob_fk = $1;";
  $statement = __METHOD__ . ".getLicenseList";
  $licenses = $dbManager->getRows($sql, array($obPk), $statement);
  foreach ($licenses as $license) {
    $dbManager->begin();
    if ($verbose) {
      echo "* Moving license " . $license['rf_fk'] . " to candidate map of " .
        "obligation $obPk *\n";
    }
    $sql = "SELECT om_pk FROM obligation_candidate_map WHERE " .
      "ob_fk = $1 AND rf_fk = $2;";
    $statement = __METHOD__ . ".checkMaping";
    $exists = $dbManager->getSingleRow($sql, array($obPk, $license['rf_fk']),
      $statement);
    if (empty($exists)) {
      $statement = __METHOD__ . ".insertCandidateMap";
      $dbManager->insertTableRow("obligation_candidate_map", array(
        "ob_fk" => $obPk,
        "rf_fk" => $license['rf_fk']
      ), $statement);
    }

    $sql = "DELETE FROM obligation_map WHERE ob_fk = $1 AND rf_fk = $2;";
    $statement = __METHOD__ . ".removeMap";
    $dbManager->getSingleRow($sql, array($obPk, $license['rf_fk']), $statement);
    $dbManager->commit();
  }
}

/**
 * Check if migration is required.
 * @param DbManager $dbManager
 * @return boolean True if migration is required, false otherwise
 */
function checkMigrate3637Required($dbManager)
{
  if($dbManager == NULL){
    echo "No connection object passed!\n";
    return false;
  }
  $requiredTables = array(
    "obligation_map",
    "obligation_candidate_map",
    "license_ref"
  );
  $migRequired = true;
  foreach ($requiredTables as $table) {
    if (DB_TableExists($table) != 1) {
      $migRequired = false;
      break;
    }
  }
  if ($migRequired) {
    $sql = "SELECT count(*) AS cnt FROM obligation_map WHERE rf_fk NOT IN (" .
      "SELECT rf_pk FROM ONLY license_ref);";
    $row = $dbManager->getSingleRow($sql);
    $migRequired = false;
    if (array_key_exists("cnt", $row) && $row["cnt"] > 0) {
      $migRequired = true;
    }
  }

  return $migRequired;
}

 /**
 * @brief Get all obligations and move licenses.
 * @param DbManager $dbManager
 * @param boolean $verbose
 */
function moveObligation($dbManager, $verbose)
{
  if($dbManager == NULL){
    echo "No connection object passed!\n";
    return false;
  }

  $sql = "SELECT ob_pk FROM obligation_ref;";
  $obligations = $dbManager->getRows($sql);
  foreach ($obligations as $obligation) {
    moveCandidateLicenseMap($dbManager, $obligation['ob_pk'], $verbose);
  }
}

/**
 * Check if Reuse value needs to be changed.
 * @param DbManager $dbManager
 * @return boolean True value 8 exist, false otherwise
 */
function migrateReuseValueForEnhanceWithMainLicense($dbManager)
{
  if($dbManager == NULL){
    echo "No connection object passed!\n";
    return false;
  }
  if (DB_TableExists("upload_reuse") != 1) {
    return false;
  }
  $stmt = __METHOD__;
  $sql = "SELECT exists(SELECT 1 FROM upload_reuse WHERE reuse_mode = $1 LIMIT 1)::int";
  $row = $dbManager->getSingleRow($sql, array(8), $stmt);

  if ($row['exists']) {
    echo "*** Changing enhance reuse with main license value to 6 from 8 ***\n";
    $stmt = __METHOD__."ReplaceValuesFrom8to6";
    $dbManager->prepare($stmt,
      "UPDATE upload_reuse
       SET reuse_mode = $1 WHERE reuse_mode = $2"
      );
    $dbManager->freeResult($dbManager->execute($stmt, array(6,8)));
    return true;
  }
  return false;
}
/**
 * Migration from FOSSology 3.6.0 to 3.7.0
 * @param DbManager $dbManager
 * @param boolean $dryRun
 */
function Migrate_36_37($dbManager, $verbose)
{
  migrateReuseValueForEnhanceWithMainLicense($dbManager);
  if (! checkMigrate3637Required($dbManager)) {
    // Migration not required
    return;
  }
  try {
    echo "*** Moving candidate licenses from obligation map ***\n";
    moveObligation($dbManager, $verbose);
  } catch (Exception $e) {
    echo "Something went wrong. Try running postinstall again!\n";
    $dbManager->rollback();
  }
}
