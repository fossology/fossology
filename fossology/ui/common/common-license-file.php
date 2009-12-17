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

/************************************************************
  This file contains common functions for the
  license_file and license_ref tables.
 ************************************************************/


/*
 * Return all the licenses for a single file or uploadtree
 * Inputs:
 *   $agent_pk
 *   $pfile_pk       (if empty, $uploadtree_pk must be given)
 *   $uploadtree_pk  (used only if $pfile_pk is empty)
 * Returns:
 *   sql result for rf_shortname, rf_fk
 *   FATAl if neither pfile_pk or uploadtree_pk were given
 */
function GetFileLicenses($agent_pk, $pfile_pk, $uploadtree_pk)
{
  global $PG_CONN;

  if (empty($agent_pk)) Fatal("Missing parameter: agent_pk", __FILE__, __LINE__);

  // if $pfile_pk, then return the licenses for that one file
  if ($pfile_pk)
  {
    $sql = "SELECT rf_shortname, rf_fk
              from license_ref,license_file,
              where pfile_fk='$pfile_pk' and agent_fk=$agent_pk and rf_fk=rf_pk
              order by rf_shortname desc";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
  }
  else if ($uploadtree_pk)
  {
    /* Find lft and rgt bounds for this $uploadtree_pk  */
    $sql = "SELECT lft, rgt, upload_fk FROM uploadtree 
                   WHERE uploadtree_pk = $uploadtree_pk";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $row = pg_fetch_assoc($result);
    $lft = $row["lft"];
    $rgt = $row["rgt"];
    $upload_pk = $row["upload_fk"];
    pg_free_result($result);

    /*  Get the counts for each license under this UploadtreePk*/
    $sql = "SELECT distinct(rf_shortname) as rf_shortname, rf_fk
              from license_ref,license_file,
                  (SELECT distinct(pfile_fk) as PF from uploadtree 
                     where upload_fk=$upload_pk 
                       and uploadtree.lft BETWEEN $lft and $rgt) as SS
              where PF=pfile_fk and agent_fk=$agent_pk and rf_fk=rf_pk
              order by rf_shortname desc";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
  }
  else Fatal("Missing function inputs", __FILE__, __LINE__);
  return $result;
}

// Same as GetFileLicenses() but returns license list as a single string
function GetFileLicenses_string($agent_pk, $pfile_pk, $uploadtree_pk)
{
  $LicStr = "";
  $LicenseResult = GetFileLicenses($agent_pk, $pfile_pk, $uploadtree_pk);
  while ($row = pg_fetch_assoc($LicenseResult))
  {
    $LicStr .= $row['rf_shortname']. ", ";
  }
  pg_free_result($LicenseResult);
  
  // remove trailing ", "
  $LicStr[strlen($LicStr)-2] = ' ';
  return $LicStr;
}

/*
 * Return files with a given license (shortname).
 * Inputs:
 *   $agent_pk
 *   $rf_shortname
 *   $uploadtree_pk   sets scope of request
 *   $PkgsOnly        if true, only list packages, default is false (all files are listed)
 *                    $PkgsOnly is not yet implemented.
 *   $offset          select offset, default is 0
 *   $limit           select limit (num rows returned), default is no limit
 *   $order           sql order by clause, default is blank
 *                      e.g. "order by ufile_name asc"
 * Returns:
 *   pg_query result.  See $sql for fields returned.
 *   Caller should use pg_free_result to free.
 */
function GetFilesWithLicense($agent_pk, $rf_shortname, $uploadtree_pk, 
                             $PkgsOnly=false, $offset=0, $limit="ALL",
                             $order="")
{
  global $PG_CONN;
  
  /* Find lft and rgt bounds for this $uploadtree_pk  */
  $sql = "SELECT lft, rgt, upload_fk FROM uploadtree 
                 WHERE uploadtree_pk = $uploadtree_pk";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  $row = pg_fetch_assoc($result);
  $lft = $row["lft"];
  $rgt = $row["rgt"];
  $upload_pk = $row["upload_fk"];
  pg_free_result($result);

  $shortname = pg_escape_string($rf_shortname);

  $sql = "select uploadtree_pk, pfile_fk, ufile_name
          from license_ref,license_file,
              (SELECT pfile_fk as PF, uploadtree_pk, ufile_name from uploadtree 
                 where upload_fk=$upload_pk
                   and uploadtree.lft BETWEEN $lft and $rgt) as SS
          where PF=pfile_fk and agent_fk=$agent_pk and rf_fk=rf_pk
                and rf_shortname='$shortname'
          $order limit $limit offset $offset";
  $result = pg_query($PG_CONN, $sql);  // Top uploadtree_pk's
  DBCheckResult($result, $sql, __FILE__, __LINE__);

//echo "<br>$sql<br>";
  return $result;
}

