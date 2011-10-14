<?php
/***********************************************************
 Copyright (C) 2008-2011 Hewlett-Packard Development Company, L.P.

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
 * \file index.php
 * \ brief This is the main guts of the UI: Find the plugin and run it.
 */

//require("i18n.php"); DISABLED until i18n infrastructure is set-up.
require_once("../../lib/php/common.php");
require_once("template/template-plugin.php");

$SysConf = array();  // fo system configuration variables
$PG_CONN = 0;   // Database connection

/* Get SYSCONFDIR and set global (for backward compatibility) */
$SYSCONFDIR = GetSYSCONFDIR();

/**
 * Connect to the database.  If the connection fails,
 * DBconnect() will print a failure message and exit.
 */
$PG_CONN = DBconnect($SYSCONFDIR);

/* Initialize global system configuration variables $SysConfig[] */
$SysConf = ConfigInit($SYSCONFDIR);
//debugprint($SysConf, "SysConf");

plugin_load();

$Mod = GetParm("mod",PARM_STRING);
if (!isset($Mod)) { $Mod = "Default"; }
$PluginId = plugin_find_id($Mod);
if ($PluginId >= 0)
{
  /* Found a plugin, so call it! */
  $Plugins[$PluginId]->OutputOpen("HTML",1);
  // error_reporting(E_ALL | E_NOTICE);
  $Plugins[$PluginId]->Output();
  $Plugins[$PluginId]->OutputClose();
}
else
{
  $Uri = Traceback_uri() . "?mod=auth";
  $text = _("Module unavailable or your login session timed out.");
  print "$text <P />";
  $text01= _("Click here to continue.");
  print "<a href='$Uri'>" . $text01 . "</a>";

  print "<script language='JavaScript'>\n";
  print "function Redirect()\n";
  print "{\n";
  print "window.location.href = '$Uri';\n";
  print "}\n";
  /* Redirect in 5 seconds. */
//  print "window.setTimeout('Redirect()',5000);\n";
  print "</script>\n";
}
plugin_unload();
return(0);
?>
