#!/usr/bin/php -q
<?php
/*
 SPDX-FileCopyrightText: © 2013-2014 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @file list_license.php
 * @brief list license in fossology
 *
 * @return 0 for success, 1 for failure.
 **/

$PREFIX = "/usr/local/";
require_once("$PREFIX/share/fossology/lib/php/common.php");
$sysconfig = "$PREFIX/etc/fossology/";

$AllPossibleOpts = "nrh";
$reference_flag = 0; // 1: include; 0: exclude
$nomos_flag = 0; // 1: include; 0: exclude

/* command-line options */
$Options = getopt($AllPossibleOpts);
foreach($Options as $Option => $OptVal)
{
  switch($Option)
  {
    case 'n': /* list license from nomos */
      $nomos_flag = 1;
      break;
    case 'r': /* list license from reference */
      $reference_flag = 1;
      break;
    case 'h': /* help */
      Usage();
    default:
      echo "Invalid Option \"$Option\".\n";
      Usage();
  }
}

/** for no any flag scenario, meanning, list all licenses */
if (0 == $reference_flag && 0 == $nomos_flag)
{
  $reference_flag = 1;
  $nomos_flag = 1; 
}

$PG_CONN = DBconnect($sysconfig);
list_license($reference_flag, $nomos_flag);

function list_license($reference_flag, $nomos_flag)
{
  global $PG_CONN;
  $sql_statment = "SELECT rf_shortname from license_ref ";
  if ($reference_flag && $nomos_flag) ;
  else if ($reference_flag) $sql_statment .= " where rf_detector_type = 1";
  else if ($nomos_flag) $sql_statment .= " where rf_detector_type = 2";
  $sql_statment .= " order by rf_shortname";
  $result = pg_query($PG_CONN, $sql_statment);
  DBCheckResult($result, $sql_statment, __FILE__, __LINE__);
  while ($row = pg_fetch_assoc($result))
  {
    print $row['rf_shortname']."\n";
  }
  pg_free_result($result);
}

/** \brief Print Usage statement.
 *  \return No return, this calls exit.
 **/
function Usage()
{
  global $argv;

  $usage = "Usage: " . basename($argv[0]) . " [options]
  List licenses fossology support.  Options are:
  -n  licenses are just from nomos 
  -r  licenses are just from reference
  -h  this help usage
  default will list all licenses fossology support";
  print "$usage\n";
  exit(0);
}