/*
 * Count files with a given license (shortname).
 * Inputs:
 *   $agent_pk
 *   $rf_shortname
 *   $uploadtree_pk   sets scope of request
 *   $PkgsOnly        if true, only list packages, default is false (all files are listed)
 *                    $PkgsOnly is not yet implemented.  Default is false.
 *   $CheckOnly       if true, sets LIMIT 1 to check if uploadtree_pk has 
 *                    any of the given license.  Default is false.
 * Returns:
 *   number of files with this shortname.
 */
function CountFilesWithLicense($agent_pk, $rf_shortname, $uploadtree_pk, 
                             $PkgsOnly=false, $CheckOnly=false)
{
  global $PG_CONN;
  
  /* Find lft and rgt bounds for this $uploadtree_pk  */
  $sql = "SELECT lft, rgt, upload_fk FROM uploadtree 
                 WHERE uploadtree_pk = $uploadtree_pk";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  $row = pg_fetch_assoc($result);
  $lft = $row["lft"];
  $rgt = $row["rgt"];
  $upload_pk = $row["upload_fk"];
  pg_free_result($result);

  $shortname = pg_escape_string($rf_shortname);
  $chkonly = ($CheckOnly) ? " LIMIT 1" : "";

  $sql = "select count(*)
          from license_ref,license_file,
              (SELECT pfile_fk as PF, uploadtree_pk, ufile_name from uploadtree 
                 where upload_fk=$upload_pk
                   and uploadtree.lft BETWEEN $lft and $rgt) as SS
          where PF=pfile_fk and agent_fk=$agent_pk and rf_fk=rf_pk
                and rf_shortname='$shortname' $chkonly";

  $result = pg_query($PG_CONN, $sql);  // Top uploadtree_pk's
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  
  $LicCount = pg_fetch_result($result, 0, 0);
  pg_free_result($result);
  return $LicCount;
}


/*
 * Return which uploadtree_pk's in the top level of $uploadtree_pk
 * have license $rf_shortname.
 *
 * Inputs:
 *   $agent_pk
 *   $rf_shortname
 *   $uploadtree_pk   sets scope of request
 *   $PkgsOnly        if true, only list packages, default is false (all files are listed)
 *                    $PkgsOnly is not yet implemented.
 * Returns:
 *   Array of uploadtree_pk ==> ufile_name
 */
function Level1WithLicense($agent_pk, $rf_shortname, $uploadtree_pk, $PkgsOnly=false)
{
  global $PG_CONN;
  $pkarray = array();

  $sql = "select uploadtree_pk, ufile_name from uploadtree where parent=$uploadtree_pk";
  $TopUTpks = pg_query($PG_CONN, $sql);  // Top uploadtree_pk's
  DBCheckResult($TopUTpks, $sql, __FILE__, __LINE__);
  
  /* Loop throught each top level uploadtree_pk */
  $offset = 0;
  $limit = 1;
  $order = "";
  while ($row = pg_fetch_assoc($TopUTpks))
  {
//$uTime2 = microtime(true);
    $result = GetFilesWithLicense($agent_pk, $rf_shortname, $row['uploadtree_pk'], 
                             $PkgsOnly, $offset, $limit, $order);
//$Time = microtime(true) - $uTime2;
//printf( "GetFilesWithLicense($row[ufile_name]) time: %.2f seconds<br>", $Time);

    if (pg_num_rows($result) > 0) $pkarray[$row['uploadtree_pk']] = $row['ufile_name'];
    pg_free_result($result);
  }
  return $pkarray;
}

?>
