<?php
/*
 SPDX-FileCopyrightText: © 2018 Siemens AG
 Author: Gaurav Mishra <mishra.gaurav@siemens.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @file
 * @brief Migrate DB from release 3.3.0 to 3.4.0 with new constraints
 */

/**
 * @brief Delete all rows from the table which does not have reference.
 *
 * For foreign key constraints.
 * @param DbManager $dbManager DB Manager to use
 * @param string $tableToClean    Dirty table
 * @param string $foreignKey      Foreign key of dirty table
 * @param string $referenceTable  Table to be referenced
 * @param string $referenceKey    Reference key of referenced table
 * @param boolean $dryRun         Set TRUE to make a dry run
 * @return integer Number of entries deleted
 */
function cleanTableForeign($dbManager, $tableToClean, $foreignKey, $referenceTable, $referenceKey, $dryRun)
{
  if($dbManager == NULL){
    echo "No connection object passed!\n";
    return false;
  }
  if(!(DB_TableExists($tableToClean) == 1 && DB_TableExists($referenceTable) == 1)) {
    // Table does not exists (migrating from old version)
    echo "Table $tableToClean or $referenceTable does not exists, not cleaning!\n";
    return 0;
  }

  $sql = "";
  if($dryRun) {
    $sql = "
SELECT count(*) AS count FROM $tableToClean
WHERE NOT EXISTS (
  SELECT 1 FROM $referenceTable
  WHERE $tableToClean.$foreignKey = $referenceTable.$referenceKey
);
";
  } else {
    $sql = "
WITH deleted AS (
  DELETE FROM $tableToClean
  WHERE NOT EXISTS (
    SELECT 1 FROM $referenceTable
    WHERE $tableToClean.$foreignKey = $referenceTable.$referenceKey
  ) RETURNING 1
) SELECT count(*) AS count FROM deleted;
";
  }
  return intval($dbManager->getSingleRow($sql, [],
    "cleanTableForeign." . $tableToClean . $foreignKey . "." . $referenceTable . $referenceKey)['count']);
}

/**
 * @brief Remove redundant rows based on values in columnNames.
 *
 * For unique constraints.
 * @param DbManager $dbManager
 * @param string $tableName
 * @param string $primaryKey
 * @param string[] $columnNames
 * @param boolean $dryRun
 * @return integer Number of entries deleted
 */
function cleanWithUnique($dbManager, $tableName, $primaryKey, $columnNames, $dryRun)
{
  if($dbManager == NULL){
    echo "No connection object passed!\n";
    return false;
  }
  if(DB_TableExists($tableName) != 1) {
    // Table does not exists (migrating from old version)
    echo "Table $tableName does not exists, not cleaning!\n";
    return 0;
  }

  $sql = "";
  if($dryRun) {
    $sql = "
SELECT count(*) AS count
FROM (
  SELECT $primaryKey, ROW_NUMBER() OVER (
    PARTITION BY " . implode(",", $columnNames) .
    " ORDER BY $primaryKey
  ) AS rnum
  FROM $tableName
) a
WHERE a.rnum > 1;
";
  } else {
    $sql = "
WITH deleted AS (
  DELETE FROM $tableName
  WHERE $primaryKey IN (
    SELECT $primaryKey
    FROM (
      SELECT $primaryKey, ROW_NUMBER() OVER (
        PARTITION BY " . implode(",", $columnNames) .
        " ORDER BY $primaryKey
      ) AS rnum
      FROM $tableName
    ) a
    WHERE a.rnum > 1
  ) RETURNING 1
) SELECT count(*) AS count FROM deleted;
";
  }
  return intval($dbManager->getSingleRow($sql, [],
    "cleanWithUnique." . $tableName . "." . implode(".", $columnNames))['count']);
}

/**
 * Migration from FOSSology 3.3.0 to 3.4.0
 * @param DbManager $dbManager
 * @param boolean $dryRun
 */
function Migrate_33_34($dbManager, $dryRun)
{
  if(DB_ConstraintExists('group_user_member_user_group_ukey', $GLOBALS["SysConf"]["DBCONF"]["dbname"])) {
    // The last constraint also cleared, no need for re-run
    return;
  }
  try {
    echo "*** Cleaning tables for new constraints ***\n";
    $count = 0;
    $tableMap = [
      ["author", "agent_fk", "agent", "agent_pk"],
      ["author", "pfile_fk", "pfile", "pfile_pk"],
      ["bucket_container", "bucket_fk", "bucket_def", "bucket_pk"],
      ["bucket_file", "bucket_fk", "bucket_def", "bucket_pk"],
      ["bucket_file", "pfile_fk", "pfile", "pfile_pk"],
      ["copyright", "agent_fk", "agent", "agent_pk"],
      ["copyright_decision", "pfile_fk", "pfile", "pfile_pk"],
      ["ecc", "agent_fk", "agent", "agent_pk"],
      ["ecc", "pfile_fk", "pfile", "pfile_pk"],
      ["ecc_decision", "pfile_fk", "pfile", "pfile_pk"],
      ["highlight_keyword", "pfile_fk", "pfile", "pfile_pk"],
      ["keyword", "agent_fk", "agent", "agent_pk"],
      ["keyword", "pfile_fk", "pfile", "pfile_pk"],
      ["keyword_decision", "pfile_fk", "pfile", "pfile_pk"],
      ["pkg_deb_req", "pkg_fk", "pkg_deb", "pkg_pk"],
      ["pkg_rpm_req", "pkg_fk", "pkg_rpm", "pkg_pk"],
      ["report_cache", "report_cache_uploadfk", "upload", "upload_pk"],
      ["report_info", "upload_fk", "upload", "upload_pk"],
      ["reportgen", "upload_fk", "upload", "upload_pk"],
      ["upload", "pfile_fk", "pfile", "pfile_pk"],
      ["upload_clearing_license", "upload_fk", "upload", "upload_pk"]
    ];
    $dbManager->queryOnce("BEGIN;");

    // Foreign key constraints
    foreach ($tableMap as $mapRow) {
      $count += cleanTableForeign($dbManager, $mapRow[0], $mapRow[1], $mapRow[2], $mapRow[3], $dryRun);
    }

    // Primary constraints
    $count += cleanWithUnique($dbManager, "obligation_ref", "ctid", ["ob_pk"], $dryRun);
    $count += cleanWithUnique($dbManager, "report_info", "ctid", ["ri_pk"], $dryRun);

    // Unique constraints
    $count += cleanWithUnique($dbManager, "obligation_ref", "ob_pk", ["ob_md5"], $dryRun);
    $count += cleanWithUnique($dbManager, "group_user_member", "group_user_member_pk",
      ["user_fk", "group_fk"], $dryRun);
    $dbManager->queryOnce("COMMIT;");
    echo "Removed $count rows from tables with new constraints\n";
  } catch (Exception $e) {
    echo "Something went wrong. Try running postinstall again!\n";
    $dbManager->queryOnce("ROLLBACK;");
  }
}
