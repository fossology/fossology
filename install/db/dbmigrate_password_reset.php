<?php
/*
  SPDX-FileCopyrightText: © 2026 FOSSology Contributors
  SPDX-License-Identifier: GPL-2.0-only
*/

function Password_reset_migration(): void
{
    global $PG_CONN;

    $sql = "CREATE TABLE IF NOT EXISTS password_reset (
        password_reset_pk SERIAL PRIMARY KEY,
        user_fk INTEGER NOT NULL REFERENCES users(user_pk) ON DELETE CASCADE,
        token_hash VARCHAR(255) NOT NULL,
        created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
        expires_at TIMESTAMP WITH TIME ZONE NOT NULL,
        used_at TIMESTAMP WITH TIME ZONE DEFAULT NULL
    );";

    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    pg_free_result($result);

    $sql_index = "CREATE INDEX IF NOT EXISTS idx_password_reset_token_hash ON password_reset(token_hash);";
    $result_index = pg_query($PG_CONN, $sql_index);
    DBCheckResult($result_index, $sql_index, __FILE__, __LINE__);
    pg_free_result($result_index);
}
