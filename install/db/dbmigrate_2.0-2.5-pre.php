<?php
/***********************************************************
 Copyright (C) 2014 Hewlett-Packard Development Company, L.P.

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
 * @file dbmigrate_2.0-2.5.php
 * @brief This file is called by fo-postinstall, 
 *        2.0 database to 2.5. 
 *
 * This should be called before fossinit calls apply_schema
 **/


/**
 * \brief 
 * delete from copyright where pfile_fk not in (select pfile_pk from pfile)
 * add foreign constraint on copyright pfile_fk if not exist
 *
 * \return 0 on success, 1 on failure
 **/
function Migrate_20_25($Verbose)
{
  global $PG_CONN;

  $sql = "select count(*) from pg_class where relname='copyright';";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  $row = pg_fetch_assoc($result);
  pg_free_result($result);
  if (1 > $row['count']) {
    return 0; // fresh install, even no copyright table
  }

  $sql = "delete from copyright where pfile_fk not in (select pfile_pk from pfile);";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  pg_free_result($result);

  /** add foreign key CONSTRAINT on pfile_fk of copyrigyt table when not exist */
  $sql = "SELECT conname from pg_constraint where conname= 'copyright_pfile_fk_fkey';";
  $conresult = pg_query($PG_CONN, $sql);
  DBCheckResult($conresult, $sql, __FILE__, __LINE__);
  if (pg_num_rows($conresult) == 0) {
    $sql = "ALTER TABLE copyright ADD CONSTRAINT copyright_pfile_fk_fkey FOREIGN KEY (pfile_fk) REFERENCES pfile (pfile_pk) ON DELETE CASCADE; ";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    pg_free_result($result);
    print "add contr\n";
  }
  pg_free_result($conresult);
  return 0;
} // Migrate_20_25()

?>
