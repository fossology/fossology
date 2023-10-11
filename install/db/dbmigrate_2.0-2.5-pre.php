<?php
/*
 SPDX-FileCopyrightText: © 2014 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @file dbmigrate_2.0-2.5-pre.php
 * @brief This file is called by fo-postinstall, 
 *        2.0 database to 2.5. 
 *
 * This should be called before fossinit calls apply_schema
 **/


/**
 * \brief Delete from copyright where pfile_fk not in (select pfile_pk from pfile)
 * add foreign constraint on copyright pfile_fk if not exist
 *
 * \return int 0 on success, 1 on failure
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
