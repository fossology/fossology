<?php
use Fossology\Lib\BusinessRules\LicenseMap;
use Fossology\Lib\BusinessRules\ObligationMap;

/*
 SPDX-FileCopyrightText: © 2019 Siemens AG
 Author: Gaurav Mishra <mishra.gaurav@siemens.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

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
    $statement = __METHOD__ . ".candCount";
    $row = $dbManager->getSingleRow($sql, array(), $statement);
    $migRequired = false;
    if (array_key_exists("cnt", $row) && $row["cnt"] > 0) {
      $migRequired = true;
    }
  }

  return $migRequired;
}

/**
 * Get the entries for which migration is required for non concluded licenses.
 * @param DbManager $dbManager
 * @return array Entries from which require migration
 */
function getLicConObligationMigrate($dbManager)
{
  if($dbManager == NULL){
    echo "No connection object passed!\n";
    return false;
  }
  $requiredTables = array(
    "obligation_map",
    "license_ref",
    "license_map"
  );
  foreach ($requiredTables as $table) {
    if (DB_TableExists($table) != 1) {
      return array();
    }
  }

  $sql = "WITH comp AS (" . LicenseMap::getMappedLicenseRefView() .
    ") SELECT * FROM obligation_map WHERE rf_fk NOT IN " .
    "(SELECT rf_pk FROM comp);";
  $statement = __METHOD__ . ".conclusionMigrate";
  return $dbManager->getRows($sql, array(LicenseMap::CONCLUSION), $statement);
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
  if ($dbManager === NULL) {
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
    $stmt = __METHOD__ . "ReplaceValuesFrom8to6";
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
 * @brief Remove identified licenses from obligations.
 *
 * The function removes the obligation map sent from $migrationData. To do so,
 * the function first gets the list of licenses along with their parent license.
 * Then makes sure that the parent is part of the obligation to prevent data
 * loss. Then it finally removes the obligation map.
 * @param DbManager $dbManager
 * @param array     $migrationData
 */
function removeNonConcLicensesFromObligation($dbManager, $migrationData)
{
  $obligationMap = new ObligationMap($dbManager);
  $mappedLicenses = [];

  $sql = LicenseMap::getMappedLicenseRefView();
  $params = array(LicenseMap::CONCLUSION);
  $statement = __METHOD__ . ".getLicenseMap";
  $rows = $dbManager->getRows($sql, $params, $statement);
  foreach ($rows as $row) {
    $mappedLicenses[$row["rf_origin"]] = $row;
  }

  foreach ($migrationData as $row) {
    $dbManager->begin();
    $obligationId = $row["ob_fk"];
    $licenseId = $row["rf_fk"];
    $parentId = $mappedLicenses[$licenseId]["rf_pk"];

    $licenseName = $obligationMap->getShortnameFromId($row["rf_fk"]);
    $obligationTopic = $obligationMap->getTopicNameFromId($row["ob_fk"]);
    echo "    Removed '$licenseName' license from '$obligationTopic' obligation";
    if (! $obligationMap->isLicenseAssociated($obligationId, $parentId)) {
      $obligationMap->associateLicenseWithObligation($obligationId, $parentId);
      echo " replacing with parent license '" .
        $obligationMap->getShortnameFromId($parentId) . "'";
    }
    echo ".\n";
    $obligationMap->unassociateLicenseFromObligation($obligationId, $licenseId);
    $dbManager->commit();
  }
}

/**
 * Migration from FOSSology 3.6.0 to 3.7.0
 * @param DbManager $dbManager
 * @param boolean $dryRun
 */
function Migrate_36_37($dbManager, $verbose)
{
  migrateReuseValueForEnhanceWithMainLicense($dbManager);
  $candidateMigration = checkMigrate3637Required($dbManager);
  $concLicenseMigration = getLicConObligationMigrate($dbManager);
  if (! ($candidateMigration || (count($concLicenseMigration) > 0))) {
    // Migration not required
    return;
  }
  try {
    if ($candidateMigration) {
      echo "*** Moving candidate licenses from obligation map ***\n";
      moveObligation($dbManager, $verbose);
    }
    if (count($concLicenseMigration) > 0) {
      echo "*** Removed following obligation map (" .
        count($concLicenseMigration) . ") ***\n";
      removeNonConcLicensesFromObligation($dbManager, $concLicenseMigration);
    }
  } catch (Exception $e) {
    echo "Something went wrong. Try running postinstall again!\n";
    $dbManager->rollback();
  }
}
