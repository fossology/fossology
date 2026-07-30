<?php
/*
 SPDX-FileCopyrightText: © 2026 Fossology contributors

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @file
 * @brief Drop the FK on custom_phrase_license_map.rf_fk so kotoba phrases
 * can reference candidate licenses, not just license_ref.
 *
 * Removing the CONSTRAINT entry from core-schema.dat only affects fresh
 * installs; libschema.php::dropConstraints() never drops a constraint
 * whose name is absent from the target schema, so existing databases
 * keep the FK unless it is dropped explicitly here.
 */

/**
 * Drop custom_phrase_license_map_rf_fk_fkey if it still exists.
 */
function Kotoba_candidate_license_migration(): void
{
    global $PG_CONN;

    $sql = "ALTER TABLE \"custom_phrase_license_map\" "
         . "DROP CONSTRAINT IF EXISTS \"custom_phrase_license_map_rf_fk_fkey\";";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    pg_free_result($result);
}
