#!/usr/bin/php
<?php
/***********************************************************
 Copyright (C) 2008 Hewlett-Packard Development Company, L.P.

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

/**************************************************************
 fossinit

 Initialize the FOSSology system (no UI required).
 This should be used immediately after an install, and before
 starting up the scheduler.

 @return 0 for success, 1 for failure.
 *************************************************************/

/* Have to set this or else plugins will not load. */
$GlobalReady = 1;

/* Must run as group fossy! */
$GID = posix_getgrnam("fossy");
posix_setgid($GID['gid']);
$Group = `groups`;
if (!preg_match("/\sfossy\s/",$Group) && (posix_getgid() != $GID['gid']))
{
  print "ERROR: Unable to run as group 'fossy'\n";
  exit(1);
}

/* Load all code */
require_once(dirname(__FILE__) . '/../share/fossology/php/pathinclude.php');

$UI_CLI = 1; /* this is a command-line program */

global $WEBDIR;
require_once("$WEBDIR/common/common.php");
cli_Init();

global $Plugins;
global $LIBEXECDIR;
require_once("$LIBEXECDIR/libschema.php");

global $PGCONN;

$usage = "Usage: " . basename($argv[0]) . " [options]
  -v  = enable verbose mode (lists each module being processed)
  -h  = this help usage";

/* Load command-line options */
/*** -v  = verbose ***/
$Options = getopt('vh');
if (array_key_exists('h',$Options))
{
  print "$usage\n";
  exit(0);
}
$Verbose = array_key_exists("v",$Options);
if ($Verbose == "") { $Verbose=0; }

/* Initialize the system! */
$Schema = &$Plugins[plugin_find_any_id("schema")];
if (empty($Schema))
{
  print "FAILED: Unable to find the schema plugin.\n";
  exit(1);
}

global $WEBDIR;
$Filename = "$WEBDIR/plugins/core-schema.dat";
if (!file_exists($Filename))
{
  print "FAILED: Schema data file ($Filename) not found.\n";
  exit(1);
}

$PGCONN = dbConnect(NULL);
$FailFlag = ApplySchema($Filename,0,$Verbose);

/* Remove the "Need to initialize" flag */
global $PG_CONN;

if (!$FailFlag)
{
  $Connect = 1;
  $State = 1;
  $PG_CONN = DBconnect($SYSCONFDIR);
  if(!is_resource($PG_CONN))
  {
    $Connect = 0;
  }
  $Filename = "$WEBDIR/init.ui";
  if (file_exists($Filename))
  {
    if ($Verbose) { print "Removing flag '$Filename'\n"; }
    if (is_writable($WEBDIR)) { $State = unlink($Filename); }
    else { $State = 0; }
  }
  if (!$State)
  {
    print "Failed to remove $Filename\n";
    print "Remove this file to complete the initialization.\n";
  }
  else
  {
    print "Initialization completed successfully.\n";
  }
  if(!$Connect)
  {
    echo "FATAL! could not connect to the DataBase\n";
    exit(2);
  }
}
else
{
  print "Initialization had errors.\n";
  exit(1);
}
exit(0);
?>
