<?php
/***********************************************************
 Copyright (C) 2011 Hewlett-Packard Development Company, L.P.

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

/**********************************************************
 *  This file contains common core database functions
 **********************************************************/


/*****************************************
 DBconnect()
   Connect to database engine.
   This is a no-op if $PG_CONN already has a value.

 Params:
   $Options" = an optional list of attributes for
               connecting to the database. E.g.:
     "dbname=text host=text user=text password=text"

 If $Options is null, then connection parameters 
 will be read from Db.conf.

 Returns:
   Success: $PG_CONN, the postgres connection object
            Also, the global $PG_CONN is set.
   Failure: Error message is printed and exit
 *****************************************/
function DBconnect($Options="")
{
  global $DATADIR, $PROJECT, $SYSCONFDIR;
  global $PG_CONN;

  if (!empty($PG_CONN)) return $PG_CONN;

  $path="$SYSCONFDIR/$PROJECT/Db.conf";
  if (empty($Options))
    $PG_CONN = pg_pconnect(str_replace(";", " ", file_get_contents($path)), PGSQL_CONNECT_FORCE_NEW);
  else
    $PG_CONN = pg_pconnect(str_replace(";", " ", $Options), PGSQL_CONNECT_FORCE_NEW);

  if (empty($PG_CONN))
  {
    $text = _("Could not connect to FOSSology database.");
    echo "<h2>$text</h2>";
    debugbacktrace();
    exit;
  }
  return($PG_CONN);
} /* End DBconnect() */


/*****************************************
 GetSingleRec
   Retrieve a single database record

 Params:
   $Table
   $Where   SQL where clause 
            e.g. "where uploadtree_pk=2"

 Returns:
   Associative array for this record.  
   May be empty if no record found.
 *****************************************/
function GetSingleRec($Table, $Where="")
{
  global $PG_CONN;

  $sql = "SELECT * from $Table $Where limit 1";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);

  $row = pg_fetch_assoc($result);
  pg_free_result($result);
  return $row;
}

?>
