<?php
/***********************************************************
 * Copyright (C) 2008-2011 Hewlett-Packard Development Company, L.P.
 * Copyright (C) 2014 Siemens AG
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 ***********************************************************/

use Fossology\Lib\Util\TimingLogger;
use Fossology\UI\Page\HomePage;

/**
 * @file index.php
 * @brief This is the main guts of the UI: Find the plugin and run it.
 */

$startTime = microtime(true);
require_once(__DIR__ . "/../../lib/php/bootstrap.php");

$PG_CONN = 0;   // Database connection

/* Set SYSCONFDIR and set global (for backward compatibility) */
$SysConf = bootstrap();

global $container;
/** @var TimingLogger $logger */
$timingLogger = $container->get("log.timing");
$timingLogger->logWithStartTime("bootstrap", $startTime);

/* Initialize global system configuration variables $SysConfig[] */
$timingLogger->tic();
ConfigInit($SYSCONFDIR, $SysConf);
$timingLogger->toc("setup init");

$timingLogger->tic();
plugin_load();
plugin_preinstall();
plugin_postinstall();
$timingLogger->toc("setup plugins");

$plugin = plugin_find(GetParm("mod", PARM_STRING) ?: HomePage::NAME);
if ($plugin)
{
  $timingLogger->tic();
  $plugin->execute();
  $timingLogger->toc("plugin execution");
} else
{
  $linkUri = Traceback_uri() . "?mod=auth";
  $errorText = _("Module unavailable or your login session timed out.");
  print "$errorText <P />";
  $linkText = _("Click here to continue.");
  print "<a href=\"$linkUri\">$linkText</a>";
}
plugin_unload();

$container->get("db.manager")->flushStats();
return 0;