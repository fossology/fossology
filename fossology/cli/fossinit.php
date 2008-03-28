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
require_once("$WEBDIR/common/common.php");
require_once("$WEBDIR/template/template-plugin.php");

/* Load the plugins */
global $Plugins;
plugin_load("$WEBDIR/plugins",0); /* load but do not initialize */

/* Turn off authentication */
/** The auth module hijacks and disables plugins, so turn it off. **/
$P = &$Plugins[plugin_find_any_id("auth")];
if (!empty($P)) { $P->State = PLUGIN_STATE_FAIL; }

/* Initialize plugins */
/** This registers plugins with the menu structure and start the DB
    connection. **/
plugin_init(); /* this registers plugins with menus */

/* Load command-line options */
/*** -v  = verbose ***/
$Options = getopt("v");
$Verbose = array_key_exists("v",$Options);

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
  }
else
  {
  print "Initialization had errors.\n";
  }
?>
