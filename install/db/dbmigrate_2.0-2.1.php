<?php
/*
 SPDX-FileCopyrightText: © 2012-2014 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @file dbmigrate_2.0-2.1.php
 * @brief This file is called by fossinit.php to migrate from
 *        a 2.0 database to 2.1.
 *
 * This should be called after fossinit calls apply_schema.
 **/


/**
 * \brief Migrate to the uploadtree_a table
 *
 * \param boolean $DryRun Do not create the table, just print the sql.
 *
 * \return int 0 on success, 1 on failure
 **/
function Migrate_20_21($DryRun)
{
  // Check if uploadtree_a already inherits from uploadtree.  If so, we are done.
  $sql = "SELECT EXISTS (SELECT 1 FROM pg_catalog.pg_inherits WHERE inhrelid = 'public.uploadtree_a'::regclass::oid);";
  $row = RunSQL($sql, $DryRun);
  /** on fedora 18, the column name is 'exist', on other distritution, it is '?column?' */
  foreach ($row as $exist_key => $exist_value) {
  }

  if (@$exist_value == 't') 
  {
    if ($DryRun) {
      echo __FUNCTION__.": Data previously migrated.\n";
    }
    return 0;  // migration has already happened
  }

  // Is there data in uploadtree?  If so then we need to migrate
  $sql = "select uploadtree_pk from uploadtree limit 1";
  $row = RunSQL($sql, $DryRun);
  if (!empty($row))
  {
    echo "Migrating existing uploadtree data.\n";

    // drop uploadtree_a, it was put there by core schema for new installs only.
    $sql = "drop table uploadtree_a";
    RunSQL($sql, $DryRun);

    // rename uploadtree to uploadtree_a
    $sql = "alter table uploadtree rename to uploadtree_a";
    RunSQL($sql, $DryRun);

    // create new uploadtree table
    $sql = "create table uploadtree (like uploadtree_a INCLUDING DEFAULTS INCLUDING CONSTRAINTS INCLUDING INDEXES)";
    RunSQL($sql, $DryRun);

    // Fix the foreign keys that moved when the table was renamed
    $sql = "alter table uploadtree add foreign key (upload_fk) references upload(upload_pk) on delete cascade";
    RunSQL($sql, $DryRun);
  }

  // Fix the forieign keys removed when the table was renamed
  $sql = "SELECT conname from pg_constraint where conname= 'uploadtree_a_upload_fk_fkey';";
  $row = RunSQL($sql, $DryRun);
  if (empty($row)) {
    $sql = "alter table uploadtree_a add foreign key (upload_fk) references upload(upload_pk) on delete cascade";
    RunSQL($sql, $DryRun);
  }

  // fix uploadtree_tablename
  $sql = "update upload set uploadtree_tablename='uploadtree_a' where uploadtree_tablename is null";
  RunSQL($sql, $DryRun);

  // have uploadtreee_a inherit uploadtree
  $sql = "alter table uploadtree_a inherit uploadtree";
  RunSQL($sql, $DryRun);
                    
  return 0;  // success
} // Migrate_20_21

/**
 * @brief Run a SQL query and return the result array
 *
 * @param string $sql The SQL query to be executed
 * @param boolean $DryRun Do not create the table, just print the sql.
 *
 * @return mixed Result of $sql query
 **/
function RunSQL($sql, $DryRun)
{
  global $PG_CONN;
  $row = '';

  if ($DryRun)
    echo "DryRun: $sql\n";
  else
  {
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $row = pg_fetch_assoc($result);
    pg_free_result($result);
  }
  return $row;
}
