<?php
/*
 SPDX-FileCopyrightText: © 2025 Siemens AG
 SPDX-FileContributor: Dearsh Oberoi <dearsh.oberoi@siemens.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @file
 * @brief Database schema changes to make license_ref table 
 * compatible to licenses imported from LicenseDB
 * 
 * - removes the column rf_md5 and the unique constraint on it
 *   to allow duplicate licenses
 *
 * - a column rf_external_id for mapping fossology license
 *   entries to licensedb license entries for update/deletion etc
 *   will be added in core-schema.dat for full compatibility
 */

/**
 * The migration function which should be called for all fossology
 * releases above 4.6.0
 */
function LicenseDB_compatibility_migration(): void
{
    global $PG_CONN;

    $sql = "BEGIN;";
    $result_begin = pg_query($PG_CONN, $sql);
    DBCheckResult($result_begin, $sql, __FILE__, __LINE__);
    pg_free_result($result_begin);

    $sql = "ALTER TABLE \"license_ref\" DROP CONSTRAINT IF EXISTS \"rf_md5unique\";";
    $result_drop_constraint = pg_query($PG_CONN, $sql);
    DBCheckResult($result_drop_constraint, $sql, __FILE__, __LINE__);
    pg_free_result($result_drop_constraint);

    $sql = "ALTER TABLE \"obligation_ref\" DROP CONSTRAINT IF EXISTS \"obligation_ref_md5_ukey\";";
    $result_drop_constraint = pg_query($PG_CONN, $sql);
    DBCheckResult($result_drop_constraint, $sql, __FILE__, __LINE__);
    pg_free_result($result_drop_constraint);

    $sql = "ALTER TABLE \"license_ref\" DROP CONSTRAINT IF EXISTS \"license_ref_rf_shortname_key\";";
    $result_drop_constraint = pg_query($PG_CONN, $sql);
    DBCheckResult($result_drop_constraint, $sql, __FILE__, __LINE__);
    pg_free_result($result_drop_constraint);

    $sql = "DELETE FROM \"sysconfig\" WHERE variablename='LicenseDBURL';";
    $result_delete_old_sysvar = pg_query($PG_CONN, $sql);
    DBCheckResult($result_delete_old_sysvar, $sql, __FILE__, __LINE__);
    pg_free_result($result_delete_old_sysvar);

    $sql = "COMMIT;";
    $result_end = pg_query($PG_CONN, $sql);
    DBCheckResult($result_end, $sql, __FILE__, __LINE__);
    pg_free_result($result_end);
}
