<?php
/*
 SPDX-FileCopyrightText: © 2008-2012 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @file schema-export.php
 * @brief Export a schema in the format used by GetSchema().
 *        This is typically used to export from fossology-gold
 *        but can be used on any db.
 *
 * This is not a stand alone file.  It must be used with fo_wrapper.php
 * ln -s fo_wrapper.php schema-export
 *
 * @return 0 for success, 1 for failure.
 **/

/* Note: php 5 getopt() ignores options not specified in the function call, so add
 * dummy options in order to catch invalid options.
 */
$AllPossibleOpts = "abc:d:ef:ghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";

/* defaults */
$Verbose = false;
$DatabaseName = "fossology";
$UpdateLiceneseRef = false;
$showUsage = false;

/* default location of core-schema.dat.  This file is checked into svn */
$SchemaFilePath = "$MODDIR/www/ui/core-schema.dat";

/* command-line options */
$Options = getopt($AllPossibleOpts);
foreach ($Options as $Option => $OptVal) {
  switch ($Option) {
    case 'c': /* used by fo_wrapper */
      break;
    case 'd': /* optional database name */
      $DatabaseName = $OptVal;
      break;
    case 'f': /* schema file */
      $SchemaFilePath = $OptVal;
      break;
    case 'h': /* help */
      $showUsage = true;
      break;
    default:
      echo "Invalid Option \"$Option\".\n";
      $showUsage = true;
  }
}

if ($showUsage) {
  global $argv;

  $usage = "Usage: " . basename($argv[0]) . " [options]
  Update FOSSology database.  Options are:
  -d  {database name} default is 'fossology'
  -f  {output file}
  -h  this help usage";
  print "$usage\n";
} else {
  if (file_exists($SchemaFilePath) && !@unlink($SchemaFilePath)) {
    $FailMsg = "Existing schema data file ($SchemaFilePath) could not be removed.";
  } else {
    $FailMsg = ExportSchema($SchemaFilePath);
  }

  if ($FailMsg !== false) {
    print "ERROR: $FailMsg \n";
    exit(1);
  }
}
