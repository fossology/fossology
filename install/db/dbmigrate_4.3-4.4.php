<?php
/*
 SPDX-FileCopyrightText: © 2025 Harshit Gandhi <gandhiharshit716@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @file
 * @brief Migrate DB from release 4.3.0 to 4.4.0
 */

/**
 * Migration from FOSSology 4.3.0 to 4.4.0
 * @param bool $verbose Verbose?
 */
function Migrate_43_44(bool $verbose): void
{
  global $PG_CONN;

  if (!$PG_CONN) {
    echo "ERROR: No database connection\n";
    return;
  }

  // Check if custom_phrase table already exists
  $sql = "SELECT EXISTS (
    SELECT FROM information_schema.tables 
    WHERE table_schema = 'public' 
    AND table_name = 'custom_phrase'
  )";
  $result = pg_query($PG_CONN, $sql);
  $exists = pg_fetch_result($result, 0, 0);
  
  if ($exists === 't') {
    if ($verbose) {
      echo "Table custom_phrase already exists, skipping creation.\n";
    }
    return;
  }

  if ($verbose) {
    echo "Creating custom_phrase table...\n";
  }

  // Create the custom_phrase table
  $createTableSQL = "
    CREATE TABLE custom_phrase (
      cp_pk SERIAL PRIMARY KEY,
      user_fk INTEGER,
      group_fk INTEGER,
      text TEXT NOT NULL,
      text_md5 VARCHAR(32) NOT NULL,
      acknowledgement TEXT,
      comments TEXT,
      created_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      is_active BOOLEAN DEFAULT TRUE
    );
  ";

  $result = pg_query($PG_CONN, $createTableSQL);
  if (!$result) {
    echo "ERROR: Failed to create custom_phrase table: " . pg_last_error($PG_CONN) . "\n";
    return;
  }

  // Create the junction table for custom_phrase-license mapping
  $createJunctionTableSQL = "
    CREATE TABLE custom_phrase_license_map (
      cp_pk INTEGER NOT NULL REFERENCES custom_phrase(cp_pk) ON DELETE CASCADE,
      rf_pk INTEGER NOT NULL REFERENCES license_ref(rf_pk) ON DELETE CASCADE,
      PRIMARY KEY (cp_pk, rf_pk)
    );
  ";

  $result = pg_query($PG_CONN, $createJunctionTableSQL);
  if (!$result) {
    echo "ERROR: Failed to create custom_phrase_license_map table: " . pg_last_error($PG_CONN) . "\n";
    return;
  }

  // Create indexes
  $indexSQL = [
    "CREATE INDEX cp_user_group_idx ON custom_phrase(user_fk, group_fk);",
    "CREATE UNIQUE INDEX cp_text_md5_unique_idx ON custom_phrase(text_md5);",
    "CREATE INDEX cplm_cp_pk_idx ON custom_phrase_license_map(cp_pk);",
    "CREATE INDEX cplm_rf_pk_idx ON custom_phrase_license_map(rf_pk);"
  ];

  foreach ($indexSQL as $sql) {
    $result = pg_query($PG_CONN, $sql);
    if (!$result) {
      echo "ERROR: Failed to create index: " . pg_last_error($PG_CONN) . "\n";
      return;
    }
  }

  // Grant permissions to all current users
  $permissionSQL = [
    "GRANT SELECT, INSERT, UPDATE, DELETE ON custom_phrase TO PUBLIC;",
    "GRANT USAGE, SELECT ON SEQUENCE custom_phrase_cp_pk_seq TO PUBLIC;",
    "GRANT SELECT, INSERT, UPDATE, DELETE ON custom_phrase_license_map TO PUBLIC;"
  ];

  foreach ($permissionSQL as $sql) {
    $result = pg_query($PG_CONN, $sql);
    if (!$result) {
      echo "ERROR: Failed to grant permissions: " . pg_last_error($PG_CONN) . "\n";
      return;
    }
  }

  // Set default privileges for future users
  $defaultPrivilegesSQL = [
    "ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO PUBLIC;",
    "ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT USAGE, SELECT ON SEQUENCES TO PUBLIC;"
  ];

  foreach ($defaultPrivilegesSQL as $sql) {
    $result = pg_query($PG_CONN, $sql);
    if (!$result) {
      echo "ERROR: Failed to set default privileges: " . pg_last_error($PG_CONN) . "\n";
      return;
    }
  }

  if ($verbose) {
    echo "Successfully created custom_phrase table with indexes and permissions.\n";
  }
} 