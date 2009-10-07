<?php
/***********************************************************
 Copyright (C) 2009 Hewlett-Packard Development Company, L.P.

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

/*************************************************
 Restrict usage: Every PHP file should have this
 at the very beginning.
 This prevents hacking attempts.
 *************************************************/
global $GlobalReady;
if (!isset($GlobalReady)) { exit; }

/*****************************************
 DB2KeyValArray: 
   Create an associative array by using table
   rows to source the key/value pairs.

 Params:
   $Table   tablename
   $KeyCol  Key column name in $Table
   $ValCol  Value column name in $Table
   $Where   SQL where clause (optional)
            This can really be any clause following the
            table name in the sql

 Returns:
   Array[Key] = Val for each row in the table
   May be empty if no table rows or Where results
   in no rows.
 *****************************************/
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


/*****************************************
 Array2SingleSelect: Build a single choice select pulldown

 Params:
   $KeyValArray   Assoc array.  Use key/val pairs for list
   $SLName        Select list name (default is "unnamed")
   $SelectedVal   Initially selected value (optional)
   $FirstEmpty    True if the list starts off with an empty choice
                  (default is false)
 *****************************************/
function Array2SingleSelect($KeyValArray, $SLName="unnamed", $SelectedVal= "", 
                            $FirstEmpty=false)
{
  $str ="\n<select name='$SLName'>\n";
  if ($FirstEmpty) $str .= "<option value='' > \n";
  foreach ($KeyValArray as $key => $val)
  {
    $SELECTED = ($val == $SelectedVal) ? "SELECTED" : "";
    $str .= "<option value='$key' $SELECTED>$val\n";
  }
  $str .= "</select>";
  return $str;
}


/*****************************************
 DBCheckResult: 
   Check the postgres result for unexpected errors.
   If found, treat them as fatal.

 Params:
   $result  command result object
   $sql     SQL command (optional)
   $filenm  File name (__FILE__)
   $lineno  Line number of the caller (__LINE__)

 Returns:
   None, prints error, sql and line number, then exits(1)
 *****************************************/
function DBCheckResult($result, $sql="", $filenm, $lineno)
{
  global $PG_CONN;

  if (!$result)
  {
    echo "<hr>File: $filenm, Line number: $lineno<br>";
    echo pg_last_error($PG_CONN);
    echo "<br> $sql <hr>";
    exit(1);
  }
}


function debugprint($val, $title)
{
  echo $title, "<pre>";
  print_r($val);
  echo "</pre>";
}

function HumanSize( $bytes )
{
    $types = array( 'B', 'KB', 'MB', 'GB', 'TB' );
    for( $i = 0; $bytes >= 1024 && $i < ( count( $types ) -1 ); $bytes /= 1024, $i++ );
    return( round( $bytes, 2 ) . " " . $types[$i] );
}

