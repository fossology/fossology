<?php
/*
 SPDX-FileCopyrightText: © 2011-2012 Hewlett-Packard Development Company, L.P.
 SPDX-FileCopyrightText: © 2017 Siemens AG

 SPDX-License-Identifier: LGPL-2.1-only
*/

/**
 *  \file
 *  \brief This file contains common database functions.
 **/


/**
 * \brief Connect to database engine.
 *        This is a no-op if $PG_CONN already has a value.
 *
 * \param string $sysconfdir fossology configuration directory (location of
 *             Db.conf)
 * \param string $options an optional list of attributes for
 *             connecting to the database. E.g.:
 *   `"dbname=text host=text user=text password=text"`
 * \param bool   $exitOnFail true (default) to print error and call exit on
 *                  failure false to return $PG_CONN === false on failure
 *
 * If $options is empty, then connection parameters will be read from Db.conf.
 *
 * \return
 *   Success: $PG_CONN, the postgres connection object \n
 *   Failure: Error message is printed
 **/
function DBconnect($sysconfdir, $options="", $exitOnFail=true)
{
  global $PG_CONN;

  if (! empty($PG_CONN)) {
    return $PG_CONN;
  }

  $path="$sysconfdir/Db.conf";
  if (empty($options)) {
    $dbConf = file_get_contents($path);
    if ($exitOnFail && (false === $dbConf)) {
      $text = _("Could not connect to FOSSology database.");
      echo "<h2>$text</h2>";
      echo _('permission denied for configuration file');
      exit();
    }
    if (false === $dbConf) {
      $PG_CONN = false;
      return;
    }
    $options = $dbConf;
  }
  if (! empty($options)) {
    $PG_CONN = pg_connect(str_replace(";", " ", $options));
  }

  if (! empty($PG_CONN)) {
    /* success */
    return $PG_CONN;
  }

  if ($exitOnFail) {
    $text = _("Could not connect to FOSSology database.");
    echo "<h2>$text</h2>";
    exit();
  }
  $PG_CONN = false;
}


/**
   \brief Retrieve a single database record.

   This function does a:
   \code
   "SELECT * from $Table $Where limit 1"
   \endcode
   and returns the result as an associative array.

   \param string $Table   Table name
   \param string $Where   SQL where clause e.g. `"where uploadtree_pk=2"`.
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
 * \param string $Table   tablename
 * \param string $KeyCol  Key column name in $Table
 * \param string $ValCol  Value column name in $Table
 * \param string $Where   SQL where clause (optional)
 *                 This can really be any clause following the
 *                 table name in the sql
 *
 * \return
 *  Array[Key] = Val for each row in the table.
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

  while ($row = pg_fetch_assoc($result)) {
    $ResArray[$row[$KeyCol]] = $row[$ValCol];
  }
  return $ResArray;
}

/**
 * \brief Create an array by using table
 *        rows to source the values.
 *
 * \param string $Table   tablename
 * \param string $ValCol  Value column name in $Table
 * \param string $Uniq    Sort out duplicates
 * \param string $Where   SQL where clause (optional)
 *                 This can really be any clause following the
 *                 table name in the sql
 *
 * \return
 *  Array[Key] = Val for each row in the table.
 *  May be empty if no table rows or Where results
 *  in no rows.
 **/
function DB2ValArray($Table, $ValCol, $Uniq=false, $Where="")
{
  global $PG_CONN;

  $ResArray = array();

  if ($Uniq) {
    $sql = "SELECT DISTINCT $ValCol from $Table $Where";
  } else {
    $sql = "SELECT $ValCol from $Table $Where";
  }
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);

  $i = 0;
  while ($row = pg_fetch_assoc($result)) {
    $ResArray[$i] = $row[$ValCol];
    ++ $i;
  }
  return $ResArray;
}


/**
 * \brief Check the postgres result for unexpected errors.
 *  If found, treat them as fatal.
 *
 * \param $result  command result object
 * \param string $sql     SQL command (optional)
 * \param string $filenm  File name (__FILE__)
 * \param int    $lineno  Line number of the caller (__LINE__)
 *
 * \return None, prints error, sql and line number, then exits(1)
 **/
