#!/usr/bin/php
<?php
/*
 SPDX-FileCopyrightText: © 2011 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

/** @file This file is a quick hack to read a list of filepaths and tag those
 *  files.  As is it isn't ready for prime time but needs to solve an immediate
 *  problem.  I'll improve this at a future date but wanted to check it in
 *  because others my find it useful (with minor modificaitons).
@todo remove:
  -  GlobalReady
  -  Hardcoded path to bobg's pathinclude.php
  _  UI_CLI
  _  $WEBDIR
  -  cli_Init();
  -  $DB;
 */

$GlobalReady = 1;
/* Load all code */
require_once "/usr/share/fossology/php/pathinclude.php";
global $WEBDIR;
$UI_CLI = 1; /* this is a command-line program */
require_once ("$WEBDIR/common/common.php");

function Usage($argc, $argv)
{
  echo "$argv[0] -m -f {file of pathnames}  -u {original upload_pk} -t {tag_pk}\n";
  echo "         -m means to only print out missing files.  Do not update the db.\n";
}

/**
 * @brief Take raw line with path from diff output and return just the path.
 * The diff output lines (grepped for +++) follow this format:\n
 *+++ 1065/LINUX/android//bootable/bootloader/lk/app/aboot/aboot.c    2011-03-08 17:03:19.432717000 +0800
 * @returns File path
 */
function Strip2Path($RawFilePath)
{
  if (empty($RawFilePath)) {
    return "";
  }

  /* strip to first slash */
  $FirstSlashPos = strpos($RawFilePath, "/");
  $StartStr = substr($RawFilePath, $FirstSlashPos);

  $FParts = explode(" ", $StartStr, 3);

  /* strip trailing text after path */
  $fPath = strtok($FParts[0], " \t");
  return $fPath;
}

/**
 * @brief Given a path, find the uploadtree record for that file.
 *
 * @param $upload_pk
 * @param $FilePath
 *
 * @return array|false
 *         if the $FilePath was found, return the uploadtree row,
 *          or false if the path wasn't found.
 **/
function Path2Uploadtree($upload_pk, $FilePath)
{
  global $PG_CONN;

  $FileName = basename($FilePath);

  /* create the cleaned up file path string
   * note delimiters are not put in the string since they are not needed for
   * the string compare.
   */
  $FilePathArray = explode('/', $FilePath);
  $FilePathStr = "";
  foreach ($FilePathArray as $name) {
    if (empty($name)) {
      continue;
    }
    $FilePathStr .= $name;
  }

  $sql = "SELECT * from uploadtree where upload_fk='$upload_pk' and ufile_name='$FileName'";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  if (pg_num_rows($result) == 0) {
    return false;
  }

  /* Get the uploadtree recs for this file name */
  while ($row = pg_fetch_assoc($result)) {
    $sql = "select ufile_name from uploadtree2path('$row[uploadtree_pk]')";
    $PathResult = pg_query($PG_CONN, $sql);
    DBCheckResult($PathResult, $sql, __FILE__, __LINE__);

    /* Check each uploadtree rec to see if the path matches */
    $SelectedPathStr = "";
    while ($PathRow = pg_fetch_assoc($PathResult)) {
      $SelectedPathStr = $PathRow['ufile_name'] . $SelectedPathStr;
    }
    pg_free_result($PathResult);

    /* do the paths match? */
    if ($FilePathStr == $SelectedPathStr) {
      pg_free_result($result);
      return $row;
    }
  }
  pg_free_result($result);
  return false;
}


/**
 * @brief Tag an item
 * This updates tag_file and/or tag_uploadtree.
 **/
function TagPath($UploadtreeRow, $tag_pk)
{
  global $PG_CONN;

  if (empty($UploadtreeRow['pfile_fk'])) {
    /* this is not a pfile, update table tag_uploadtree */
    /* There is no constraint preventing duplicate tags so do a precheck */
    $sql = "SELECT * from tag_uploadtree where uploadtree_fk='$UploadtreeRow[uploadtree_pk]' and tag_fk='$tag_pk'";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    if (pg_num_rows($result) == 0) {
      $sql = "insert into tag_uploadtree (tag_fk, uploadtree_fk, tag_uploadtree_date, tag_uploadtree_text) values ($tag_pk, '$UploadtreeRow[uploadtree_pk]', now(), NULL)";
      $InsResult = pg_query($PG_CONN, $sql);
      DBCheckResult($InsResult, $sql, __FILE__, __LINE__);
    }
  } else {
    /* this is a pfile, update table tag_file */
    /* There is no constraint preventing duplicate tags so do a precheck */
    $sql = "SELECT * from tag_file where pfile_fk='$UploadtreeRow[pfile_fk]' and tag_fk='$tag_pk'";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    if (pg_num_rows($result) == 0) {
      $sql = "insert into tag_file (tag_fk, pfile_fk, tag_file_date, tag_file_text) values ($tag_pk, '$UploadtreeRow[pfile_fk]', now(), NULL)";
      $InsResult = pg_query($PG_CONN, $sql);
      DBCheckResult($InsResult, $sql, __FILE__, __LINE__);
    }
  }
  pg_free_result($result);
  return;
}


/* note cli_Init(), and db_init() should go away in 2.0
 * but keep it here for now to ease backporting to 1.4
 */
cli_Init();
global $Plugins;
error_reporting(E_NOTICE & E_STRICT);

global $DB;
global $PG_CONN;
$dbok = $DB->db_init();
if (! $dbok) {
  echo "FATAL: NO DB connection";
  exit -1;
}

/*  -f {file of pathnames}  -u {original upload_pk} -t {tag_pk} */
$Options = getopt("mf:t:u:");
if (array_key_exists('f', $Options)
     && array_key_exists('t', $Options)
     && array_key_exists('u', $Options)
   ) {
  $Missing = array_key_exists('m', $Options) ? true : false;
  $PathFile = $Options['f'];
  $tag_pk = $Options['t'];
  $upload_pk = $Options['u'];
} else {
  echo "Fatal: Missing parameter\n";
  Usage($argc, $argv);
  exit -1;
}

if ($Missing) {
  "Missing: $Missing\n";
}

/* read $PathFile a line at a time */
$fhandle  = @fopen($PathFile, "r");
$FileCount = 0;
$MissCount = 0;
if ($fhandle) {
  while (($fpathRaw = fgets($fhandle, 4096)) !== false) {
    $fpath = Strip2Path($fpathRaw);
    $UploadtreeRow = Path2Uploadtree($upload_pk, $fpath);
    if ($UploadtreeRow === false) {
      echo "Missing $fpath\n";
      $MissCount ++;
    } else {
      if ($Missing === false) {
        TagPath($UploadtreeRow, $tag_pk);
      }
      $FileCount ++;
    }
  }
  if (! feof($fhandle)) {
    echo "Error: unexpected fgets() fail\n";
    exit(- 1);
  }
  fclose($fhandle);
}

echo "$FileCount files tagged\n";
echo "$MissCount file paths not found\n";

return (0);

