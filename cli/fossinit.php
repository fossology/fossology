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

/* Load all code */
require_once("pathinclude.h.php");
global $WEBDIR;
$UI_CLI = 1; /* this is a command-line program */
require_once("$WEBDIR/common/common.php");
cli_Init();

global $Plugins;

$usage = "Usage: " . basename($argv[0]) . " [options]
  -v  = enable verbose mode (lists each module being processed)
  -d  = enable debug database (lists every DB error)
  -D  = enable debug database (lists EVERY DB call)
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

global $DB;
if (array_key_exists('d',$Options))
  {
  $DB->Debug=1;
  }
if (array_key_exists('D',$Options))
  {
  $DB->Debug=2;
  }


/* Initialize the list of registered plugins */
$FailFlag=0;
for($p=0; !empty($Plugins[$p]); $p++)
  {
  $P = &$Plugins[$p];
  if ($Verbose) { print "Initialize: " . $P->Name . "\n"; }
  if ($P->Install() != 0)
    {
    $FailFlag = 1;
    print "FAILED: " . $P->Name . " failed to install.\n";
    }
  }

/* Remove the "Need to initialize" flag */
if (!$FailFlag)
  {
  $Filename = "$WEBDIR/init.ui";
  $State = 1;
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
  }
else
  {
  print "Initialization had errors.\n";
  }
?>
