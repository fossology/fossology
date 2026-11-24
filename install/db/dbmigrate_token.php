<?php
/*
 SPDX-FileCopyrightText: © 2026 Siemens AG
 SPDX-FileContributor: Dearsh Oberoi <dearsh.oberoi@siemens.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

function Token_timestamp_migration(): void
{
    global $PG_CONN;

    $sql = "ALTER TABLE \"personal_access_tokens\"\n"
                . "ALTER COLUMN \"created_on\" TYPE TIMESTAMP WITH TIME ZONE USING \"created_on\"::TIMESTAMP WITH TIME ZONE, "
                . "ALTER COLUMN \"created_on\" SET DEFAULT NOW();";
    $result_alter_column = pg_query($PG_CONN, $sql);
    DBCheckResult($result_alter_column, $sql, __FILE__, __LINE__);
    pg_free_result($result_alter_column);

    $sql = "ALTER TABLE \"personal_access_tokens\"\n"
                . "ALTER COLUMN \"expire_on\" TYPE TIMESTAMP WITH TIME ZONE USING \"expire_on\"::TIMESTAMP WITH TIME ZONE;";
    $result_alter_column = pg_query($PG_CONN, $sql);
    DBCheckResult($result_alter_column, $sql, __FILE__, __LINE__);
    pg_free_result($result_alter_column);
}