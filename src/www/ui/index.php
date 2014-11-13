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
use Fossology\Lib\Plugin\Plugin;
use Monolog\Logger;

/**
 * \file index.php
 * \ brief This is the main guts of the UI: Find the plugin and run it.
 */

$startTime = microtime(true);
require_once(__DIR__ . "/../../lib/php/bootstrap.php");

$SysConf = array();  // fo system configuration variables
$PG_CONN = 0;   // Database connection

/* Set SYSCONFDIR and set global (for backward compatibility) */
$SysConf = bootstrap();

global $container;
/** @var Logger $logger */
$logger = $container->get("logger");

$endTime = microtime(true);
$logger->debug(sprintf("bootstrap in %.3fs", $endTime - $startTime));
$startTime = $endTime;
/* Initialize global system configuration variables $SysConfig[] */
ConfigInit($SYSCONFDIR, $SysConf);
$endTime = microtime(true);
$logger->debug(sprintf("setup init in %.3fs", $endTime - $startTime));
$startTime = $endTime;

plugin_load();
plugin_preinstall();

// call install method of every plugin, core-auth creates the default user and
// the fossy user
plugin_postinstall();
$logger->debug(sprintf("setup plugins in %.3fs", microtime(true) - $startTime));


$Mod = GetParm("mod",PARM_STRING);
if (!isset($Mod)) { $Mod = "Default"; }
$PluginId = plugin_find_id($Mod);
if ($PluginId >= 0)
{
  $startTime = microtime(true);
  /** @var Plugin[] $Plugins */
  $plugin = $Plugins[$PluginId];
  $plugin->execute();
  $logger->debug(sprintf("plugin execution in %.3fs", microtime(true) - $startTime));
}
else
{
  // header("Status: 408 Request Time-out");
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
global $container;
$container->get("db.manager")->flushStats();
return 0;