function DBCheckResult($result, $sql, $filenm, $lineno)
{
  global $PG_CONN;

  if (! $result) {
    echo "<hr>File: $filenm, Line number: $lineno<br>";
    if (pg_connection_status($PG_CONN) === PGSQL_CONNECTION_OK) {
      echo pg_last_error($PG_CONN);
    } else {
      echo "FATAL: DB connection lost.";
    }
    echo "<br> ".htmlspecialchars($sql);
    debugbacktrace();
    echo "<hr>";
    exit(1);
  }
}


/**
 * \brief Check if table exists.
 * \note This is postgresql specific.
 *
 * \param string $tableName Table to check
 *
 * \return 1 if table exists, 0 if not.
**/
function DB_TableExists($tableName)
{
  global $PG_CONN;
  global $SysConf;

  $sql = "select count(*) as count from information_schema.tables where "
       . "table_catalog='{$SysConf['DBCONF']['dbname']}' and table_name='$tableName'";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  $row = pg_fetch_assoc($result);
  $count = $row['count'];
  pg_free_result($result);
  return($count);
} /* DB_TableExists()  */


/**
 * \brief Check if a column exists.
 * \note This is postgresql specific.
 *
 * \param string $tableName Table to check in
 * \param string $colName   Column to check
 * \param string $DBName    Database name, default "fossology"
 *
 * \return 1 if column exists, 0 if not.
**/
function DB_ColExists($tableName, $colName, $DBName='fossology')
{
  global $PG_CONN;

  $sql = "select count(*) as count from information_schema.columns where "
       . "table_catalog='$DBName' and table_name='$tableName' and column_name='$colName'";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  $row = pg_fetch_assoc($result);
  $count = $row['count'];
  pg_free_result($result);
  return($count);
} /* DB_ColExists()  */


/**
 * \brief Check if a constraint exists.
 * \note This is postgresql specific.
 *
 * \param string $ConstraintName Constraint to check
 * \param string $DBName         Database name, default "fossology"
 *
 * \return True if constraint exists, False if not.
**/
function DB_ConstraintExists($ConstraintName, $DBName='fossology')
{
  global $PG_CONN;

  $sql = "select count(*) as count from information_schema.table_constraints "
       . "where table_catalog='$DBName' and constraint_name='$ConstraintName' limit 1";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  $row = pg_fetch_assoc($result);
  $count = $row['count'];
  pg_free_result($result);
  if ($count == 1) {
    return true;
  }
  return False;
} /* DB_ColExists()  */


/**
 * \brief Get last sequence number.
 *
 * This is typically used to get the primary key of a newly inserted record.
 * This must be called immediately after the insert.
 *
 * \param string $seqname   Sequence Name of key just added
 * \param string $tablename Table containing $seqname
 *
 * \return Current sequence number (i.e. the primary key of the rec just added)
**/
function GetLastSeq($seqname, $tablename)
{
  global $PG_CONN;

  $sql = "SELECT currval('$seqname') as mykey FROM $tablename";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  $row = pg_fetch_assoc($result);
  $mykey = $row["mykey"];
  pg_free_result($result);
  return($mykey);
}

/**
 * \brief Get constraints on a specific column.
 *
 * \param string $table  Table name
 * \param string $column Column name
 *
 * \return array of constraint names
 */
function DB_ColumnConstraints($table, $column)
{
  global $PG_CONN;
  $sql = "
    SELECT con.conname
    FROM pg_constraint con
    JOIN pg_class rel ON rel.oid = con.conrelid
    JOIN pg_namespace nsp ON nsp.oid = rel.relnamespace
    JOIN unnest(con.conkey) AS colnum(attnum) ON true
    JOIN pg_attribute att
      ON att.attrelid = rel.oid
     AND att.attnum   = colnum.attnum
    WHERE nsp.nspname = 'public'
      AND rel.relname = $1
      AND att.attname = $2
  ";

  $result = pg_query_params($PG_CONN, $sql, [$table, $column]);
  DBCheckResult($result, $sql, __FILE__, __LINE__);

  $constraints = [];
  while ($row = pg_fetch_assoc($result)) {
    $constraints[] = $row['conname'];
  }

  pg_free_result($result);
  return $constraints;
}

