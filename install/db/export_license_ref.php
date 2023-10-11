#!/usr/bin/php -q

<?php
/*
 SPDX-FileCopyrightText: © 2013 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * \file export_license_ref.php
 * \brief export license_ref into licenseref.sql
 *        Added in 2.2.0
 * \exit 0: sucess; 1: failed
 **/

$Usage = "Usage: " . basename($argv[0]) . "
  -h     help, this message
  -f     {output file}
  --help help, this message (Note: the user postgres should have write permission on the output file.)
  ";

$Options = getopt("hf:", array("help"));

/* command-line options */
$SchemaFilePath = "";
foreach($Options as $Option => $OptVal)
{
  switch($Option)
  {
    case 'f': /* schema file */
      $SchemaFilePath = $OptVal;
      break;
    case 'h': /* help */
      print $Usage;
      exit (0);
    case 'help': /* help */
      print $Usage;
      exit (0);
    default:
      echo "Invalid Option \"$Option\".\n";
      print $Usage;
      exit (1);
  }
}

# dump license_ref table into a temp file
if (empty($SchemaFilePath)) $SchemaFilePath = "licenseref.sql";
$dump_command = "sudo su postgres -c 'pg_dump -f $SchemaFilePath -a -t license_ref --column-inserts fossology'";
system($dump_command, $return_var);

if(!$return_var) exit (0);
else exit (1);
