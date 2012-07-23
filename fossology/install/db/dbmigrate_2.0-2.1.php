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
 * @brief This file is called by fossinit.php to migrate from
 *        a 2.0 database to 2.1.
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
  $sql = "alter table uploadtree_a inherit uploadtree";
  RunSQL($sql, $DryRun);

  $sql = "update upload set uploadtree_tablename='uploadtree_a'";
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
