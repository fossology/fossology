<?php
/*
 SPDX-FileCopyrightText: © 2026 Fossology contributors

 SPDX-License-Identifier: GPL-2.0-only
*/

use Fossology\Lib\Db\DbManager;

/**
 * @file
 * @brief Retire deprecated license shortnames an agent may have re-created
 *
 * A shortname corrected by a release migration comes back whenever an agent
 * still reports the old name: every scan after the upgrade inserts the
 * deprecated row again. Release migrations are version gated and never run a
 * second time, so that correction has to be repeated unconditionally.
 *
 * For each pair the migration either renames the deprecated row, or -- when
 * both rows exist -- moves every reference onto the current row and drops the
 * deprecated one.
 */

/**
 * @brief Deprecated shortname => current shortname
 * @return array
 */
function getLegacyLicenseNames()
{
  return array(
    /* nomos reported GFDL-1.3 until the SPDX -only form was adopted */
    'GFDL-1.3' => 'GFDL-1.3-only',
  );
}

/**
 * @brief Columns referencing license_ref.rf_pk, as table => column pairs
 * @return array
 */
function getLicenseRefReferences()
{
  return array(
    array('clearing_event', 'rf_fk'),
    array('custom_phrase_license_map', 'rf_fk'),
    array('license_file', 'rf_fk'),
    array('license_map', 'rf_fk'),
    array('license_map', 'rf_parent'),
    array('license_set_bulk', 'rf_fk'),
    array('obligation_candidate_map', 'rf_fk'),
    array('obligation_map', 'rf_fk'),
    array('upload_clearing_license', 'rf_fk'),
    array('comp_result', 'first_rf_fk'),
    array('comp_result', 'second_rf_fk'),
    array('license_rules', 'first_rf_fk'),
    array('license_rules', 'second_rf_fk'),
  );
}

/**
 * @brief Run a parameterised write; getSingleRow() would fetch a command result
 * @param DbManager $dbManager
 * @param string $sql
 * @param array $params
 * @param string $statementName
 * @return void
 */
function legacyLicenseWrite(DbManager $dbManager, $sql, $params, $statementName)
{
  $dbManager->prepare($statementName, $sql);
  $res = $dbManager->execute($statementName, $params);
  $dbManager->freeResult($res);
}

/**
 * @brief Rename a license, or merge it away when the target already exists
 * @param DbManager $dbManager
 * @param string $old Deprecated shortname
 * @param string $new Current shortname
 * @param bool $verbose
 * @return void
 */
function renameOrMergeLicense(DbManager $dbManager, $old, $new, $verbose = false)
{
  $row = $dbManager->getSingleRow(
    "SELECT
       (SELECT rf_pk FROM license_ref WHERE rf_shortname=$1 LIMIT 1) AS old_id,
       (SELECT rf_pk FROM license_ref WHERE rf_shortname=$2 LIMIT 1) AS new_id",
    array($old, $new),
    __FUNCTION__ . '.getIds'
  );

  $oldId = (!empty($row) && !empty($row['old_id'])) ? intval($row['old_id']) : 0;
  $newId = (!empty($row) && !empty($row['new_id'])) ? intval($row['new_id']) : 0;

  if ($oldId === 0 || $oldId === $newId) {
    return;
  }

  if ($newId === 0) {
    legacyLicenseWrite($dbManager, "UPDATE license_ref SET rf_shortname=$1 WHERE rf_pk=$2",
      array($new, $oldId), __FUNCTION__ . '.rename');
    if ($verbose) {
      print "renamed license $old to $new\n";
    }
    return;
  }

  foreach (getLicenseRefReferences() as $ref) {
    list ($table, $column) = $ref;
    if (!$dbManager->existsTable($table)) {
      continue;
    }
    legacyLicenseWrite($dbManager, "UPDATE $table SET $column=$1 WHERE $column=$2",
      array($newId, $oldId), __FUNCTION__ . ".move.$table.$column");
  }

  legacyLicenseWrite($dbManager, "DELETE FROM license_ref WHERE rf_pk=$1",
    array($oldId), __FUNCTION__ . '.deleteLegacy');
  if ($verbose) {
    print "merged license $old into existing $new\n";
  }
}

/**
 * @param DbManager $dbManager
 * @param bool $verbose
 * @return void
 */
function Migrate_Legacy_License_Names(DbManager $dbManager, $verbose = false)
{
  if (!$dbManager->existsTable('license_ref')) {
    return;
  }

  $dbManager->begin();
  foreach (getLegacyLicenseNames() as $old => $new) {
    renameOrMergeLicense($dbManager, $old, $new, $verbose);
  }
  $dbManager->commit();
}
