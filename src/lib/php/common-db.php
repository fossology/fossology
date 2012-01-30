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

/**
 *  \file common-db.php
 *  \brief This file contains common database functions.
 **/


/**
 * \brief Connect to database engine.
 *        This is a no-op if $PG_CONN already has a value.
 *
 * \param $sysconfdir fossology configuration directory (location of Db.conf)
 * \param $Options an optional list of attributes for
 *             connecting to the database. E.g.:
 *   "dbname=text host=text user=text password=text"
 * \param $FailExit true (default) to print error, backtrace and call exit on failure
 *                  false to return $PG_CONN === false on failure
 *
 * If $Options is null, then connection parameters 
 * will be read from Db.conf.
 *
 * \return 
 *   Success: $PG_CONN, the postgres connection object
 *   Failure: Error message is printed and exit
 **/
function DBconnect($sysconfdir, $Options="", $FailExit=true)
{
  global $PG_CONN;

  if (!empty($PG_CONN)) return $PG_CONN;

  $path="$sysconfdir/Db.conf";
  if (empty($Options))
    $PG_CONN = pg_pconnect(str_replace(";", " ", file_get_contents($path)));
  else
    $PG_CONN = pg_pconnect(str_replace(";", " ", $Options));

  if (!empty($PG_CONN)) return($PG_CONN); /* success */

  if ($FailExit)
  {
    $text = _("Could not connect to FOSSology database.");
    echo "<h2>$text</h2>";
    debugbacktrace();
    exit;
  }
  else
  {
    $PG_CONN = false;
    return;
  }
} /* End DBconnect() */


/**
   \brief Retrieve a single database record.

          This function does a:
            "SELECT * from $Table $Where limit 1"
          and returns the result as an associative array.

   \param $Table   Table name
   \param $Where   SQL where clause e.g. "where uploadtree_pk=2".
                   Though a WHERE clause is the typical use, $Where
                   can really be any options following the sql tablename.
   \return 
       Associative array for this record.  
       May be empty if no record found.
 **/
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


/**
 * \brief Create an associative array by using table
 *        rows to source the key/value pairs.
 *
 * \param $Table   tablename
 * \param $KeyCol  Key column name in $Table
 * \param $ValCol  Value column name in $Table
 * \param $Where   SQL where clause (optional)
 *                 This can really be any clause following the
 *                 table name in the sql
 *
 * \return
 *  Array[Key] = Val for each row in the table
 *  May be empty if no table rows or Where results
 *  in no rows.
 **/
function DB2KeyValArray($Table, $KeyCol, $ValCol, $Where="")
{
  global $PG_CONN;

  $ResArray = array();

  $sql = "SELECT $KeyCol, $ValCol from $Table $Where";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);

  while ($row = pg_fetch_assoc($result))
  {
    $ResArray[$row[$KeyCol]] = $row[$ValCol];
  }
  return $ResArray;
}


/**
 * \brief Check the postgres result for unexpected errors.
 *  If found, treat them as fatal.
 *
 * \param $result  command result object
 * \param $sql     SQL command (optional)
 * \param $filenm  File name (__FILE__)
 * \param $lineno  Line number of the caller (__LINE__)
 *
 * \return None, prints error, sql and line number, then exits(1)
 **/
function DBCheckResult($result, $sql="", $filenm, $lineno)
{
  global $PG_CONN;

  if (!$result)
  {
    echo "<hr>File: $filenm, Line number: $lineno<br>";
    if (pg_connection_status($PG_CONN) === PGSQL_CONNECTION_OK)
      echo pg_last_error($PG_CONN);
    else
      echo "FATAL: DB connection lost.";
    echo "<br> $sql";
    debugbacktrace();
    echo "<hr>";
    exit(1);
  }
}


/**
 * \brief Check if table exists.
 *
 * \param $tableName
 *
 * \return 1 if table exists, 0 if not.
**/
function DB_TableExists($tableName)
{
  global $PG_CONN;
  global $SysConf;

  $sql = "select count(*) as count from information_schema.tables where table_catalog='{$SysConf['DBCONF']['dbname']}' and table_name='$tableName'";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  $row = pg_fetch_assoc($result);
  $count = $row['count'];
  pg_free_result($result);
  return($count);
} /* DB_TableExists()  */


/**
 * \brief Check if a column exists.
 *        Note, this assumes the database name is 'fossology'.
 *
 * \param $tableName
 * \param $colName
 *
 * \return 1 if column exists, 0 if not.
**/
function DB_ColExists($tableName, $colName)
{
  global $PG_CONN;

  $sql = "select count(*) as count from information_schema.columns where table_catalog='fossology' and table_name='$tableName' and column_name='$colName'";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  $row = pg_fetch_assoc($result);
  $count = $row['count'];
  pg_free_result($result);
  return($count);
} /* DB_ColExists()  */

?>
