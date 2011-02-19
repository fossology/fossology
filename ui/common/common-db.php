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

/*************************************************
 Restrict usage: Every PHP file should have this
 at the very beginning.
 This prevents hacking attempts.
 *************************************************/
global $GlobalReady;
if (!isset($GlobalReady)) { exit; }


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
