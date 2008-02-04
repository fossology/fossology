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

/***************************************************************
 $GlobalReady is only set here.
 This flag tells all other PHP code that it is running from this system.
 If other PHP code does not see this flag, then the code is not being
 executed correctly and should exit immediately (without taking further
 action).
 ***************************************************************/
$GlobalReady=1;

include_once("common/common.php");
include_once("common/common-parm.php");
include_once("common/common-folders.php");
include_once("common/common-dir.php");
include_once("template/template-plugin.php");
include_once("pathinclude.h.php");

/****************************************************
 This is the main guts of the UI: Find the plugin and run it.
 ****************************************************/
plugin_load("plugins");

$Mod = GetParm("mod",PARM_STRING);
if (!isset($Mod)) { $Mod = "Default"; }
$PluginId = plugin_find_id($Mod);
if ($PluginId >= 0)
  {
  /* Found a plugin, so call it! */
  $Plugins[$PluginId]->OutputOpen("HTML",1);
  $Plugins[$PluginId]->Output();
  $Plugins[$PluginId]->OutputClose();
  }

/*** DEBUG ***/
if (0)
  {
  print "<P>===Plugins===\n";
  foreach ($Plugins as $key => $val) { print "$key : $val->Name (state=$val->State)<br>\n"; }

  print "===Menu===\n";
  menu_print($MenuList,0);
  }

plugin_unload();

/********************************** TESTING **************************/
return(0);
?>
