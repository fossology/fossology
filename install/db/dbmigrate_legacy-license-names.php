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
    /* nomos reported GFDL-1.3 until the SPDX -only form was adopted. */
    'GFDL-1.3' => 'GFDL-1.3-only',

    /* SPDX defines CMU-Mach and MIT-CMU, but no bare CMU. Rows survive from
     * scans made before nomos stopped emitting the name. */
    'CMU' => 'CMU-Mach',

    /* Was misspelled ('Reponsible') and is now named for the RAIL family. */
    'Reponsible-AI-Source-Code-License-v1.0' => 'RAIL-S-1.0',

    /* The hyphenated forms are FOSSology-local and not valid SPDX. */
    'GPL-2.0-with-autoconf-exception' => 'GPL-2.0-only WITH Autoconf-exception-3.0',
    'GPL-2.0-with-bison-exception' => 'GPL-2.0-only WITH Bison-exception-2.2',
    'GPL-2.0-with-classpath-exception' => 'GPL-2.0-only WITH Classpath-exception-2.0',
    'GPL-2.0-with-font-exception' => 'GPL-2.0-only WITH Font-exception-2.0',
    'GPL-2.0-with-GCC-exception' => 'GPL-2.0-only WITH GCC-exception-3.1',
    'GPL-2.0+-with-bison-exception' => 'GPL-2.0-or-later WITH Bison-exception-2.2',
    'GPL-2.0-or-later-with-Bison-2.2-exception' => 'GPL-2.0-or-later WITH Bison-exception-2.2',
    'GPL-2.0+-with-classpath-exception' => 'GPL-2.0-or-later WITH Classpath-exception-2.0',
    'GPL-3.0-with-autoconf-exception' => 'GPL-3.0-only WITH Autoconf-exception-3.0',
    'GPL-3.0-with-bison-exception' => 'GPL-3.0-only WITH Bison-exception-2.2',
    'GPL-3.0-with-classpath-exception' => 'GPL-3.0-only WITH Classpath-exception-2.0',
    'GPL-3.0-with-GCC-exception' => 'GPL-3.0-only WITH GCC-exception-3.1',
    'GPL-3.0+-with-bison-exception' => 'GPL-3.0-or-later WITH Bison-exception-2.2',
    'GPL-3.0+-with-classpath-exception' => 'GPL-3.0-or-later WITH Classpath-exception-2.0',

    /* Not valid SPDX idstrings. convertToSpdxId() prefixes at report time. */
    'Alliance for Open Media Patent License 1.0' => 'AOM-Patent-1.0',
    'unRAR restriction' => 'unRAR-restriction',
    'GPL-2.1[sic]' => 'GPL-2.1-sic',
    'GPL-2.1+[sic]' => 'GPL-2.1-or-later-sic',
    'CopyLeft[1]' => 'CopyLeft-1',
    'CopyLeft[2]' => 'CopyLeft-2',
    'GPL(rms)' => 'GPL-rms',
    'X/Open' => 'X-Open',
    'X/Open-style' => 'X-Open-style',
    'CECILL(dual)' => 'CECILL-dual',
    'ImageMagick(Apache)' => 'ImageMagick-Apache',
    'SGI_GLX' => 'SGI-GLX',
    'ANT+SharedSource' => 'ANT-SharedSource',
    'AgainstDRM' => 'Against-DRM',
    'M-Plus-Project' => 'M-Plus',
    'GPL-2.0-or-laterKDEupgradeClause' => 'GPL-2.0-or-later-with-KDE-upgrade-clause',
    'SGI_GLX-1.0' => 'SGI-GLX-1.0',
    'Public-domain(C)' => 'Public-domain-copyright',
    'UnclassifiedLicense(PS)' => 'UnclassifiedLicense-PS',
    'Empty-file-no-data!' => 'Empty-file-no-data',
    'Platform-Computing(RESTRICTED)' => 'Platform-Computing-RESTRICTED',

    /* Written exception-first; nomos uses <license>-with-<exception>. */
    'openssl-exception-AGPL-3.0-or-later' => 'AGPL-3.0-or-later-with-openssl-exception',
    'openssl-exception-GPL-2.0-or-later' => 'GPL-2.0-or-later-with-openssl-exception',
    'GPL-3.0-or-later-openssl' => 'GPL-3.0-or-later-with-openssl-exception',
    'cygwin-exception-LGPL-3.0-or-later' => 'LGPL-3.0-or-later-with-Cygwin-exception',
    'spell-checker-exception-LGPL-2.1-or-later' => 'LGPL-2.1-or-later-with-spell-checker-exception',
    'opensc-openssl-openpace-exception-gpl' => 'GPL-with-opensc-openssl-openpace-exception',

    /* Case differed from the seeded entry, so each scan created a second row. */
    'DOCBOOK' => 'Docbook',
    'MindTerm' => 'Mindterm',
    'Qt.Commercial' => 'QT.Commercial',
    'SpikeSource' => 'Spikesource',
    'VMware-EULA' => 'VMWare-EULA',
    'WxWindows' => 'wxWindows',
    'ubuntu-font-1.0' => 'Ubuntu-font-1.0',
    'universal-foss-exception-1.0' => 'Universal-FOSS-exception-1.0',
    'naist-2003' => 'NAIST-2003',
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
 * @brief Rewrite a shortname held in report_info.ri_excluded_obligations
 *
 * The column stores a JSON map of obligation topic to license names. A rename
 * leaves the deprecated name behind and the obligation stops being excluded.
 * The boundary class keeps a name from matching inside a longer one.
 *
 * @param DbManager $dbManager
 * @param string $old Deprecated shortname
 * @param string $new Current shortname
 * @return void
 */
function updateExcludedObligationNames(DbManager $dbManager, $old, $new)
{
  if (!$dbManager->existsTable('report_info')) {
    return;
  }

  /* Match the escaping the column was written with, then the one the pattern
   * needs. A literal backslash has to survive the replacement string too. */
  $oldName = trim(json_encode($old), '"');
  $newName = trim(json_encode($new), '"');
  $pattern = '(^|[^A-Za-z0-9-])' . preg_quote($oldName) . '([^A-Za-z0-9-]|$)';
  $replacement = '\1' . str_replace('\\', '\\\\', $newName) . '\2';

  legacyLicenseWrite($dbManager,
    "UPDATE report_info
        SET ri_excluded_obligations =
              regexp_replace(ri_excluded_obligations::text, $1, $2, 'g')::json
      WHERE ri_excluded_obligations::text ~ $1",
    array($pattern, $replacement), __FUNCTION__);
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
    updateExcludedObligationNames($dbManager, $old, $new);
  }
  $dbManager->commit();
}
