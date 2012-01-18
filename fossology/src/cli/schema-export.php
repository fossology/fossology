<?php
/***********************************************************
 Copyright (C) 2008-2012 Hewlett-Packard Development Company, L.P.

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
$AllPossibleOpts = "abcd:ef:ghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";

/* defaults */
$Verbose = false;
$DatabaseName = "fossology";
$UpdateLiceneseRef = false;
$SchemaFilePath = "$MODDIR/www/ui/core-schema.dat";

/* command-line options */
$Options = getopt($AllPossibleOpts);
foreach($Options as $Option => $OptVal)
{
  switch($Option)
  {
    case 'd': /* optional database name */
      $DatabaseName = $OptVal;
      break;
    case 'f': /* schema file */
      $SchemaFilePath = $OptVal;
      break;
    case 'h': /* help */
      Usage();
    default:
      echo "Invalid Option \"$Option\".\n";
      Usage();
  }
}

if (file_exists($SchemaFilePath))
{
  $success = unlink($SchemaFilePath);
  if (!$success)
  {
    print "ERROR: Existing schema data file ($SchemaFilePath) could not be removed.\n";
    exit(1);
  }
}

$FailMsg = ExportSchema($SchemaFilePath);

exit(0);


/** \brief Print Usage statement.
 *  \return No return, this calls exit.
 **/
function Usage()
{
  global $argv;

  $usage = "Usage: " . basename($argv[0]) . " [options]
  Update FOSSology database.  Options are:
  -d  {database name} default is 'fossology'
  -f  {output file} 
  -h  this help usage";
  print "$usage\n";
  exit(0);
}
?>
