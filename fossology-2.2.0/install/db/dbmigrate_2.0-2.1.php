<?php
/***********************************************************
 Copyright (C) 2012-2013 Hewlett-Packard Development Company, L.P.

 This program is free software; you can redistribute it and/or
 modify it under the terms of the GNU General Public License
 version 2 as published by the Free Software Foundation.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License along
 with this program; if not, write to the Free Software Foundation, Inc.,
 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 ***********************************************************/

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
 * \param $DryRun Do not create the table, just print the sql.
 *
 * \return 0 on success, 1 on failure
 **/
function Migrate_20_21($DryRun)
{
  // Check if uploadtree_a already inherits from uploadtree.  If so, we are done.
  $sql = "SELECT EXISTS (SELECT 1 FROM pg_catalog.pg_inherits WHERE inhrelid = 'public.uploadtree_a'::regclass::oid);";
  $row = RunSQL($sql, $DryRun);
  /** on fedora 18, the column name is 'exist', on other distritution, it is '?column?' */
  foreach ($row as $exist_key => $exist_value) {
  }

  if ($exist_value == 't') 
  {
    echo "Data previously migrated.\n";
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
  $sql = "alter table uploadtree_a add foreign key (upload_fk) references upload(upload_pk) on delete cascade";
  RunSQL($sql, $DryRun);

  // fix uploadtree_tablename
  $sql = "update upload set uploadtree_tablename='uploadtree_a' where uploadtree_tablename is null";
  RunSQL($sql, $DryRun);

  // have uploadtreee_a inherit uploadtree
  $sql = "alter table uploadtree_a inherit uploadtree";
  RunSQL($sql, $DryRun);
                    
  return 0;  // success
} // Migrate_20_21

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

?>
