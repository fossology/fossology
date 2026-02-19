<?php
/*
 SPDX-FileCopyrightText: © 2026 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

use Fossology\Lib\Db\DbManager;

/**
 * @file
 * @brief Migrate duplicate CMU license entry to CMU-Mach
 *
 * Historically, the reference license list contained two nearly identical
 * entries: "CMU" and "CMU-Mach". SPDX only defines "CMU-Mach" (and MIT-CMU),
 * but not a generic "CMU" license. This migration:
 *  - ensures the database contains only the CMU-Mach license entry
 *  - rewrites rf_fk references from CMU => CMU-Mach where applicable
 *  - updates stored report metadata where the standalone token "CMU" is used
 *    (without touching "MIT-CMU")
 */

/**
 * @param DbManager $dbManager
 * @param bool $verbose
 * @return void
 */
function Migrate_Cmu_Mach(DbManager $dbManager, $verbose = false)
{
  if (!$dbManager->existsTable('license_ref')) {
    return;
  }

  $dbManager->begin();

  $row = $dbManager->getSingleRow(
    "SELECT
       (SELECT rf_pk FROM license_ref WHERE rf_shortname='CMU' LIMIT 1) AS cmu_id,
       (SELECT rf_pk FROM license_ref WHERE rf_shortname='CMU-Mach' LIMIT 1) AS cmu_mach_id",
    array(),
    __METHOD__ . '.getIds'
  );

  $cmuId = (!empty($row) && !empty($row['cmu_id'])) ? intval($row['cmu_id']) : 0;
  $cmuMachId = (!empty($row) && !empty($row['cmu_mach_id'])) ? intval($row['cmu_mach_id']) : 0;

  // Case A: only legacy CMU exists -> rename it to CMU-Mach (no duplicate created).
  if ($cmuId > 0 && $cmuMachId === 0) {
    $dbManager->queryOnce(
      "UPDATE license_ref
         SET rf_shortname='CMU-Mach',
             rf_fullname='CMU Mach License',
             rf_url='https://www.cs.cmu.edu/~410/licenses.html'
       WHERE rf_pk=$cmuId",
      __METHOD__ . '.renameOnlyCmu'
    );
    $cmuMachId = $cmuId;
    $cmuId = 0;
  }

  // Case B: both exist -> move references, then delete the legacy CMU row.
  if ($cmuId > 0 && $cmuMachId > 0 && $cmuId !== $cmuMachId) {
    // Update known rf_fk / license FK columns referencing license_ref.
    $updates = [
      "UPDATE clearing_event SET rf_fk=$cmuMachId WHERE rf_fk=$cmuId",
      "UPDATE license_file SET rf_fk=$cmuMachId WHERE rf_fk=$cmuId",
      "UPDATE license_map SET rf_fk=$cmuMachId WHERE rf_fk=$cmuId",
      "UPDATE license_set_bulk SET rf_fk=$cmuMachId WHERE rf_fk=$cmuId",
      "UPDATE obligation_map SET rf_fk=$cmuMachId WHERE rf_fk=$cmuId",
      "UPDATE upload_clearing_license SET rf_fk=$cmuMachId WHERE rf_fk=$cmuId",

      "UPDATE comp_result SET first_rf_fk=$cmuMachId WHERE first_rf_fk=$cmuId",
      "UPDATE comp_result SET second_rf_fk=$cmuMachId WHERE second_rf_fk=$cmuId",
      "UPDATE license_rules SET first_rf_fk=$cmuMachId WHERE first_rf_fk=$cmuId",
      "UPDATE license_rules SET second_rf_fk=$cmuMachId WHERE second_rf_fk=$cmuId",
    ];

    foreach ($updates as $sql) {
      $dbManager->queryOnce($sql, __METHOD__ . '.moveRefs');
    }

    // Only delete if nothing references the old CMU row anymore.
    $dbManager->queryOnce(
      "DELETE FROM license_ref WHERE rf_pk=$cmuId",
      __METHOD__ . '.deleteLegacyCmu'
    );
  }

  if ($dbManager->existsTable('report_info')) {
    $pattern = '(^|[^A-Za-z0-9-])CMU([^A-Za-z0-9-]|$)';
    $replacement = '\\1CMU-Mach\\2';

    $stmtName = __METHOD__ . '.updateReportInfoExcludedObligations';
    $sql = "UPDATE report_info
          SET ri_excluded_obligations = regexp_replace(COALESCE(ri_excluded_obligations::text, ''), $1, $2, 'g')::json
        WHERE ri_excluded_obligations IS NOT NULL
          AND ri_excluded_obligations::text ~ $1";
    $dbManager->prepare($stmtName, $sql);
    $res = $dbManager->execute($stmtName, array($pattern, $replacement));
    $dbManager->freeResult($res);
  }

  $dbManager->commit();
}

