<?php
/*
 SPDX-FileCopyrightText: © 2026 Siemens AG
 SPDX-FileContributor: Krrish Biswas <krrishbiswas175@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @file
 * @brief Migrate DB from release 4.6.0 to 4.7.0
 *
 * Renames legacy FOSSology license shortnames to their SPDX-compliant
 * equivalents using the SPDX "WITH" operator for license exceptions.
 *
 * References:
 *   - SPDX License List: https://spdx.org/licenses/
 *   - SPDX Exceptions: https://spdx.org/licenses/exceptions-index.html
 *   - Issue #3158
 */

use Fossology\Lib\Db\DbManager;

/**
 * Migration from FOSSology 4.6.0 to 4.7.0
 * @param DbManager $dbManager
 * @param bool $verbose Verbose?
 */
function Migrate_46_47(DbManager $dbManager, $verbose = false): void
{
  if (!$dbManager->existsTable('license_ref')) {
    return;
  }

  $shortname_array = array(
    /* old_shortname => new_shortname */

    /* ── Miscellaneous renames ─────────────────────────────────────── */
    'Alliance for Open Media Patent License 1.0' => 'AOM-Patent-1.0',
    'unRAR restriction'  => 'unRAR-restriction',
    'M+'                 => 'M-Plus',

    /* ── GPL-2.0 "or-later" (+) with exception ─────────────────────── */
    'GPL-2.0+-with-bison-exception'
        => 'GPL-2.0-or-later WITH Bison-exception-2.2',
    'GPL-2.0+-with-classpath-exception'
        => 'GPL-2.0-or-later WITH Classpath-exception-2.0',

    /* ── GPL-2.0 "only" with exception ─────────────────────────────── */
    'GPL-2.0-with-autoconf-exception'
        => 'GPL-2.0-only WITH Autoconf-exception-3.0',
    'GPL-2.0-with-bison-exception'
        => 'GPL-2.0-only WITH Bison-exception-2.2',
    'GPL-2.0-with-classpath-exception'
        => 'GPL-2.0-only WITH Classpath-exception-2.0',
    'GPL-2.0-with-font-exception'
        => 'GPL-2.0-only WITH Font-exception-2.0',
    'GPL-2.0-with-GCC-exception'
        => 'GPL-2.0-only WITH GCC-exception-3.1',

    /* ── GPL-3.0 "or-later" (+) with exception ─────────────────────── */
    'GPL-3.0+-with-bison-exception'
        => 'GPL-3.0-or-later WITH Bison-exception-2.2',
    'GPL-3.0+-with-classpath-exception'
        => 'GPL-3.0-or-later WITH Classpath-exception-2.0',

    /* ── GPL-3.0 "only" with exception ─────────────────────────────── */
    'GPL-3.0-with-autoconf-exception'
        => 'GPL-3.0-only WITH Autoconf-exception-3.0',
    'GPL-3.0-with-bison-exception'
        => 'GPL-3.0-only WITH Bison-exception-2.2',
    'GPL-3.0-with-GCC-exception'
        => 'GPL-3.0-only WITH GCC-exception-3.1',
  );

  $dbManager->begin();

  foreach ($shortname_array as $old_shortname => $new_shortname) {
    $row = $dbManager->getSingleRow(
      "SELECT
         (SELECT rf_pk FROM license_ref WHERE rf_shortname=$1 LIMIT 1) AS old_id,
         (SELECT rf_pk FROM license_ref WHERE rf_shortname=$2 LIMIT 1) AS new_id",
      array($old_shortname, $new_shortname),
      __METHOD__ . '.getIds'
    );

    $oldId = (!empty($row) && !empty($row['old_id'])) ? intval($row['old_id']) : 0;
    $newId = (!empty($row) && !empty($row['new_id'])) ? intval($row['new_id']) : 0;

    if ($oldId > 0 && $newId === 0) {
      // Case A: Only old license exists -> rename it in-place
      $dbManager->queryOnce(
        "UPDATE license_ref SET rf_shortname='$new_shortname' WHERE rf_pk=$oldId",
        __METHOD__ . '.renameLicense'
      );
    } elseif ($oldId > 0 && $newId > 0 && $oldId !== $newId) {
      // Case B: Both licenses exist -> update references to point to new ID, then delete old row
      $updates = array(
        "UPDATE clearing_event SET rf_fk=$newId WHERE rf_fk=$oldId",
        "UPDATE license_file SET rf_fk=$newId WHERE rf_fk=$oldId",
        "UPDATE license_map SET rf_fk=$newId WHERE rf_fk=$oldId",
        "UPDATE license_set_bulk SET rf_fk=$newId WHERE rf_fk=$oldId",
        "UPDATE obligation_map SET rf_fk=$newId WHERE rf_fk=$oldId",
        "UPDATE upload_clearing_license SET rf_fk=$newId WHERE rf_fk=$oldId",
        "UPDATE comp_result SET first_rf_fk=$newId WHERE first_rf_fk=$oldId",
        "UPDATE comp_result SET second_rf_fk=$newId WHERE second_rf_fk=$oldId",
        "UPDATE license_rules SET first_rf_fk=$newId WHERE first_rf_fk=$oldId",
        "UPDATE license_rules SET second_rf_fk=$newId WHERE second_rf_fk=$oldId",
      );

      foreach ($updates as $sql) {
        $dbManager->queryOnce($sql, __METHOD__ . '.moveRefs');
      }

      if ($dbManager->existsTable('license_file_audit')) {
        $dbManager->queryOnce(
          "UPDATE license_file_audit SET rf_fk=$newId WHERE rf_fk=$oldId",
          __METHOD__ . '.moveRefsAudit'
        );
      }

      $dbManager->queryOnce(
        "DELETE FROM license_ref WHERE rf_pk=$oldId",
        __METHOD__ . '.deleteLegacyLicense'
      );
    }
  }

  $dbManager->commit();
}
