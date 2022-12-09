<?php
/*
 SPDX-FileCopyrightText: © 2008-2011 Hewlett-Packard Development Company, L.P.
 SPDX-FileCopyrightText: © 2014 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

use Fossology\Lib\Util\TimingLogger;
use Fossology\UI\Page\HomePage;

/**
 * @file index.php
 * @brief This is the main guts of the UI: Find the plugin and run it.
 */

/* initialize session variable to run test in cli mode */
if (! isset($_SESSION)) {
  $_SESSION = array();
}

$startTime = microtime(true);
require_once(__DIR__ . "/../../lib/php/bootstrap.php");

$PG_CONN = 0;   // Database connection

/* Set SYSCONFDIR and set global (for backward compatibility) */
$SysConf = bootstrap();

global $container;
/** @var TimingLogger $logger */
$timingLogger = $container->get("log.timing");
$timingLogger->logWithStartTime("bootstrap", $startTime);

/* Load UI templates */
$loader = $container->get('twig.loader');
$loader->addPath(dirname(__FILE__).'/template');

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
if ($plugin) {
  $timingLogger->tic();
  $plugin->execute();
  $timingLogger->toc("plugin execution");
} else {
  $linkUri = Traceback_uri() . "?mod=auth";
  $errorText = _("Module unavailable or your login session timed out.");
  print "$errorText <P />";
  $linkText = _("Click here to continue.");
  print "<a href=\"$linkUri\">$linkText</a>";
}
plugin_unload();

$container->get("db.manager")->flushStats();
return 0;
