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

/*************************************************
 Restrict usage: Every PHP file should have this
 at the very beginning.
 This prevents hacking attempts.
 *************************************************/
global $GlobalReady;
if (!isset($GlobalReady)) { exit; }

/*****************************************************************
 These are common functions to be used by anyone.
 *****************************************************************/

require_once("common-menu.php");
require_once("common-plugin.php");
require_once("common-folders.php");
require_once("common-dir.php");
require_once("common-parm.php");
require_once("common-repo.php");
require_once("common-license.php");
require_once("common-job.php");
require_once("common-agents.php");
require_once("common-active.php");
require_once("common-cache.php");
require_once("common-ui.php");

/* Only include the command-line interface functions if it is required. */
global $UI_CLI;
if (!empty($UI_CLI) && ($UI_CLI == 1))
  {
  require_once("common-cli.php");
  }

global $WEBDIR;
require_once("$WEBDIR/template/template-plugin.php");

?>
