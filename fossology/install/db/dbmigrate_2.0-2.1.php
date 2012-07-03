<?php
/***********************************************************
 Copyright (C) 2012 Hewlett-Packard Development Company, L.P.

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
 * @brief This file is called by fossinit.php to create and initialize 
 *        new ARS tables when migrating from a 2.0 database to 2.1.
 *
 * This should be called after fossinit calls apply_schema.
 **/


/**
 * \brief Create the uploadtree_0 table
 *
 * \param $DryRun Do not create the table, just print the sql.
 *
 * \return 0 on success, 1 on failure
 **/
function Migrate_20_21($DryRun)
{
  /* if uploadtree_0 already exists, then do nothing but return success */
  if (DB_TableExists('uploadtree_0')) return 0;

  $sql = "alter table uploadtree rename to uploadtree_0";
  RunSQL($sql, $DryRun);

  $sql = "create table uploadtree (like uploadtree_0 INCLUDING DEFAULTS INCLUDING CONSTRAINTS INCLUDING INDEXES)";
  RunSQL($sql, $DryRun);

  $sql = "alter table uploadtree_0 inherit uploadtree";
  RunSQL($sql, $DryRun);

  $sql = "update upload set uploadtree_tablename='uploadtree_0'";
  RunSQL($sql, $DryRun);
                    
  return 0;
} // Migrate_20_21

function RunSQL($sql, $DryRun)
{
  global $PG_CONN;

  if ($DryRun)
    echo "DryRun: $sql\n";
  else
  {
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    pg_free_result($result);
  }
}

?>
