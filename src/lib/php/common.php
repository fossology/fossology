<?php
/***********************************************************
 Copyright (C) 2008-2013 Hewlett-Packard Development Company, L.P.

 This library is free software; you can redistribute it and/or
 modify it under the terms of the GNU Lesser General Public
 License version 2.1 as published by the Free Software Foundation.

 This library is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 Lesser General Public License for more details.

 You should have received a copy of the GNU Lesser General Public License
 along with this library; if not, write to the Free Software Foundation, Inc.0
 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
***********************************************************/

/**
 * \file common.php
 * \brief These are common functions to be used by anyone.
 */

// setup autoloading
require_once(dirname(dirname(dirname(__FILE__))) . "/vendor/autoload.php");
// setup dependency injection
require_once("common-container.php");

require_once("common-sysconfig.php");
require_once("common-scheduler.php");
require_once("common-menu.php");
require_once("common-plugin.php");
require_once("common-folders.php");
require_once("common-dir.php");
require_once("common-parm.php");
require_once("common-repo.php");
require_once("common-license-file.php");
require_once("common-copyright-file.php");
require_once("common-job.php");
require_once("common-agents.php");
require_once("common-active.php");
require_once("common-cache.php");
require_once("common-ui.php");
require_once("common-buckets.php");
require_once("common-pkg.php");
require_once("common-tags.php");
require_once("common-compare.php");
require_once("common-db.php");
require_once("common-auth.php");
require_once("common-perms.php");
require_once("common-users.php");

/* Only include the command-line interface functions if it is required. */
global $UI_CLI;
if (!empty($UI_CLI) && ($UI_CLI == 1))
{
  require_once("common-cli.php");
}
